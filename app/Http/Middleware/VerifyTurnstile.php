<?php

namespace App\Http\Middleware;

use App\Services\TurnstileService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class VerifyTurnstile
{
    public function __construct(private TurnstileService $turnstile) {}

    public function handle(Request $request, Closure $next): Response
    {
        $action = match (true) {
            $request->isMethod('POST') && $request->routeIs('login.store') => 'login',
            $request->isMethod('POST') && $request->routeIs('password.email') => 'forgot_password',
            default => null,
        };

        if ($action === null) {
            return $next($request);
        }

        // Limit upstream verification work across both forms, including invalid tokens.
        $key = 'turnstile:'.hash('sha256', (string) $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            return response('Too many attempts.', 429, ['Retry-After' => RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 60);

        if (! $this->turnstile->verify($request->input('cf-turnstile-response'), $action, $request->ip())) {
            $request->attributes->set('turnstile_failed', true);
            $request->attributes->set('turnstile_retry_url', route($action === 'login' ? 'login' : 'password.request'));
            abort(403);
        }

        return $next($request);
    }
}
