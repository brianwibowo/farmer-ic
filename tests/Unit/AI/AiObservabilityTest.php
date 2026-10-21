<?php

namespace Tests\Unit\AI;

use App\AI\Services\AiObservability;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiUsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_aggregates_latency_quality_retrieval_and_fallback_metrics(): void
    {
        $user = User::factory()->create();
        $conversation = AiConversation::factory()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
        ]);
        $first = AiMessage::factory()->assistant()->create([
            'ai_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'provider' => 'openrouter',
            'model' => 'fast-model',
            'latency_ms' => 100,
            'metadata' => [
                'route' => ['intent' => 'knowledge'],
                'retrieval' => ['retriever' => 'qdrant'],
                'latency' => ['first_token_ms' => 20],
                'citation' => ['ok' => true],
                'fallback' => ['used' => false],
            ],
        ]);
        $second = AiMessage::factory()->assistant()->create([
            'ai_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'provider' => 'openrouter',
            'model' => 'fallback-model',
            'latency_ms' => 300,
            'metadata' => [
                'route' => ['intent' => 'llm'],
                'retrieval' => ['retriever' => 'in-memory', 'fallback' => true],
                'latency' => ['first_token_ms' => 90],
                'citation' => ['ok' => false, 'issues' => ['citation_missing']],
                'fallback_index' => 1,
                'fallback' => ['used' => true],
            ],
        ]);

        AiUsageEvent::create([
            'team_id' => $conversation->team_id,
            'user_id' => $user->id,
            'ai_conversation_id' => $conversation->id,
            'ai_message_id' => $first->id,
            'provider' => $first->provider,
            'model' => $first->model,
            'event_type' => 'completion',
            'latency_ms' => $first->latency_ms,
            'metadata' => $first->metadata,
        ]);
        AiUsageEvent::create([
            'team_id' => $conversation->team_id,
            'user_id' => $user->id,
            'ai_conversation_id' => $conversation->id,
            'ai_message_id' => $second->id,
            'provider' => $second->provider,
            'model' => $second->model,
            'event_type' => 'completion_failed',
            'latency_ms' => $second->latency_ms,
            'metadata' => $second->metadata,
        ]);

        $report = app(AiObservability::class)->report($conversation->team_id, 60);

        $this->assertSame(2, data_get($report, 'events.completion'));
        $this->assertSame(1, data_get($report, 'events.failed'));
        $this->assertSame(1, data_get($report, 'events.fallbacks'));
        $this->assertSame(200.0, data_get($report, 'latency_ms.mean'));
        $this->assertSame(290.0, data_get($report, 'latency_ms.p95'));
        $this->assertSame(86.5, data_get($report, 'latency_ms.first_token_p95'));
        $this->assertSame(0.5, data_get($report, 'quality.error_rate'));
        $this->assertSame(0.5, data_get($report, 'quality.citation_pass_rate'));
        $this->assertSame(['in-memory' => 1, 'qdrant' => 1], data_get($report, 'retrieval.retrievers'));
    }

    public function test_report_can_include_all_teams_and_returns_null_metrics_without_events(): void
    {
        User::factory()->create();

        $report = app(AiObservability::class)->report(null, 5);

        $this->assertSame(0, data_get($report, 'events.total'));
        $this->assertNull(data_get($report, 'latency_ms.p95'));
        $this->assertNull(data_get($report, 'quality.citation_pass_rate'));
        $this->assertSame(5, data_get($report, 'window.minutes'));
    }
}
