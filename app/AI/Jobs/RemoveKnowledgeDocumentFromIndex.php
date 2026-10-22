<?php

namespace App\AI\Jobs;

use App\AI\Contracts\VectorStore;
use App\AI\Contracts\VectorStoreFilterLifecycle;
use App\AI\Contracts\VectorStoreLifecycle;
use App\Models\AiKnowledgeChunk;
use App\Models\AiKnowledgeDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Remove every vector belonging to a document that is no longer published.
 *
 * Point ids are removed first when chunk rows still exist. The payload filter
 * is also applied so this remains correct after a hard delete or a previous
 * partial cleanup. A document that was republished before this job runs is
 * left alone, which prevents an old archive/update job from deleting fresh
 * vectors.
 */
final class RemoveKnowledgeDocumentFromIndex implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly int $documentId,
        public readonly int $teamId,
    ) {
        $this->onQueue('ai-index');
    }

    public function handle(VectorStore $vectors): void
    {
        if (! $vectors instanceof VectorStoreLifecycle) {
            Log::warning('RemoveKnowledgeDocumentFromIndex: vector store has no lifecycle support.', [
                'document_id' => $this->documentId,
                'team_id' => $this->teamId,
            ]);

            return;
        }

        $document = AiKnowledgeDocument::query()
            ->whereKey($this->documentId)
            ->where('team_id', $this->teamId)
            ->first();

        // If a document was archived/edited and then republished before this
        // queue item ran, its current vectors are valid and must be retained.
        if ($document?->status === AiKnowledgeDocument::STATUS_PUBLISHED) {
            return;
        }

        $pointIds = $document?->chunks()
            ->pluck('qdrant_point_id')
            ->filter(static fn (mixed $id): bool => is_string($id) && trim($id) !== '')
            ->map(static fn (string $id): string => $id)
            ->values()
            ->all() ?? [];

        if ($pointIds !== []) {
            $vectors->deletePoints($pointIds);
        }

        if ($vectors instanceof VectorStoreFilterLifecycle) {
            $vectors->deleteByFilter([
                'must' => [
                    ['key' => 'team_id', 'match' => ['value' => $this->teamId]],
                ],
                // Older indexers stored document_id as a string while the
                // per-document job stores it as an integer. Qdrant's top
                // level should condition handles both payload types.
                'should' => [
                    ['key' => 'document_id', 'match' => ['value' => $this->documentId]],
                    ['key' => 'document_id', 'match' => ['value' => (string) $this->documentId]],
                ],
            ]);
        }

        // Mark chunks pending so a later publish re-embeds them instead of
        // trusting rows whose vectors have just been removed.
        $document?->chunks()->update([
            'status' => AiKnowledgeChunk::STATUS_PENDING,
            'qdrant_point_id' => null,
            'embedding_model' => null,
            'indexed_at' => null,
        ]);

        $document?->forceFill([
            'index_status' => AiKnowledgeDocument::INDEX_STATUS_PENDING,
            'index_version' => null,
            'indexed_at' => null,
            'index_error' => null,
        ])->save();
    }
}
