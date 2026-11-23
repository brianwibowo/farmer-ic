<?php

namespace Tests\Feature\Activity;

use App\Http\Middleware\MeasurePetugasActivity;
use App\Providers\ActivityTimingServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class ActivityTimingProviderTest extends TestCase
{
    public function test_disabled_configuration_does_not_register_a_query_listener(): void
    {
        config(['activity_timing.enabled' => false]);
        DB::shouldReceive('listen')->never();

        (new ActivityTimingServiceProvider($this->app))->boot();
    }

    public function test_enabled_configuration_registers_the_query_listener(): void
    {
        config(['activity_timing.enabled' => true]);
        DB::shouldReceive('listen')->once()->with(\Mockery::type('callable'));

        (new ActivityTimingServiceProvider($this->app))->boot();
    }

    public function test_ssr_http_timing_is_recorded_only_for_the_configured_gateway(): void
    {
        config(['inertia.ssr.url' => 'http://127.0.0.1:13714']);
        Vite::shouldReceive('isRunningHot')->andReturn(false);
        $request = Request::create('/petugas/aktivitas');
        $metrics = (object) ['ssr_started' => null, 'ssr_ms' => null, 'ssr_result' => null];
        $request->attributes->set(MeasurePetugasActivity::ATTRIBUTE, $metrics);
        $this->app->instance('request', $request);
        Http::fake(['*' => Http::response([], 200)]);

        Http::post('http://127.0.0.1:13714/other');
        $this->assertNull($metrics->ssr_ms);
        Http::post('http://127.0.0.1:13714/render');
        $this->assertSame('success', $metrics->ssr_result);
        $this->assertGreaterThanOrEqual(0, $metrics->ssr_ms);
    }

    public function test_vite_server_timing_is_recorded_for_the_hot_gateway(): void
    {
        $hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
        file_put_contents($hotFile, 'http://127.0.0.1:5173');
        try {
            Vite::shouldReceive('isRunningHot')->andReturn(true);
            Vite::shouldReceive('hotFile')->andReturn($hotFile);
            $request = Request::create('/petugas/aktivitas');
            $metrics = (object) ['ssr_started' => null, 'ssr_ms' => null, 'ssr_result' => null];
            $request->attributes->set(MeasurePetugasActivity::ATTRIBUTE, $metrics);
            $this->app->instance('request', $request);
            Http::fake(['*' => Http::response([], 200, ['Server-Timing' => 'other;dur=4, farmer-ssr;dur=27.35'])]);

            Http::post('http://127.0.0.1:5173/__inertia_ssr');
            $this->assertSame(27.35, $metrics->ssr_vite_ms);
            $this->assertSame('success', $metrics->ssr_result);
        } finally {
            unlink($hotFile);
        }
    }
}
