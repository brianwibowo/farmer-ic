<?php

namespace App\AI\Jobs;

use App\AI\Contracts\EmbeddingGateway;
use App\AI\Contracts\VectorStore;
use App\AI\Contracts\VectorStoreLifecycle;
use App\AI\Exceptions\EmbeddingException;
use App\AI\Exceptions\VectorStoreException;
use App\AI\Services\KnowledgeChunker;
use App\Models\AiKnowledgeChunk;
use App\Models\AiKnowledgeDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Index a single published knowledge document: chunk → embed → upsert Qdrant.
 *
 * The job is idempotent: re-dispatching for the same document skips chunks
 * whose content_hash has not changed. A full rebuild is available via the
 * --force flag on the artisan command.
 */
final class IndexKnowledgeDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(
        public readonly string $documentId,
        public readonly bool $force = false,
    ) {
        $this->onQueue('ai-index');
    }

    public function handle(
        KnowledgeChunker $chunker,
        EmbeddingGateway $embeddings,
        VectorStore $vectors,
    ): void {
        $document = AiKnowledgeDocument::query()->find($this->documentId);
        if (! $document) {
            return;
        }

        if ($document->status !== AiKnowledgeDocument::STATUS_PUBLISHED) {
            Log::info('IndexKnowledgeDocument: skipped non-published document.', [
                'document_id' => $this->documentId,
                'status' => $document->status,
            ]);

            return;
        }

        $document->forceFill([
            'index_status' => AiKnowledgeDocument::INDEX_STATUS_INDEXING,
            'index_error' => null,
        ])->save();

        try {
            $this->indexDocument($document, $chunker, $embeddings, $vectors);

            $document->forceFill([
                'index_status' => AiKnowledgeDocument::INDEX_STATUS_INDEXED,
                'index_version' => config('ai.rag.index_version', 'embed-v1-chunk-v1'),
                'indexed_at' => now(),
                'index_error' => null,
            ])->save();
        } catch (EmbeddingException|VectorStoreException|\RuntimeException $exception) {
            $document->forceFill([
                'index_status' => AiKnowledgeDocument::INDEX_STATUS_FAILED,
                'index_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();

            Log::error('IndexKnowledgeDocument: indexing failed.', [
                'document_id' => $this->documentId,
                'error_type' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function indexDocument(
        AiKnowledgeDocument $document,
        KnowledgeChunker $chunker,
        EmbeddingGateway $embeddings,
        VectorStore $vectors,
    ): void {
        $chunkingVersion = (string) config('ai.rag.chunking_version', config('ai.rag.index_version', 'chunk-v1'));
        $indexVersion = (string) config('ai.rag.index_version', 'embed-v1-chunk-v1');
        $embeddingModel = (string) config('ai.embeddings.model', '');
        $dimensions = (int) config('ai.embeddings.dimensions', 1536);
        $teamId = (int) $document->team_id;

        $rawChunks = $chunker->chunk(
            (string) $document->content,
            (string) $document->title,
            (string) ($document->source ?: 'Knowledge team Farmer IC'),
            (string) $document->getKey(),
        );

        if ($rawChunks === []) {
            $document->chunks()
                ->where('status', '!=', AiKnowledgeChunk::STATUS_PENDING)
                ->update(['status' => AiKnowledgeChunk::STATUS_PENDING]);

            return;
        }

        $vectors->ensureCollection($dimensions);

        $persistedChunks = [];
        foreach ($rawChunks as $index => $raw) {
            $contentHash = hash('sha256', $raw['content']);

            $existing = $document->chunks()
                ->where('chunk_index', $index)
                ->where('content_hash', $contentHash)
                ->where('chunking_version', $chunkingVersion)
                ->first();

            if ($existing && ! $this->force && $existing->status === AiKnowledgeChunk::STATUS_INDEXED) {
                $persistedChunks[] = $existing;

                continue;
            }

            $chunk = $existing ?? new AiKnowledgeChunk;
            $chunk->forceFill([
                'ai_knowledge_document_id' => $document->getKey(),
                'team_id' => $teamId,
                'chunk_index' => $index,
                'content' => $raw['content'],
                'content_hash' => $contentHash,
                'chunking_version' => $chunkingVersion,
                'token_count' => $this->estimateTokens($raw['content']),
                'metadata' => [
                    'title' => $raw['title'],
                    'source' => $raw['source'],
                    'document_id' => (string) $document->getKey(),
                ],
                'status' => AiKnowledgeChunk::STATUS_PENDING,
            ]);
            $chunk->save();
            $persistedChunks[] = $chunk;
        }

        $pendingChunks = array_filter(
            $persistedChunks,
            fn (AiKnowledgeChunk $chunk): bool => $chunk->status !== AiKnowledgeChunk::STATUS_INDEXED || $this->force,
        );

        if ($pendingChunks !== []) {
            $batchSize = max(1, (int) config('ai.embeddings.batch_size', 32));
            foreach (array_chunk($pendingChunks, $batchSize) as $batch) {
                $embedded = $embeddings->embed(array_map(
                    fn (AiKnowledgeChunk $chunk): string => $chunk->content,
                    $batch,
                ));

                $vectors_data = $embedded['vectors'];
                $model = $embedded['model'];

                if (count($vectors_data) !== count($batch)) {
                    throw new \RuntimeException('Embedding batch size mismatch: expected '.count($batch).', got '.count($vectors_data).'.');
                }

                $points = [];
                foreach ($batch as $i => $chunk) {
                    $pointId = $this->pointId($chunk);
                    $points[] = [
                        'id' => $pointId,
                        'vector' => $vectors_data[$i],
                        'payload' => [
                            'chunk_id' => $chunk->getKey(),
                            'document_id' => (string) $document->getKey(),
                            'title' => $chunk->metadata['title'] ?? $document->title,
                            'source' => $chunk->metadata['source'] ?? ($document->source ?: 'Knowledge team Farmer IC'),
                            'content' => $chunk->content,
                            'version' => $document->version,
                            'team_id' => $teamId,
                            'status' => 'published',
                            'domain' => $document->domain,
                            'locale' => $document->locale ?? 'id-ID',
                            'content_hash' => $chunk->content_hash,
                            'index_version' => $indexVersion,
                        ],
                    ];

                    $chunk->forceFill([
                        'qdrant_point_id' => $pointId,
                        'embedding_model' => $model,
                        'status' => AiKnowledgeChunk::STATUS_INDEXED,
                        'indexed_at' => now(),
                    ])->save();
                }

                $vectors->upsert($points);
            }
        }

        $activeChunkIds = collect($persistedChunks)->pluck('id')->all();
        $staleChunks = $document->chunks()
            ->whereNotIn('id', $activeChunkIds)
            ->get();

        if ($staleChunks->isNotEmpty() && $vectors instanceof VectorStoreLifecycle) {
            $stalePointIds = $staleChunks
                ->pluck('qdrant_point_id')
                ->filter()
                ->values()
                ->all();

            if ($stalePointIds !== []) {
                $vectors->deletePoints($stalePointIds);
            }
        }

        $document->chunks()
            ->whereNotIn('id', $activeChunkIds)
            ->delete();
    }

    private function pointId(AiKnowledgeChunk $chunk): string
    {
        $hex = substr(hash('sha256', $chunk->team_id.':'.$chunk->getKey().':'.$chunk->content_hash), 0, 32);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-4'.substr($hex, 13, 3).'-8'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }

    private function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 3.5);
    }
}
