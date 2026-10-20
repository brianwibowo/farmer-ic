<?php

namespace App\Console\Commands;

use App\AI\Exceptions\EmbeddingException;
use App\AI\Exceptions\VectorStoreException;
use App\AI\Services\KnowledgeRetrievalBenchmark;
use App\Models\Team;
use Illuminate\Console\Command;
use RuntimeException;

class BenchmarkKnowledgeRetrievalCommand extends Command
{
    protected $signature = 'ai:knowledge:benchmark
        {--team= : Team slug to benchmark; defaults to the first team}
        {--dataset= : JSON dataset path, defaults to resources/ai/evals/retrieval-id.json}
        {--limit= : Only run the first N prompts}
        {--output= : Optional JSON report output path}';

    protected $description = 'Compare in-memory and Qdrant retrieval on Indonesian prompts';

    public function handle(KnowledgeRetrievalBenchmark $benchmark): int
    {
        if (! config('ai.rag.vector_enabled')) {
            $this->components->error('AI_RAG_VECTOR_ENABLED=false. Set it to true after Qdrant and the embedding provider are configured.');

            return self::FAILURE;
        }

        $team = $this->team();
        if (! $team) {
            $this->components->error('No team found. Create a demo user/team first.');

            return self::FAILURE;
        }

        $cases = $this->dataset();
        $limit = $this->option('limit');
        if (is_string($limit) && ctype_digit($limit)) {
            $cases = array_slice($cases, 0, max(1, (int) $limit));
        }

        try {
            $report = $benchmark->run($team, $cases);
        } catch (EmbeddingException|VectorStoreException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->table(
            ['Retriever', 'Hit@1', 'Mean latency (ms)', 'P95 latency (ms)'],
            [
                [
                    'in-memory',
                    $report['summary']['in_memory_hit_at_1'],
                    $report['summary']['in_memory_mean_latency_ms'],
                    $report['summary']['in_memory_p95_latency_ms'],
                ],
                [
                    'vector',
                    $report['summary']['vector_hit_at_1'],
                    $report['summary']['vector_mean_latency_ms'],
                    $report['summary']['vector_p95_latency_ms'],
                ],
            ],
        );

        $output = $this->option('output');
        if (is_string($output) && trim($output) !== '') {
            $path = str_starts_with($output, DIRECTORY_SEPARATOR) ? $output : base_path($output);
            $directory = dirname($path);
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException("Unable to create benchmark output directory [{$directory}].");
            }
            file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $this->components->info("Report written to {$path}");
        }

        return self::SUCCESS;
    }

    /** @return array<int, array{prompt: string, expected_document: string, expected_terms: array<int, string>, category?: string}> */
    private function dataset(): array
    {
        $path = $this->option('dataset');
        $path = is_string($path) && trim($path) !== '' ? $path : resource_path('ai/evals/retrieval-id.json');
        $path = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);

        if (! is_file($path)) {
            throw new RuntimeException("Retrieval dataset [{$path}] is unavailable.");
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Retrieval dataset must be a JSON array.');
        }

        $cases = [];
        foreach ($decoded as $case) {
            if (! is_array($case) || ! isset($case['prompt'], $case['expected_document'])) {
                continue;
            }

            $terms = $case['expected_terms'] ?? [];
            $cases[] = [
                'prompt' => (string) $case['prompt'],
                'expected_document' => (string) $case['expected_document'],
                'expected_terms' => is_array($terms)
                    ? array_values(array_map(static fn (mixed $term): string => (string) $term, $terms))
                    : [],
                ...((isset($case['category']) && is_string($case['category'])) ? ['category' => $case['category']] : []),
            ];
        }

        return $cases;
    }

    private function team(): ?Team
    {
        $slug = $this->option('team');

        return is_string($slug) && trim($slug) !== ''
            ? Team::query()->where('slug', trim($slug))->first()
            : Team::query()->orderBy('id')->first();
    }
}
