<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MeasurePetugasWebReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $metrics = $request->attributes->get(MeasurePetugasActivity::ATTRIBUTE);
        if ($metrics !== null && $metrics->web_started !== null) {
            $metrics->web_setup_ms = round((hrtime(true) - $metrics->web_started) / 1_000_000, 2);
            $metrics->web_setup_queries = $metrics->queries - $metrics->web_queries_start;
            $metrics->web_setup_query_ms = round($metrics->query_ms - $metrics->web_query_ms_start, 2);
        }

        return $next($request);
    }
}
