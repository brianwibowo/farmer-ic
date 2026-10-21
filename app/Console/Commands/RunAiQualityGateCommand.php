<?php

namespace App\Console\Commands;

use App\AI\Exceptions\EmbeddingException;
use App\AI\Exceptions\VectorStoreException;
use App\AI\Services\AiQualityGate;
use App\AI\Services\InMemoryKnowledgeRetriever;
use App\AI\Services\VectorKnowledgeRetriever;
use App\Models\Team;
use Illuminate\Console\Command;
use RuntimeException;

class RunAiQualityGateCommand extends Command
{
    protected $signature = 'ai:quality:gate
        {--team= : Team slug to evaluate; defaults to the first team}
        {--dataset= : Golden JSON path, defaults to resources/ai/evals/golden-id.json}
        {--retriever=in-memory : in-memory or vector}
        {--max-p95-ms=1500 : Maximum allowed retrieval p95 latency in milliseconds}
        {--baseline= : Optional previous JSON report for regression comparison}
        {--output= : Optional JSON report output path}';

    protected $aliases = ['ai:eval'];

    protected $description = 'Run the offline P2 golden prompt quality gate';

    public function handle(
        AiQualityGate $gate,
        InMemoryKnowledgeRetriever $inMemory,
        VectorKnowledgeRetriever $vector,
    ): int {
        $team = $this->team();
        if (! $team) {
            $this->components->error('No team found. Create a demo user/team first.');

            return self::FAILURE;
        }

        $cases = $this->dataset();
        $routing = $gate->evaluateRouting($cases);
        $retrieverOption = $this->option('retriever');
        $retrieverName = is_string($retrieverOption) ? strtolower($retrieverOption) : 'in-memory';
        $retriever = match ($retrieverName) {
            'in-memory', 'memory' => $inMemory,
            'vector', 'qdrant' => $vector,
            default => throw new RuntimeException('Retriever must be in-memory or vector.'),
        };

        try {
            $retrieval = $gate->evaluateRetrieval($team, $retriever, $cases);
        } catch (EmbeddingException|VectorStoreException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $citationRows = [];
        foreach ($cases as $case) {
            if (! array_key_exists('citation_fixture', $case)) {
                continue;
            }
            $caseId = (string) ($case['id'] ?? $case['prompt']);
            $rowIndex = array_search($caseId, array_column($retrieval['rows'], 'id'), true);
            $result = $rowIndex === false ? null : ($retrieval['rows'][$rowIndex] ?? null);
            $evidenceCount = is_array($result) ? (int) ($result['evidence_count'] ?? 0) : 0;
            $citationRows[] = [
                'id' => $caseId,
                ...$gate->validateCitations(
                    (string) $case['citation_fixture'],
                    $evidenceCount,
                    (bool) ($case['citation_required'] ?? true),
                ),
            ];
        }
        $citationPasses = count(array_filter($citationRows, static fn (array $row): bool => $row['valid'] === true));
        $citation = [
            'accuracy' => count($citationRows) === 0 ? 0.0 : round($citationPasses / count($citationRows), 4),
            'checked' => count($citationRows),
            'failures' => array_values(array_filter($citationRows, static fn (array $row): bool => $row['valid'] !== true)),
        ];

        $fallback = $gate->validateFallbackChain(array_merge(
            [(string) config('ai.models.primary')],
            (array) config('ai.models.text_fallbacks', []),
        ));
        $p95Budget = (float) $this->option('max-p95-ms');
        if ($p95Budget <= 0) {
            throw new RuntimeException('--max-p95-ms must be greater than zero.');
        }
        $p95Pass = $retrieval['p95_latency_ms'] <= $p95Budget;
        $baseline = $this->baseline();
        $regression = $this->compareBaseline($baseline, [
            'routing_accuracy' => $routing['accuracy'],
            'retrieval_hit_at_1' => $retrieval['hit_at_1'],
            'citation_accuracy' => $citation['accuracy'],
            'retrieval_p95_latency_ms' => $retrieval['p95_latency_ms'],
        ]);
        $report = [
            'dataset' => $this->datasetPath(),
            'retriever' => $retrieverName,
            'summary' => [
                'cases' => count($cases),
                'routing_accuracy' => $routing['accuracy'],
                'retrieval_hit_at_1' => $retrieval['hit_at_1'],
                'retrieval_mean_latency_ms' => $retrieval['mean_latency_ms'],
                'retrieval_p95_latency_ms' => $retrieval['p95_latency_ms'],
                'retrieval_p95_budget_ms' => $p95Budget,
                'retrieval_p95_pass' => $p95Pass,
                'citation_accuracy' => $citation['accuracy'],
                'fallback_chain_valid' => $fallback['valid'],
                'regression_pass' => $regression['pass'],
                'overall_pass' => $routing['failures'] === []
                    && $retrieval['hit_at_1'] >= 0.90
                    && $p95Pass
                    && $citation['failures'] === []
                    && $fallback['valid']
                    && $regression['pass'],
            ],
            'routing' => $routing,
            'retrieval' => $retrieval,
            'citation' => $citation,
            'fallback' => $fallback,
            'baseline' => $regression,
        ];

        $this->table(
            ['Gate', 'Result'],
            [
                ['routing accuracy', $routing['accuracy']],
                ['retrieval Hit@1', $retrieval['hit_at_1']],
                ['retrieval p95 (ms)', $retrieval['p95_latency_ms']],
                ['p95 budget', $p95Pass ? "pass (<= {$p95Budget} ms)" : "fail (> {$p95Budget} ms)"],
                ['citation accuracy', $citation['accuracy']],
                ['fallback chain', $fallback['valid'] ? 'valid' : 'invalid'],
                ['baseline regression', $regression['pass'] ? 'pass' : 'fail'],
                ['overall', $report['summary']['overall_pass'] ? 'PASS' : 'FAIL'],
            ],
        );

        $output = $this->option('output');
        if (is_string($output) && trim($output) !== '') {
            $path = str_starts_with($output, DIRECTORY_SEPARATOR) ? $output : base_path($output);
            $directory = dirname($path);
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException("Unable to create quality report directory [{$directory}].");
            }
            file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $this->components->info("Report written to {$path}");
        }

        return $report['summary']['overall_pass'] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<int, array<string, mixed>> */
    private function dataset(): array
    {
        $path = $this->datasetPath();
        if (! is_file($path)) {
            throw new RuntimeException("Golden dataset [{$path}] is unavailable.");
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Golden dataset must be a JSON array.');
        }

        return array_values(array_filter($decoded, static fn (mixed $case): bool => is_array($case) && isset($case['prompt'])));
    }

    /** @return array<string, mixed>|null */
    private function baseline(): ?array
    {
        $path = $this->option('baseline');
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
        if (! is_file($path)) {
            throw new RuntimeException("Baseline report [{$path}] is unavailable.");
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>|null  $baseline
     * @param  array<string, float|int>  $current
     * @return array{pass: bool, failures: array<int, string>, baseline: array<string, mixed>|null}
     */
    private function compareBaseline(?array $baseline, array $current): array
    {
        if ($baseline === null) {
            return ['pass' => true, 'failures' => [], 'baseline' => null];
        }

        $previous = is_array($baseline['summary'] ?? null) ? $baseline['summary'] : [];
        $failures = [];
        foreach (['routing_accuracy', 'retrieval_hit_at_1', 'citation_accuracy'] as $metric) {
            if (isset($previous[$metric]) && (float) $current[$metric] + 0.0001 < (float) $previous[$metric]) {
                $failures[] = "{$metric}_regressed";
            }
        }
        if (isset($previous['retrieval_p95_latency_ms'])
            && (float) $current['retrieval_p95_latency_ms'] > (float) $previous['retrieval_p95_latency_ms'] * 1.25
        ) {
            $failures[] = 'retrieval_p95_regressed';
        }

        return ['pass' => $failures === [], 'failures' => $failures, 'baseline' => $previous];
    }

    private function datasetPath(): string
    {
        $path = $this->option('dataset');
        $path = is_string($path) && trim($path) !== '' ? $path : resource_path('ai/evals/golden-id.json');

        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }

    private function team(): ?Team
    {
        $slug = $this->option('team');

        return is_string($slug) && trim($slug) !== ''
            ? Team::query()->where('slug', trim($slug))->first()
            : Team::query()->orderBy('id')->first();
    }
}
