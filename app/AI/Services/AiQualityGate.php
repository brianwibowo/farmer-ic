<?php

namespace App\AI\Services;

use App\AI\Contracts\KnowledgeRetriever;
use App\Models\Team;

/**
 * Offline quality gate for the assistant's routing and retrieval contract.
 *
 * The gate deliberately does not call an LLM. It makes the parts we can
 * deterministically verify (route, evidence rank, citation syntax and
 * latency statistics) repeatable in CI, while the live provider benchmark can
 * be run separately with the same golden dataset.
 */
class AiQualityGate
{
    public function __construct(private readonly AiIntentRouter $router) {}

    /**
     * @param  array<int, array<string, mixed>>  $cases
     * @return array{accuracy: float, checked: int, failures: array<int, array<string, mixed>>}
     */
    public function evaluateRouting(array $cases): array
    {
        $failures = [];
        $checked = 0;

        foreach ($cases as $case) {
            $expected = is_array($case['expected_route'] ?? null) ? $case['expected_route'] : [];
            if ($expected === []) {
                continue;
            }

            $checked++;
            $decision = $this->router->decide((string) ($case['prompt'] ?? ''));
            $mismatches = [];
            foreach (['intent', 'domain', 'period', 'reason'] as $field) {
                if (array_key_exists($field, $expected) && $decision->{$field} !== $expected[$field]) {
                    $mismatches[$field] = [
                        'expected' => $expected[$field],
                        'actual' => $decision->{$field},
                    ];
                }
            }

            if ($mismatches !== []) {
                $failures[] = [
                    'id' => (string) ($case['id'] ?? $case['prompt'] ?? 'case'),
                    'prompt' => (string) ($case['prompt'] ?? ''),
                    'mismatches' => $mismatches,
                ];
            }
        }

        return [
            'accuracy' => $this->rate($checked - count($failures), $checked),
            'checked' => $checked,
            'failures' => $failures,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cases
     * @return array{accuracy: float, hit_at_1: float, checked: int, p95_latency_ms: float, mean_latency_ms: float, rows: array<int, array<string, mixed>>}
     */
    public function evaluateRetrieval(Team $team, KnowledgeRetriever $retriever, array $cases): array
    {
        $rows = [];

        foreach ($cases as $case) {
            $prompt = (string) ($case['prompt'] ?? '');
            if (($case['retrieval_required'] ?? true) === false) {
                $rows[] = [
                    'id' => (string) ($case['id'] ?? $prompt),
                    'prompt' => $prompt,
                    'expected_document' => (string) ($case['expected_document'] ?? ''),
                    'rank' => null,
                    'hit_at_1' => true,
                    'latency_ms' => 0.0,
                    'retrieval' => 'skipped_deterministic_route',
                    'evidence_count' => 0,
                    'top_result' => null,
                    'skipped' => true,
                ];

                continue;
            }
            $started = hrtime(true);
            $results = $retriever->retrieve($prompt, $team, 4);
            $latency = round((hrtime(true) - $started) / 1_000_000, 3);
            $expectedDocument = (string) ($case['expected_document'] ?? '');
            $expectedTerms = is_array($case['expected_terms'] ?? null) ? $case['expected_terms'] : [];

            $rank = null;
            foreach ($results as $index => $result) {
                $documentId = (string) data_get($result, 'metadata.document_id', '');
                $content = mb_strtolower($result['content'], 'UTF-8');
                $hasTerms = true;
                foreach ($expectedTerms as $term) {
                    if (! str_contains($content, mb_strtolower((string) $term, 'UTF-8'))) {
                        $hasTerms = false;
                        break;
                    }
                }
                if (($expectedDocument !== '' && $documentId === $expectedDocument) || $hasTerms) {
                    $rank = $index + 1;
                    break;
                }
            }

            $expectedEmpty = $expectedDocument === '';
            $hit = $expectedEmpty ? $results === [] : $rank === 1;
            $rows[] = [
                'id' => (string) ($case['id'] ?? $prompt),
                'prompt' => $prompt,
                'expected_document' => $expectedDocument,
                'rank' => $rank,
                'hit_at_1' => $hit,
                'latency_ms' => $latency,
                'retrieval' => data_get($results, '0.metadata.retrieval', 'unknown'),
                'evidence_count' => count($results),
                'top_result' => data_get($results, '0.metadata.document_id'),
            ];
        }

        $latencies = array_map(static fn (array $row): float => (float) $row['latency_ms'], $rows);
        $hits = count(array_filter($rows, static fn (array $row): bool => $row['hit_at_1'] === true));

        return [
            'accuracy' => $this->rate($hits, count($rows)),
            'hit_at_1' => $this->rate($hits, count($rows)),
            'checked' => count($rows),
            'p95_latency_ms' => $this->percentile($latencies, 0.95),
            'mean_latency_ms' => $this->mean($latencies),
            'rows' => $rows,
        ];
    }

    /**
     * Validate the citation contract independently of provider wording.
     *
     * A grounded answer must cite at least one supplied evidence item and
     * expose a source footer. References outside the supplied evidence are
     * rejected so a model cannot silently invent [S9].
     *
     * @return array{valid: bool, references: array<int, int>, errors: array<int, string>}
     */
    public function validateCitations(string $answer, int $evidenceCount, bool $required = true): array
    {
        preg_match_all('/\[S(\d+)\]/i', $answer, $matches);
        $references = array_values(array_unique(array_map(static fn (string $value): int => (int) $value, $matches[1])));
        $errors = [];

        if ($required && $evidenceCount === 0) {
            $errors[] = 'citation_required_but_evidence_empty';
        }
        if ($required && $references === []) {
            $errors[] = 'missing_citation';
        }
        foreach ($references as $reference) {
            if ($reference < 1 || $reference > $evidenceCount) {
                $errors[] = "citation_out_of_range:S{$reference}";
            }
        }
        if ($required && $evidenceCount > 0 && ! preg_match('/\bSumber\s*:/iu', $answer)) {
            $errors[] = 'missing_source_footer';
        }

        return [
            'valid' => $errors === [],
            'references' => $references,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * Check that a provider fallback chain is usable before making a live call.
     * The first candidate remains the primary; duplicate/empty candidates are
     * removed by ModelRouter, so this gate only verifies the resulting chain.
     *
     * @param  array<int, string>  $models
     * @return array{valid: bool, candidates: array<int, string>, errors: array<int, string>}
     */
    public function validateFallbackChain(array $models): array
    {
        $candidates = array_values(array_filter(array_map('trim', $models), static fn (string $model): bool => $model !== ''));
        $errors = [];
        if ($candidates === []) {
            $errors[] = 'no_provider_candidate';
        }
        if (count($candidates) > 1 && count($candidates) !== count(array_unique($candidates))) {
            $errors[] = 'duplicate_provider_candidate';
        }

        return [
            'valid' => $errors === [],
            'candidates' => $candidates,
            'errors' => $errors,
        ];
    }

    /** @param array<int, float|int> $values */
    public function percentile(array $values, float $quantile): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values, SORT_NUMERIC);
        $index = (int) max(0, min(count($values) - 1, (int) ceil(count($values) * $quantile) - 1));

        return round((float) $values[$index], 3);
    }

    /** @param array<int, float|int> $values */
    private function mean(array $values): float
    {
        return $values === [] ? 0.0 : round(array_sum($values) / count($values), 3);
    }

    private function rate(int $hits, int $total): float
    {
        return $total === 0 ? 0.0 : round($hits / $total, 4);
    }
}
