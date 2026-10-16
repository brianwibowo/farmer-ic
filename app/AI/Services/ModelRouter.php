<?php

namespace App\AI\Services;

class ModelRouter
{
    /** @return array<int, string> */
    public function candidates(bool $hasVision): array
    {
        $configured = $hasVision
            ? config('ai.models.vision_fallbacks', [])
            : config('ai.models.text_fallbacks', []);

        $models = array_merge([
            (string) config('ai.models.primary'),
        ], is_array($configured) ? $configured : []);

        return array_values(array_unique(array_filter($models, fn ($model): bool => is_string($model) && trim($model) !== '')));
    }
}
