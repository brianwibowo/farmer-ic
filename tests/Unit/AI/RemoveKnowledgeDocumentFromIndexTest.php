<?php

namespace Tests\Unit\AI;

use App\AI\Contracts\VectorStore;
use App\AI\Contracts\VectorStoreFilterLifecycle;
use App\AI\Contracts\VectorStoreLifecycle;
use App\AI\Jobs\RemoveKnowledgeDocumentFromIndex;
use App\Models\AiKnowledgeChunk;
use App\Models\AiKnowledgeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemoveKnowledgeDocumentFromIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_document_points_and_marks_chunks_pending(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $document = AiKnowledgeDocument::query()->create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'title' => 'Panduan irigasi',
            'content' => 'Siram pagi hari.',
            'status' => AiKnowledgeDocument::STATUS_ARCHIVED,
            'content_hash' => hash('sha256', 'Siram pagi hari.'),
            'version' => 2,
        ]);
        $chunk = AiKnowledgeChunk::query()->create([
            'ai_knowledge_document_id' => $document->id,
            'team_id' => $team->id,
            'chunk_index' => 0,
            'content' => $document->content,
            'content_hash' => hash('sha256', $document->content),
            'chunking_version' => 'chunk-v1',
            'embedding_model' => 'fake-embedding',
            'qdrant_point_id' => 'point-1',
            'status' => AiKnowledgeChunk::STATUS_INDEXED,
            'indexed_at' => now(),
        ]);
        $store = new RecordingVectorStore;

        (new RemoveKnowledgeDocumentFromIndex($document->id, $team->id))->handle($store);

        $this->assertSame(['point-1'], $store->deletedIds);
        $this->assertSame($team->id, data_get($store->deletedFilter, 'must.0.match.value'));
        $this->assertCount(2, data_get($store->deletedFilter, 'should'));
        $chunk->refresh();
        $document->refresh();
        $this->assertSame(AiKnowledgeChunk::STATUS_PENDING, $chunk->status);
        $this->assertNull($chunk->qdrant_point_id);
        $this->assertNull($chunk->embedding_model);
        $this->assertNull($chunk->indexed_at);
        $this->assertSame(AiKnowledgeDocument::INDEX_STATUS_PENDING, $document->index_status);
        $this->assertNull($document->index_version);
    }

    public function test_it_does_not_remove_vectors_after_a_document_is_republished(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $document = AiKnowledgeDocument::query()->create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'title' => 'Panduan irigasi',
            'content' => 'Siram pagi hari.',
            'status' => AiKnowledgeDocument::STATUS_PUBLISHED,
            'content_hash' => hash('sha256', 'Siram pagi hari.'),
            'version' => 3,
        ]);
        $store = new RecordingVectorStore;

        (new RemoveKnowledgeDocumentFromIndex($document->id, $team->id))->handle($store);

        $this->assertSame([], $store->deletedIds);
        $this->assertNull($store->deletedFilter);
    }
}

final class RecordingVectorStore implements VectorStore, VectorStoreFilterLifecycle, VectorStoreLifecycle
{
    /** @var array<int, string> */
    public array $deletedIds = [];

    /** @var array<string, mixed>|null */
    public ?array $deletedFilter = null;

    public function ensureCollection(int $dimensions): void {}

    public function upsert(array $points): void {}

    public function query(array $vector, int $limit, array $filter = []): array
    {
        return [];
    }

    public function deletePoints(array $ids): void
    {
        $this->deletedIds = $ids;
    }

    public function deleteByFilter(array $filter): void
    {
        $this->deletedFilter = $filter;
    }

    public function health(): array
    {
        return ['ok' => true, 'status' => 200];
    }

    public function collectionInfo(): array
    {
        return [];
    }

    public function count(array $filter = []): int
    {
        return 0;
    }
}
