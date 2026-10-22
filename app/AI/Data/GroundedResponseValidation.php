<?php

namespace App\AI\Data;

/**
 * Server-side checks for an answer that was generated from retrieved evidence.
 *
 * This is intentionally small and deterministic. It does not try to decide
 * whether a claim is true; it only verifies that the model references sources
 * that were actually supplied to it.
 */
final readonly class GroundedResponseValidation
{
    /**
     * @param  array<int, int>  $citations
     * @param  array<int, string>  $issues
     * @param  array<string, mixed>  $numeric
     */
    public function __construct(
        public bool $ok,
        public array $citations = [],
        public array $issues = [],
        public array $numeric = [],
    ) {}

    /** @return array{ok: bool, citations: array<int, int>, issues: array<int, string>, numeric: array<string, mixed>} */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'citations' => $this->citations,
            'issues' => $this->issues,
            'numeric' => $this->numeric,
        ];
    }
}
