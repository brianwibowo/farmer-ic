<?php

namespace App\AI\Data;

final readonly class AiRouteDecision
{
    /**
     * @param  array<int, array{intent: string, score: float}>  $candidates
     */
    public function __construct(
        public string $intent,
        public string $domain,
        public string $period,
        public ?string $topic,
        public float $confidence,
        public float $ambiguityMargin,
        public bool $needsClarification,
        public string $reason,
        public array $candidates = [],
        public string $version = 'router-v1',
    ) {}

    public function usesKnowledge(): bool
    {
        return $this->intent === 'knowledge';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'domain' => $this->domain,
            'period' => $this->period,
            'topic' => $this->topic,
            'confidence' => $this->confidence,
            'ambiguity_margin' => $this->ambiguityMargin,
            'needs_clarification' => $this->needsClarification,
            'reason' => $this->reason,
            'candidates' => $this->candidates,
            'version' => $this->version,
        ];
    }
}
