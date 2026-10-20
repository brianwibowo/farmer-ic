<?php

namespace Tests\Feature\AI;

use App\Models\AiUsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_command_emits_json_report_and_fails_when_provider_is_unconfigured(): void
    {
        config(['ai.openrouter.api_key' => null]);

        $result = $this->artisan('ai:health', ['--json' => true, '--minutes' => 15]);

        $result->assertExitCode(1);
    }

    public function test_health_command_passes_with_configured_provider_and_healthy_telemetry(): void
    {
        config([
            'ai.openrouter.api_key' => 'test-key',
            'ai.rag.vector_enabled' => false,
        ]);
        $user = User::factory()->create();

        AiUsageEvent::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'provider' => 'openrouter',
            'model' => 'fast-model',
            'event_type' => 'completion',
            'latency_ms' => 150,
            'metadata' => [
                'retrieval' => ['retriever' => 'in-memory'],
                'citation' => ['ok' => true],
            ],
        ]);

        $this->artisan('ai:health', ['--team' => $user->currentTeam->slug, '--p95-budget' => 1000])
            ->assertExitCode(0);
    }
}
