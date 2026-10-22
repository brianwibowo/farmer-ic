<?php

namespace App\AI\Services;

use App\AI\Contracts\EmbeddingGateway;
use App\AI\Contracts\VectorStore;
use App\AI\Contracts\VectorStoreFilterLifecycle;
use App\AI\Contracts\VectorStoreLifecycle;
use App\Models\AiKnowledgeChunk;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class QdrantKnowledgeIndexer
{
    public function __construct(
        private readonly KnowledgeService $knowledge,
        private readonly KnowledgeChunker $chunker,
        private readonly EmbeddingGateway $embeddings,
        private readonly VectorStore $vectors,
        private readonly ?IndexManifestStore $manifests = null,
    ) {}

    /**
     * Index a team's current published corpus.
     *
     * Existing points are never deleted until every new batch has been
     * embedded and upserted successfully. A manifest makes repeated runs
     * idempotent and gives a later run enough information to remove points
     * belonging to documents that were deleted or unpublished.
     *
     * @return array{documents: int, chunks: int, tokens: int, model: string, skipped: bool, removed: int, index_version: string, fingerprint: string, manifest_path: string}
     */
    public function indexTeam(Team $team, bool $force = false): array
    {
        $manifestStore = $this->manifests ?? new IndexManifestStore;
        $indexVersion = (string) config('ai.rag.index_version', 'embed-v1-chunk-v1');
        $embeddingModel = (string) config('ai.embeddings.model', '');
        $dimensions = (int) config('ai.embeddings.dimensions', 1536);
        $chunks = [];
        $documents = $this->knowledge->retrievalDocuments($team);
        foreach ($documents as $document) {
            foreach ($this->chunker->chunk(
                (string) $document['content'],
                (string) $document['title'],
                (string) $document['source'],
                (string) $document['id'],
            ) as $chunk) {
                $chunks[] = [
                    ...$chunk,
                    'document_id' => (string) $document['id'],
                    'version' => $document['version'] ?? null,
                ];
            }
        }

        $fingerprint = $this->fingerprint($chunks, $indexVersion, $embeddingModel, $dimensions);
        $manifestPath = $manifestStore->path($team);
        $previous = $manifestStore->get($team);
        $removedOnFastPath = 0;
        if (! $force && is_array($previous) && hash_equals(
            (string) ($previous['fingerprint'] ?? ''),
            $fingerprint,
        )) {
            // A manifest alone must not make a deleted/reset collection look
            // current. Reconcile the expected team point count before taking
            // the idempotent fast path; failures fall through to a rebuild.
            if ($this->vectors instanceof VectorStoreLifecycle) {
                try {
                    $indexed = $this->vectors->count([
                        'must' => [
                            ['key' => 'team_id', 'match' => ['value' => $team->getKey()]],
                            ['key' => 'status', 'match' => ['value' => 'published']],
                            ['key' => 'index_version', 'match' => ['value' => $indexVersion]],
                        ],
                    ]);
                    if ($indexed !== count((array) ($previous['point_ids'] ?? []))) {
                        $previous = null;
                    } elseif ($this->vectors instanceof VectorStoreFilterLifecycle) {
                        // A collection can contain a stale point with the
                        // same total count (for example after a manual
                        // restore). Reconcile by chunk identity before the
                        // idempotent return; this also removes old index
                        // versions that retrieval would otherwise see.
                        $removedOnFastPath = $this->reconcileStalePoints($team, $chunks);
                    }
                } catch (\RuntimeException) {
                    $previous = null;
                }
            }
        }
        if (! $force && is_array($previous) && hash_equals(
            (string) ($previous['fingerprint'] ?? ''),
            $fingerprint,
        )) {
            return [
                'documents' => (int) ($previous['documents'] ?? count($documents)),
                'chunks' => (int) ($previous['chunks'] ?? count($chunks)),
                'tokens' => 0,
                'model' => (string) ($previous['model'] ?? $embeddingModel),
                'skipped' => true,
                'removed' => $removedOnFastPath,
                'index_version' => $indexVersion,
                'fingerprint' => $fingerprint,
                'manifest_path' => $manifestPath,
            ];
        }

        if ($chunks === []) {
            $this->vectors->ensureCollection($dimensions);
            $removed = $this->reconcileStalePoints($team, $chunks);
            $manifestStore->put($team, [
                'schema_version' => 1,
                'team_id' => (string) $team->getKey(),
                'index_version' => $indexVersion,
                'fingerprint' => $fingerprint,
                'model' => $embeddingModel,
                'dimensions' => $dimensions,
                'documents' => 0,
                'chunks' => 0,
                'point_ids' => [],
                'generated_at' => Carbon::now()->toIso8601String(),
            ]);

            return [
                'documents' => 0,
                'chunks' => 0,
                'tokens' => 0,
                'model' => $embeddingModel,
                'skipped' => false,
                'removed' => $removed,
                'index_version' => $indexVersion,
                'fingerprint' => $fingerprint,
                'manifest_path' => $manifestPath,
            ];
        }

        $this->vectors->ensureCollection($dimensions);
        $batchSize = max(1, (int) config('ai.embeddings.batch_size', 32));
        $totalTokens = 0;
        $model = '';

        foreach (array_chunk($chunks, $batchSize) as $batch) {
            $embedded = $this->embeddings->embed(array_map(
                static fn (array $chunk): string => $chunk['content'],
                $batch,
            ));
            $vectors = $embedded['vectors'];
            $model = $embedded['model'];
            $totalTokens += (int) ($embedded['usage']['total_tokens'] ?? 0);
            if (count($vectors) !== count($batch)) {
                throw new \RuntimeException('Embedding batch does not match knowledge chunks.');
            }

            $points = [];
            foreach ($batch as $index => $chunk) {
                $pointId = $this->pointId((string) $chunk['id'], $team);
                $points[] = [
                    'id' => $pointId,
                    'vector' => $vectors[$index],
                    'payload' => [
                        'chunk_id' => $chunk['id'],
                        'document_id' => $chunk['document_id'],
                        'title' => $chunk['title'],
                        'source' => $chunk['source'],
                        'content' => $chunk['content'],
                        'version' => $chunk['version'],
                        'team_id' => $team->getKey(),
                        'status' => 'published',
                        'index_version' => $indexVersion,
                    ],
                ];

                $this->persistChunk($chunk, $pointId, $model, $indexVersion, $team);
            }
            $this->vectors->upsert($points);
        }

        $pointIds = array_map(
            fn (array $chunk): string => $this->pointId((string) $chunk['id'], $team),
            $chunks,
        );
        $previousPointIds = is_array($previous['point_ids'] ?? null) ? $previous['point_ids'] : [];
        $stalePointIds = array_values(array_diff(
            array_map('strval', $previousPointIds),
            $pointIds,
        ));
        $removed = $this->reconcileStalePoints($team, $chunks, $stalePointIds);

        $manifestStore->put($team, [
            'schema_version' => 1,
            'team_id' => (string) $team->getKey(),
            'index_version' => $indexVersion,
            'fingerprint' => $fingerprint,
            'model' => $model ?: $embeddingModel,
            'dimensions' => $dimensions,
            'documents' => count($documents),
            'chunks' => count($chunks),
            'point_ids' => $pointIds,
            'generated_at' => Carbon::now()->toIso8601String(),
        ]);

        return [
            'documents' => count($documents),
            'chunks' => count($chunks),
            'tokens' => $totalTokens,
            'model' => $model,
            'skipped' => false,
            'removed' => $removed,
            'index_version' => $indexVersion,
            'fingerprint' => $fingerprint,
            'manifest_path' => $manifestPath,
        ];
    }

    /** @return array<string, mixed> */
    public function status(Team $team): array
    {
        $manifestStore = $this->manifests ?? new IndexManifestStore;
        $manifest = $manifestStore->get($team);
        $status = [
            'manifest' => $manifest,
            'manifest_path' => $manifestStore->path($team),
            'indexed' => is_array($manifest),
        ];
        if ($this->vectors instanceof VectorStoreLifecycle) {
            $status['vector_health'] = $this->vectors->health();
            $status['collection'] = $this->vectors->collectionInfo();
        }

        return $status;
    }

    /** @param array<int, array<string, mixed>> $chunks */
    private function fingerprint(array $chunks, string $indexVersion, string $model, int $dimensions): string
    {
        $canonical = array_map(static fn (array $chunk): array => [
            'id' => (string) ($chunk['id'] ?? ''),
            'document_id' => (string) ($chunk['document_id'] ?? ''),
            'content' => (string) ($chunk['content'] ?? ''),
            'title' => (string) ($chunk['title'] ?? ''),
            'source' => (string) ($chunk['source'] ?? ''),
            'version' => $chunk['version'] ?? null,
        ], $chunks);

        return hash('sha256', (string) json_encode([
            'index_version' => $indexVersion,
            'embedding_model' => $model,
            'dimensions' => $dimensions,
            'chunks' => $canonical,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Remove points for this team that are not part of the freshly embedded
     * corpus. Qdrant's payload filter is the source of truth when available;
     * the manifest point ids remain a safe fallback for simpler stores.
     *
     * @param  array<int, array<string, mixed>>  $chunks
     * @param  array<int, string>  $fallbackIds
     */
    private function reconcileStalePoints(Team $team, array $chunks, array $fallbackIds = []): int
    {
        if ($this->vectors instanceof VectorStoreFilterLifecycle) {
            $filter = [
                'must' => [
                    ['key' => 'team_id', 'match' => ['value' => $team->getKey()]],
                    ['key' => 'status', 'match' => ['value' => 'published']],
                ],
            ];

            $chunkIds = array_values(array_unique(array_map(
                static fn (array $chunk): string => (string) ($chunk['id'] ?? ''),
                $chunks,
            )));
            $chunkIds = array_values(array_filter($chunkIds, static fn (string $id): bool => $id !== ''));
            if ($chunkIds !== []) {
                $filter['must_not'] = [[
                    'key' => 'chunk_id',
                    'match' => ['any' => $chunkIds],
                ]];
            }

            // Count before deletion so command output reports a useful
            // reconciliation result. The delete itself is intentionally
            // after every upsert has completed successfully.
            $removed = $this->vectors instanceof VectorStoreLifecycle
                ? $this->vectors->count($filter)
                : 0;
            $this->vectors->deleteByFilter($filter);

            return $removed;
        }

        if ($fallbackIds !== [] && $this->vectors instanceof VectorStoreLifecycle) {
            $this->vectors->deletePoints($fallbackIds);

            return count($fallbackIds);
        }

        return 0;
    }

    /**
     * Persist a chunk to the canonical database store when the migration is available.
     */
    /** @param array<string, mixed> $chunk */
    private function persistChunk(array $chunk, string $pointId, string $embeddingModel, string $indexVersion, Team $team): void
    {
        if (! Schema::hasTable('ai_knowledge_chunks')) {
            return;
        }

        $documentId = $chunk['document_id'] ?? null;
        if ($documentId === null || $documentId === '' || str_starts_with((string) $documentId, 'application-') || str_starts_with((string) $documentId, 'assistant-') || str_starts_with((string) $documentId, 'demo-')) {
            return;
        }

        $chunkIndex = 0;
        if (preg_match('/:chunk:(\d+)$/', (string) $chunk['id'], $m)) {
            $chunkIndex = (int) $m[1];
        }

        $contentHash = hash('sha256', $chunk['content']);
        $chunkingVersion = (string) config('ai.rag.chunking_version', $indexVersion);

        AiKnowledgeChunk::query()->updateOrCreate(
            [
                'ai_knowledge_document_id' => $documentId,
                'chunk_index' => $chunkIndex,
                'content_hash' => $contentHash,
            ],
            [
                'team_id' => $team->getKey(),
                'content' => $chunk['content'],
                'chunking_version' => $chunkingVersion,
                'embedding_model' => $embeddingModel,
                'qdrant_point_id' => $pointId,
                'token_count' => (int) ceil(mb_strlen($chunk['content']) / 3.5),
                'metadata' => [
                    'title' => $chunk['title'] ?? '',
                    'source' => $chunk['source'] ?? '',
                    'heading' => $chunk['heading'] ?? '',
                ],
                'status' => AiKnowledgeChunk::STATUS_INDEXED,
                'indexed_at' => now(),
            ],
        );
    }

    private function pointId(string $chunkId, Team $team): string
    {
        $hex = substr(hash('sha256', $team->getKey().':'.$chunkId), 0, 32);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-4'.substr($hex, 13, 3).'-8'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }
}
