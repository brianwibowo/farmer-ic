<?php

namespace App\Console\Commands;

use App\AI\Services\AiObservability;
use App\Models\Team;
use Illuminate\Console\Command;

class AiHealthCommand extends Command
{
    protected $signature = 'ai:health
        {--team= : Team slug to inspect; defaults to all teams}
        {--minutes=60 : Lookback window for telemetry}
        {--p95-budget=60000 : Maximum completion p95 latency in milliseconds}
        {--error-budget=0.10 : Maximum completion error rate}
        {--json : Print the complete machine-readable report}';

    protected $description = 'Report AI pipeline health, latency, retrieval, citation, and fallback telemetry';

    public function handle(AiObservability $observability): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $team = $this->team();
        $report = $observability->report($team?->id, $minutes);
        $p95Budget = max(1, (int) $this->option('p95-budget'));
        $errorBudget = max(0.0, min(1.0, (float) $this->option('error-budget')));
        $p95 = data_get($report, 'latency_ms.p95');
        $errorRate = (float) data_get($report, 'quality.error_rate', 0.0);
        $hasProvider = trim((string) config('ai.openrouter.api_key', '')) !== '';
        $vectorReady = ! (bool) config('ai.rag.vector_enabled') || trim((string) config('ai.qdrant.url', '')) !== '';
        $checks = [
            'provider_configured' => $hasProvider,
            'retrieval_configured' => $vectorReady,
            'error_rate_within_budget' => $errorRate <= $errorBudget,
            'p95_within_budget' => $p95 === null || (float) $p95 <= $p95Budget,
        ];
        $healthy = ! in_array(false, $checks, true);
        $report['health'] = [
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'budgets' => [
                'p95_latency_ms' => $p95Budget,
                'error_rate' => $errorBudget,
            ],
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->components->info(sprintf(
                'AI health: %s (%d-minute window%s)',
                strtoupper((string) data_get($report, 'health.status')),
                $minutes,
                $team === null ? '' : ', team '.$team->slug,
            ));
            $this->table(['Metric', 'Value'], [
                ['completion events', data_get($report, 'events.completion', 0)],
                ['failed events', data_get($report, 'events.failed', 0)],
                ['error rate', data_get($report, 'quality.error_rate', 0.0)],
                ['latency p95 (ms)', data_get($report, 'latency_ms.p95', 'n/a')],
                ['first token p95 (ms)', data_get($report, 'latency_ms.first_token_p95', 'n/a')],
                ['citation pass rate', data_get($report, 'quality.citation_pass_rate', 'n/a')],
                ['fallbacks', data_get($report, 'events.fallbacks', 0)],
                ['retrievers', implode(', ', array_keys((array) data_get($report, 'retrieval.retrievers', []))) ?: 'n/a'],
            ]);
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }

    private function team(): ?Team
    {
        $slug = $this->option('team');

        return is_string($slug) && trim($slug) !== ''
            ? Team::query()->where('slug', trim($slug))->first()
            : null;
    }
}
