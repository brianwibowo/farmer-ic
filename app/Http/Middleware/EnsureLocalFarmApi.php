<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalFarmApi
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/kebun', 'api/kebun/*')) {
            abort_unless(app()->environment('local', 'testing'), 404);
            abort_unless(config('database.connections.'.config('database.default').'.driver') === 'pgsql', 503, 'API kebun memerlukan skema PostgreSQL Farmer IC.');
        }

        return $next($request);
    }
}
