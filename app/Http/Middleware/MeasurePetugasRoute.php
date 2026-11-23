<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MeasurePetugasRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $metrics = MeasurePetugasActivity::metrics($request);
        if ($metrics === null) {
            return $next($request);
        }
        $started = hrtime(true);
        $queries = $metrics->queries;
        $queryMs = $metrics->query_ms;
        try {
            return $next($request);
        } finally {
            $metrics->route_ms = round((hrtime(true) - $started) / 1_000_000, 2);
            $metrics->route_queries = $metrics->queries - $queries;
            $metrics->route_query_ms = round($metrics->query_ms - $queryMs, 2);
        }
    }
}
