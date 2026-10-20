<?php

namespace App\AI\Services;

use App\AI\Data\GroundedResponseValidation;

class GroundedResponseValidator
{
    /**
     * Validate citation markers in a provider response before the source
     * footer is appended. A footer citation alone must not make an otherwise
     * ungrounded answer look valid.
     *
     * @param  array<int, array<string, mixed>>  $evidence
     */
    public function validate(string $content, array $evidence): GroundedResponseValidation
    {
        $issues = [];
        $citations = [];

        if (trim($content) === '') {
            $issues[] = 'response_empty';
        }

        if ($evidence !== []) {
            preg_match_all('/(?<![A-Za-z0-9])\[S(\d+)\]/i', $content, $matches);
            $citations = array_values(array_unique(array_map(
                static fn (string $value): int => (int) $value,
                $matches[1],
            )));

            if ($citations === []) {
                $issues[] = 'citation_missing';
            }

            $maxCitation = count($evidence);
            $hasInvalidCitation = collect($citations)->contains(
                static fn (int $citation): bool => $citation < 1 || $citation > $maxCitation,
            );

            if ($hasInvalidCitation) {
                $issues[] = 'citation_invalid';
            }
        }

        return new GroundedResponseValidation(
            ok: $issues === [],
            citations: $citations,
            issues: array_values(array_unique($issues)),
        );
    }

    /**
     * Replace an invalid grounded answer with a short, honest abstention when
     * strict citation validation is enabled. The default remains opt-in so
     * existing deployments can observe validation before changing behavior.
     *
     * @param  array<int, array<string, mixed>>  $evidence
     */
    public function guard(string $content, array $evidence): string
    {
        $validation = $this->validate($content, $evidence);

        if ($validation->ok || ! (bool) config('ai.rag.require_citations', false)) {
            return trim($content);
        }

        return 'Saya belum bisa memastikan jawaban dari sumber yang tersedia. '
            .'Silakan beri konteks tambahan atau pilih panduan yang lebih spesifik.';
    }
}
