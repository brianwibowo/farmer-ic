<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MeasurePetugasWebStart
{
    public function handle(Request $request, Closure $next): Response
    {
        $metrics = MeasurePetugasActivity::metrics($request);
        if ($metrics === null) {
            return $next($request);
        }
        $started = hrtime(true);
        $metrics->web_started = $started;
        $metrics->web_queries_start = $metrics->queries;
        $metrics->web_query_ms_start = $metrics->query_ms;

        try {
            return $next($request);
        } finally {
            $metrics->web_pipeline_ms = round((hrtime(true) - $started) / 1_000_000, 2);
        }
    }
}
