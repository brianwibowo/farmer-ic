<?php

namespace Tests\Unit\AI;

use App\AI\Services\ModelRouter;
use Tests\TestCase;

class ModelRouterTest extends TestCase
{
    public function test_fast_text_route_is_opt_in_and_keeps_enterprise_fallback(): void
    {
        config([
            'ai.models.primary' => 'enterprise-model',
            'ai.models.fast_text_model' => 'fast-model',
            'ai.models.text_fallbacks' => ['fallback-model'],
        ]);

        $router = new ModelRouter;

        $this->assertSame(
            ['enterprise-model', 'fallback-model'],
            $router->candidates(hasVision: false),
        );
        $this->assertSame(
            ['fast-model', 'enterprise-model', 'fallback-model'],
            $router->candidates(hasVision: false, preferFast: true),
        );
    }

    public function test_fast_text_route_does_not_leak_into_vision_candidates(): void
    {
        config([
            'ai.models.primary' => 'vision-model',
            'ai.models.fast_text_model' => 'fast-model',
            'ai.models.vision_fallbacks' => ['vision-fallback'],
        ]);

        $this->assertSame(
            ['vision-model', 'vision-fallback'],
            (new ModelRouter)->candidates(hasVision: true, preferFast: true),
        );
    }
}
