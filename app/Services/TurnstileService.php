<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TurnstileService
{
    public function verify(mixed $token, string $action, ?string $ip): bool
    {
        $secret = config('services.turnstile.secret');
        $hostnames = array_filter(array_map(fn ($hostname) => strtolower(trim($hostname)), explode(',', (string) config('services.turnstile.hostnames'))));

        if (! is_string($token) || trim($token) === '' || strlen($token) > 2048
            || ! is_string($secret) || trim($secret) === '' || $hostnames === []
            || (app()->environment('production') && array_intersect($hostnames, ['localhost', '127.0.0.1', '::1']) !== [])) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ]);

            if (! $response->successful()) {
                Log::warning('Turnstile verification service returned an error.', ['status' => $response->status()]);

                return false;
            }

            return $response->json('success') === true
                && $response->json('action') === $action
                && in_array($response->json('hostname'), $hostnames, true);
        } catch (Throwable) {
            Log::warning('Turnstile verification could not be completed.');

            return false;
        }
    }
}
