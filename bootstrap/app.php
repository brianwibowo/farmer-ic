<?php

use App\Http\Middleware\EnsureLocalFarmApi;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTeamUrlDefaults;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo(fn (Request $request) => route(
            Gate::forUser($request->user())->allows('admin.access') ? 'admin.dashboard'
                : (Gate::forUser($request->user())->allows('petugas.access') ? 'petugas.home' : 'home'),
        ));
        $middleware->trustProxies(at: '*');
        $middleware->api(prepend: [EnsureLocalFarmApi::class]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetTeamUrlDefaults::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($response->getStatusCode() !== 403 || $request->is('api/*') || $request->expectsJson()) {
                return $response;
            }

            return Inertia::render('errors/Forbidden', [
                'status' => 403,
                'canLogout' => $request->user() !== null,
                // The error screen does not need to resolve team navigation after an access denial.
                'currentTeam' => null,
                'teams' => [],
                'navigation' => ['admin' => false, 'farms' => false],
            ])->toResponse($request)->setStatusCode(403);
        });
    })->create();
