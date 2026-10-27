<?php

namespace Tests\Feature\AI;

use App\AI\Jobs\RemoveKnowledgeDocumentFromIndex;
use App\Models\AiKnowledgeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiKnowledgeDocumentIndexLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_a_published_document_dispatches_vector_cleanup(): void
    {
        config(['ai.rag.vector_enabled' => true]);
        Queue::fake();
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $document = $this->document($team->id, $user->id);

        $this->actingAs($user)
            ->patchJson(route('ai.knowledge.update', [$team->slug, $document]), [
                'title' => 'Updated guide',
                'content' => 'Updated content.',
            ])
            ->assertOk()
            ->assertJsonPath('status', AiKnowledgeDocument::STATUS_DRAFT);

        Queue::assertPushed(RemoveKnowledgeDocumentFromIndex::class, fn (RemoveKnowledgeDocumentFromIndex $job): bool => $job->documentId === $document->id
            && $job->teamId === $team->id);
    }

    public function test_archiving_a_published_document_dispatches_vector_cleanup(): void
    {
        config(['ai.rag.vector_enabled' => true]);
        Queue::fake();
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $document = $this->document($team->id, $user->id);

        $this->actingAs($user)
            ->postJson(route('ai.knowledge.archive', [$team->slug, $document]))
            ->assertOk()
            ->assertJsonPath('status', AiKnowledgeDocument::STATUS_ARCHIVED);

        Queue::assertPushed(RemoveKnowledgeDocumentFromIndex::class);
    }

    public function test_deleting_a_document_dispatches_filter_cleanup_after_cascade(): void
    {
        config(['ai.rag.vector_enabled' => true]);
        Queue::fake();
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $document = $this->document($team->id, $user->id);

        $this->actingAs($user)
            ->deleteJson(route('ai.knowledge.destroy', [$team->slug, $document]))
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('ai_knowledge_documents', ['id' => $document->id]);
        Queue::assertPushed(RemoveKnowledgeDocumentFromIndex::class, fn (RemoveKnowledgeDocumentFromIndex $job): bool => $job->documentId === $document->id
            && $job->teamId === $team->id);
    }

    private function document(int $teamId, string $userId): AiKnowledgeDocument
    {
        return AiKnowledgeDocument::query()->create([
            'team_id' => $teamId,
            'created_by' => $userId,
            'title' => 'Original guide',
            'content' => 'Original content.',
            'status' => AiKnowledgeDocument::STATUS_PUBLISHED,
            'content_hash' => hash('sha256', 'Original content.'),
            'version' => 1,
            'published_at' => now(),
        ]);
    }
}
