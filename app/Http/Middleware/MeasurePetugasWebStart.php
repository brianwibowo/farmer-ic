<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MeasurePetugasWebStart
{
    public function handle(Request $request, Closure $next): Response
    {
        $metrics = $request->attributes->get(MeasurePetugasActivity::ATTRIBUTE);
        if ($metrics !== null) {
            $metrics->web_started = hrtime(true);
            $metrics->web_queries_start = $metrics->queries;
            $metrics->web_query_ms_start = $metrics->query_ms;
        }

        try {
            return $next($request);
        } finally {
            if ($metrics !== null) {
                $metrics->web_pipeline_ms = round((hrtime(true) - $metrics->web_started) / 1_000_000, 2);
            }
        }
    }
}
