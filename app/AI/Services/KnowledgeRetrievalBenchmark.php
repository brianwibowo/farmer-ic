<?php

namespace App\AI\Services;

use App\AI\Contracts\KnowledgeRetriever;
use App\Models\Team;

class KnowledgeRetrievalBenchmark
{
    public function __construct(
        private readonly InMemoryKnowledgeRetriever $inMemory,
        private readonly VectorKnowledgeRetriever $vectorRetriever,
    ) {}

    /**
     * @param  array<int, array{prompt: string, expected_document: string, expected_terms: array<int, string>, category?: string}>  $cases
     * @return array{summary: array<string, mixed>, cases: array<int, array<string, mixed>>}
     */
    public function run(Team $team, array $cases): array
    {
        $rows = [];
        foreach ($cases as $case) {
            $inMemory = $this->measure($this->inMemory, $case['prompt'], $team);
            $vector = $this->measure($this->vectorRetriever, $case['prompt'], $team);

            $rows[] = [
                'prompt' => $case['prompt'],
                'category' => $case['category'] ?? null,
                'expected_document' => $case['expected_document'],
                'in_memory' => $this->evaluate($inMemory, $case),
                'vector' => $this->evaluate($vector, $case),
            ];
        }

        return [
            'summary' => [
                'cases' => count($rows),
                'in_memory_hit_at_1' => $this->accuracy($rows, 'in_memory'),
                'vector_hit_at_1' => $this->accuracy($rows, 'vector'),
                'in_memory_mean_latency_ms' => $this->meanLatency($rows, 'in_memory'),
                'vector_mean_latency_ms' => $this->meanLatency($rows, 'vector'),
                'in_memory_p95_latency_ms' => $this->percentileLatency($rows, 'in_memory', 0.95),
                'vector_p95_latency_ms' => $this->percentileLatency($rows, 'vector', 0.95),
                'vector_embedding_cache_hits' => $this->sumMetric($rows, 'vector', 'embedding_cache_hits'),
                'vector_embedding_cache_misses' => $this->sumMetric($rows, 'vector', 'embedding_cache_misses'),
            ],
            'cases' => $rows,
        ];
    }

    /** @return array{results: array<int, array<string, mixed>>, latency_ms: float} */
    private function measure(KnowledgeRetriever $retriever, string $prompt, Team $team): array
    {
        $start = hrtime(true);
        $results = $retriever->retrieve($prompt, $team, 4);

        return [
            'results' => $results,
            'latency_ms' => round((hrtime(true) - $start) / 1_000_000, 3),
        ];
    }

    /**
     * @param  array{results: array<int, array<string, mixed>>, latency_ms: float}  $measurement
     * @param  array{prompt: string, expected_document: string, expected_terms: array<int, string>, category?: string}  $case
     * @return array{hit_at_1: bool, rank: int|null, latency_ms: float, embedding_cache_hits: int, embedding_cache_misses: int, top_results: array<int, array<string, mixed>>}
     */
    private function evaluate(array $measurement, array $case): array
    {
        $expected = $case['expected_document'];
        $results = $measurement['results'];
        if ($expected === '') {
            return [
                'hit_at_1' => $results === [],
                'rank' => $results === [] ? 1 : null,
                'latency_ms' => $measurement['latency_ms'],
                'embedding_cache_hits' => (int) data_get($results, '0.metadata.embedding_cache_hits', 0),
                'embedding_cache_misses' => (int) data_get($results, '0.metadata.embedding_cache_misses', 0),
                'top_results' => array_map(static fn (array $result): array => [
                    'id' => $result['id'],
                    'document_id' => data_get($result, 'metadata.document_id'),
                    'score' => $result['score'],
                    'vector_score' => data_get($result, 'metadata.vector_score'),
                    'lexical_score' => data_get($result, 'metadata.lexical_score'),
                    'hybrid_score' => data_get($result, 'metadata.hybrid_score'),
                ], $results),
            ];
        }
        $ranks = [];
        foreach ($results as $rank => $result) {
            $documentId = (string) data_get($result, 'metadata.document_id', '');
            $content = mb_strtolower((string) ($result['content'] ?? ''), 'UTF-8');
            $hasTerms = true;
            foreach ($case['expected_terms'] as $term) {
                if (! str_contains($content, mb_strtolower($term, 'UTF-8'))) {
                    $hasTerms = false;
                    break;
                }
            }
            if ($documentId === $expected || $hasTerms) {
                $ranks[] = $rank + 1;
            }
        }

        return [
            'hit_at_1' => in_array(1, $ranks, true),
            'rank' => $ranks[0] ?? null,
            'latency_ms' => $measurement['latency_ms'],
            'embedding_cache_hits' => (int) data_get($results, '0.metadata.embedding_cache_hits', 0),
            'embedding_cache_misses' => (int) data_get($results, '0.metadata.embedding_cache_misses', 0),
            'top_results' => array_map(static fn (array $result): array => [
                'id' => $result['id'],
                'document_id' => data_get($result, 'metadata.document_id'),
                'score' => $result['score'],
                'vector_score' => data_get($result, 'metadata.vector_score'),
                'lexical_score' => data_get($result, 'metadata.lexical_score'),
                'hybrid_score' => data_get($result, 'metadata.hybrid_score'),
            ], $results),
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function accuracy(array $rows, string $key): float
    {
        if ($rows === []) {
            return 0.0;
        }

        $hits = 0;
        foreach ($rows as $row) {
            $hits += data_get($row, $key.'.hit_at_1') ? 1 : 0;
        }

        return round($hits / count($rows), 4);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function meanLatency(array $rows, string $key): float
    {
        if ($rows === []) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float) data_get($row, $key.'.latency_ms');
        }

        return round($total / count($rows), 3);
    }

    /**
     * Return the nearest-rank percentile for a retriever's measured latency.
     *
     * Nearest-rank keeps the reported value present in the sample, which makes
     * a small golden dataset predictable and avoids interpolating a latency
     * that was never observed. Empty samples are reported as zero.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function percentileLatency(array $rows, string $key, float $quantile): float
    {
        $latencies = [];
        foreach ($rows as $row) {
            $latency = data_get($row, $key.'.latency_ms');
            if (is_numeric($latency)) {
                $latencies[] = (float) $latency;
            }
        }

        if ($latencies === []) {
            return 0.0;
        }

        sort($latencies, SORT_NUMERIC);
        $index = (int) ceil(max(0.0, min(1.0, $quantile)) * count($latencies)) - 1;
        $index = max(0, min(count($latencies) - 1, $index));

        return round($latencies[$index], 3);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function sumMetric(array $rows, string $retriever, string $metric): int
    {
        $total = 0;
        foreach ($rows as $row) {
            $total += (int) data_get($row, $retriever.'.'.$metric, 0);
        }

        return $total;
    }
}
