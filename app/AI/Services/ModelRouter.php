<?php

namespace App\AI\Services;

class ModelRouter
{
    /** @return array<int, string> */
    public function candidates(bool $hasVision, bool $preferFast = false): array
    {
        $configured = $hasVision
            ? config('ai.models.vision_fallbacks', [])
            : config('ai.models.text_fallbacks', []);

        $models = [];
        if (! $hasVision && $preferFast) {
            $fast = trim((string) config('ai.models.fast_text_model', ''));
            if ($fast !== '') {
                $models[] = $fast;
            }
        }

        $models = array_merge($models, [
            (string) config('ai.models.primary'),
        ], is_array($configured) ? $configured : []);

        return array_values(array_unique(array_filter($models, fn ($model): bool => is_string($model) && trim($model) !== '')));
    }

    /** @return array<int, string> */
    public function infographicCandidates(): array
    {
        $configured = config('ai.models.infographic_fallbacks', []);

        $models = array_merge([
            (string) config('ai.models.infographic_model', 'google/gemini-3.1-flash-image'),
        ], is_array($configured) ? $configured : []);

        return array_values(array_unique(array_filter($models, fn ($model): bool => is_string($model) && trim($model) !== '')));
    }
}
