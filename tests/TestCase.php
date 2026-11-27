<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Tests\Support\TestDatabase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        TestDatabase::prepare($app);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        // Most feature tests exercise domain behavior, not the external challenge.
        // TurnstileTest explicitly re-enables this middleware and fakes Siteverify.
        $this->withoutMiddleware(\App\Http\Middleware\VerifyTurnstile::class);
        foreach (['activity', 'legality', 'farm'] as $disk) {
            \Illuminate\Support\Facades\Storage::fake($disk);
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
