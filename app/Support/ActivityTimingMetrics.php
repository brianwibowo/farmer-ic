<?php

namespace App\Support;

/** Mutable per-request timing and query metrics collected across Petugas middleware and listeners. */
final class ActivityTimingMetrics
{
    public int $queries = 0;

    public float $query_ms = 0.0;

    public ?float $listing_ms = null;

    public ?float $permissions_ms = null;

    public ?int $task_count = null;

    public ?int $page = null;

    public ?int $per_page = null;

    public ?float $stats_ms = null;

    public ?float $categories_ms = null;

    public ?int $report_count = null;

    public int|float|null $web_started = null;

    public ?int $web_queries_start = null;

    public ?float $web_query_ms_start = null;

    public ?float $web_setup_ms = null;

    public ?int $web_setup_queries = null;

    public ?float $web_setup_query_ms = null;

    public ?float $web_pipeline_ms = null;

    public ?float $inertia_ms = null;

    public ?float $route_ms = null;

    public ?float $controller_ms = null;

    public ?int $route_queries = null;

    public ?float $route_query_ms = null;

    public ?float $shared_props_ms = null;

    public ?int $shared_props_queries = null;

    public ?float $shared_props_query_ms = null;

    public int|float|null $ssr_started = null;

    public ?float $ssr_ms = null;

    public ?float $ssr_vite_ms = null;

    public ?string $ssr_result = null;
}
