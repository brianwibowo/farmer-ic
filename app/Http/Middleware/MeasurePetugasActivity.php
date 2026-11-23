<?php

namespace App\Http\Middleware;

use App\Support\ActivityTimingMetrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MeasurePetugasActivity
{
    public const ATTRIBUTE = '_petugas_activity_timing';

    public static function metrics(Request $request): ?ActivityTimingMetrics
    {
        $metrics = $request->attributes->get(self::ATTRIBUTE);

        return $metrics instanceof ActivityTimingMetrics ? $metrics : null;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('activity_timing.enabled') || ! $request->isMethod('GET') || ! $request->is('petugas/aktivitas', 'petugas/laporan')) {
            return $next($request);
        }

        $report = $request->is('petugas/laporan');
        $metrics = new ActivityTimingMetrics;
        $request->attributes->set(self::ATTRIBUTE, $metrics);
        $traceId = (string) Str::uuid();
        $started = hrtime(true);
        $response = null;

        try {
            $response = $next($request);
            $response->headers->set($report ? 'X-Report-Trace-Id' : 'X-Activity-Trace-Id', $traceId);

            return $response;
        } finally {
            $request->attributes->remove(self::ATTRIBUTE);
            Log::info($report ? 'petugas_report_timing' : 'petugas_activity_timing', [
                'trace_id' => $traceId,
                'status' => $response?->getStatusCode(),
                'laravel_ms' => round((hrtime(true) - $started) / 1_000_000, 2),
                'listing_ms' => $metrics->listing_ms,
                'web_setup_ms' => $metrics->web_setup_ms,
                'web_setup_queries' => $metrics->web_setup_queries,
                'web_setup_query_ms' => $metrics->web_setup_query_ms,
                'web_pipeline_ms' => $metrics->web_pipeline_ms,
                'inertia_ms' => $metrics->inertia_ms,
                'route_ms' => $metrics->route_ms,
                'controller_ms' => $metrics->controller_ms,
                'inertia_visit' => $request->header('X-Inertia') === 'true',
                'ssr_ms' => $metrics->ssr_ms,
                'ssr_vite_ms' => $metrics->ssr_vite_ms,
                'ssr_result' => $metrics->ssr_result,
                'route_queries' => $metrics->route_queries,
                'route_query_ms' => $metrics->route_query_ms,
                'shared_props_ms' => $metrics->shared_props_ms,
                'shared_props_queries' => $metrics->shared_props_queries,
                'shared_props_query_ms' => $metrics->shared_props_query_ms,
                ...($report ? ['stats_ms' => $metrics->stats_ms, 'categories_ms' => $metrics->categories_ms, 'report_count' => $metrics->report_count] : ['permissions_ms' => $metrics->permissions_ms, 'task_count' => $metrics->task_count]),
                'page' => $metrics->page,
                'per_page' => $metrics->per_page,
                'query_count' => $metrics->queries,
                'query_ms' => round($metrics->query_ms, 2),
            ]);
        }
    }
}
