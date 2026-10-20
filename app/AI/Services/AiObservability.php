<?php

namespace App\AI\Services;

use App\Models\AiUsageEvent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only operational metrics for the AI pipeline.
 *
 * Usage events already contain the trace metadata emitted by AiChatService.
 * Keeping aggregation here makes the health command independent from the
 * transport (HTTP/SSE) and keeps dashboards free to consume the same shape.
 */
class AiObservability
{
    /**
     * @return array<string, mixed>
     */
    public function report(?int $teamId = null, int $windowMinutes = 60): array
    {
        $windowMinutes = max(1, $windowMinutes);
        $since = now()->subMinutes($windowMinutes);
        $query = AiUsageEvent::query()
            ->where('created_at', '>=', $since)
            ->when($teamId !== null, fn (Builder $builder): Builder => $builder->where('team_id', $teamId));

        /** @var array<int, array<string, mixed>> $events */
        $events = $query->get([
            'event_type',
            'provider',
            'model',
            'latency_ms',
            'metadata',
            'created_at',
        ])->map(static fn (AiUsageEvent $event): array => [
            'event_type' => (string) $event->event_type,
            'provider' => $event->provider,
            'model' => $event->model,
            'latency_ms' => $event->latency_ms,
            'metadata' => is_array($event->getAttribute('metadata')) ? $event->getAttribute('metadata') : [],
            'created_at' => $event->created_at,
        ])->all();

        $completionEvents = array_values(array_filter(
            $events,
            static fn (array $event): bool => in_array($event['event_type'], ['completion', 'completion_failed', 'deterministic'], true),
        ));
        $completed = count(array_filter(
            $completionEvents,
            static fn (array $event): bool => in_array($event['event_type'], ['completion', 'deterministic'], true),
        ));
        $failed = count(array_filter(
            $completionEvents,
            static fn (array $event): bool => $event['event_type'] === 'completion_failed',
        ));

        $latencies = array_values(array_filter(
            array_map(static fn (array $event): ?float => is_numeric($event['latency_ms']) ? (float) $event['latency_ms'] : null, $completionEvents),
            static fn (?float $latency): bool => $latency !== null,
        ));
        $firstTokenLatencies = [];
        $retrievers = [];
        $routes = [];
        $citationChecks = 0;
        $citationPasses = 0;
        $fallbacks = 0;
        $deterministic = 0;

        foreach ($completionEvents as $event) {
            $metadata = $event['metadata'];
            $retriever = data_get($metadata, 'retrieval.retriever');
            if (is_string($retriever) && $retriever !== '') {
                $retrievers[$retriever] = ($retrievers[$retriever] ?? 0) + 1;
            }

            $intent = data_get($metadata, 'route.intent');
            if (is_string($intent) && $intent !== '') {
                $routes[$intent] = ($routes[$intent] ?? 0) + 1;
            }

            $firstToken = data_get($metadata, 'latency.first_token_ms');
            if (is_numeric($firstToken)) {
                $firstTokenLatencies[] = (float) $firstToken;
            }

            $citation = data_get($metadata, 'citation');
            if (is_array($citation) && array_key_exists('ok', $citation)) {
                $citationChecks++;
                if ((bool) $citation['ok']) {
                    $citationPasses++;
                }
            }

            if (data_get($metadata, 'fallback_index', 0) > 0 || data_get($metadata, 'fallback_attempted', 0) > 1) {
                $fallbacks++;
            }
            if ((bool) data_get($metadata, 'deterministic', false)) {
                $deterministic++;
            }
        }

        ksort($retrievers);
        ksort($routes);

        $total = count($completionEvents);
        $p95 = $this->percentile($latencies, 0.95);
        $firstTokenP95 = $this->percentile($firstTokenLatencies, 0.95);

        return [
            'window' => [
                'minutes' => $windowMinutes,
                'since' => $since->toISOString(),
                'team_id' => $teamId,
            ],
            'events' => [
                'total' => count($events),
                'completion' => $total,
                'completed' => $completed,
                'failed' => $failed,
                'deterministic' => $deterministic,
                'fallbacks' => $fallbacks,
            ],
            'latency_ms' => [
                'mean' => $this->mean($latencies),
                'p95' => $p95,
                'first_token_mean' => $this->mean($firstTokenLatencies),
                'first_token_p95' => $firstTokenP95,
            ],
            'quality' => [
                'error_rate' => $total > 0 ? round($failed / $total, 4) : 0.0,
                'citation_checks' => $citationChecks,
                'citation_passes' => $citationPasses,
                'citation_pass_rate' => $citationChecks > 0 ? round($citationPasses / $citationChecks, 4) : null,
            ],
            'retrieval' => [
                'retrievers' => $retrievers,
                'routes' => $routes,
            ],
            'providers' => $this->counts($completionEvents, 'provider'),
            'models' => $this->counts($completionEvents, 'model'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, int>
     */
    private function counts(array $events, string $key): array
    {
        $counts = [];
        foreach ($events as $event) {
            $value = $event[$key];
            if (! is_string($value) || trim($value) === '') {
                continue;
            }
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /** @param array<int, float> $values */
    private function mean(array $values): ?float
    {
        return $values === [] ? null : round(array_sum($values) / count($values), 3);
    }

    /** @param array<int, float> $values */
    private function percentile(array $values, float $percentile): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values, SORT_NUMERIC);
        $rank = ($percentile * (count($values) - 1));
        $lower = (int) floor($rank);
        $upper = (int) ceil($rank);
        if ($lower === $upper) {
            return round($values[$lower], 3);
        }

        $weight = $rank - $lower;

        return round($values[$lower] + (($values[$upper] - $values[$lower]) * $weight), 3);
    }
}
