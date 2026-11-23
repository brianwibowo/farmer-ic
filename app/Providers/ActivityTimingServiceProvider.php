<?php

namespace App\Providers;

use App\Http\Middleware\MeasurePetugasActivity;
use App\Support\ActivityTimingMetrics;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class ActivityTimingServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (! config('activity_timing.enabled')) {
            return;
        }

        DB::listen(function (QueryExecuted $query): void {
            if (! $this->app->bound('request')) {
                return;
            }

            $metrics = MeasurePetugasActivity::metrics($this->app->make('request'));
            if ($metrics !== null) {
                $metrics->queries++;
                $metrics->query_ms += $query->time ?? 0;
            }
        });
        $currentMetrics = function (HttpClientRequest $httpRequest): ?ActivityTimingMetrics {
            if (! $this->app->bound('request')) {
                return null;
            }
            $metrics = MeasurePetugasActivity::metrics($this->app->make('request'));
            if ($metrics === null) {
                return null;
            }
            $hot = Vite::isRunningHot();
            $base = $hot ? trim((string) @file_get_contents(Vite::hotFile())) : rtrim((string) config('inertia.ssr.url', 'http://127.0.0.1:13714'), '/');
            $expected = rtrim($base, '/').($hot ? '/__inertia_ssr' : '/render');

            return $httpRequest->url() === $expected ? $metrics : null;
        };
        Event::listen(RequestSending::class, function (RequestSending $event) use ($currentMetrics): void {
            if ($metrics = $currentMetrics($event->request)) {
                $metrics->ssr_started = hrtime(true);
            }
        });
        Event::listen(ResponseReceived::class, function (ResponseReceived $event) use ($currentMetrics): void {
            if (($metrics = $currentMetrics($event->request)) && $metrics->ssr_started !== null) {
                $metrics->ssr_ms = round((hrtime(true) - $metrics->ssr_started) / 1_000_000, 2);
                $metrics->ssr_result = $event->response->successful() ? 'success' : 'error';
                if (Vite::isRunningHot() && preg_match('/(?:^|,)\s*farmer-ssr;dur=([0-9]+(?:\.[0-9]+)?)(?:\s*(?:,|$))/', (string) $event->response->header('Server-Timing'), $match)) {
                    $metrics->ssr_vite_ms = (float) $match[1];
                }
            }
        });
        Event::listen(ConnectionFailed::class, function (ConnectionFailed $event) use ($currentMetrics): void {
            if (($metrics = $currentMetrics($event->request)) && $metrics->ssr_started !== null) {
                $metrics->ssr_ms = round((hrtime(true) - $metrics->ssr_started) / 1_000_000, 2);
                $metrics->ssr_result = 'connection_failed';
            }
        });
    }
}
