<?php

namespace App\Services\Notifications;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class FonnteClient
{
    public const VALIDATION_TIMEOUT = 10;

    public const SEND_TIMEOUT = 15;

    public function isRegistered(#[\SensitiveParameter] string $target): bool
    {
        $response = $this->request('validate', $target, [], self::VALIDATION_TIMEOUT);
        $body = $response->json();
        if ($response->successful() && is_array($body) && ($body['status'] ?? null) === true) {
            $registered = $body['registered'] ?? null;
            $notRegistered = $body['not_registered'] ?? null;
            if (is_array($registered) && array_is_list($registered)
                && is_array($notRegistered) && array_is_list($notRegistered)
                && count($registered) + count($notRegistered) === 1) {
                $number = $registered[0] ?? $notRegistered[0] ?? null;
                if ((is_string($number) || is_int($number)) && (string) $number === $target) {
                    return $registered !== [];
                }
            }
            throw new FonnteException('invalid_validation_response', 'transient');
        }

        $this->reject($response, 'unconfirmed_validation_response', 'transient');
    }

    public function send(#[\SensitiveParameter] string $target, #[\SensitiveParameter] string $message): string
    {
        $response = $this->request('send', $target, ['message' => $message], self::SEND_TIMEOUT);
        $body = $response->json();
        if (is_array($body) && ($body['status'] ?? null) === true) {
            $ids = $body['id'] ?? null;
            if ($response->successful() && is_array($ids) && array_is_list($ids) && count($ids) === 1
                && (is_string($ids[0]) || is_int($ids[0])) && preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', (string) $ids[0])) {
                return (string) $ids[0];
            }
            throw new FonnteException('invalid_acceptance_response', 'uncertain');
        }

        // A proxy 5xx/malformed JSON/empty 2xx does not prove non-acceptance.
        $this->reject($response, 'unconfirmed_provider_response', 'uncertain');
    }

    /** @param array<string, string> $extra */
    private function request(string $endpoint, #[\SensitiveParameter] string $target, #[\SensitiveParameter] array $extra, int $timeout): Response
    {
        $token = config('operational_notifications.fonnte.token');
        if (! is_string($token) || trim($token) === '') {
            throw new FonnteException('missing_token', 'permanent');
        }
        if (! preg_match('/^[1-9][0-9]{7,14}$/D', $target)) {
            throw new FonnteException('invalid_target', 'permanent');
        }
        try {
            // Exactly one request per operation; retries are managed by the queue job.
            return Http::asForm()->acceptJson()->withHeaders(['Authorization' => $token])
                ->connectTimeout(5)->timeout($timeout)->withoutRedirecting()
                ->post('https://api.fonnte.com/'.$endpoint, [
                    'target' => $target, 'countryCode' => '0', ...$extra,
                ]);
        } catch (ConnectionException $exception) {
            if (preg_match('/cURL error (?:60|77):/i', $exception->getMessage()) === 1) {
                throw new FonnteException('tls_certificate_configuration', 'permanent');
            }
            $preConnect = preg_match('/cURL error (?:6|7):/i', $exception->getMessage()) === 1;
            if ($endpoint === 'validate') {
                // Retrying validation is safe because it never sends a message.
                throw new FonnteException('validation_connection_unavailable', 'transient');
            }
            throw new FonnteException($preConnect ? 'connection_unavailable' : 'transport_outcome_unknown', $preConnect ? 'transient' : 'uncertain');
        }
    }

    private function reject(#[\SensitiveParameter] Response $response, string $fallback, string $classification): never
    {
        $body = $response->json();
        if (is_array($body) && (($body['status'] ?? $body['Status'] ?? null) === false)) {
            $reason = is_string($body['reason'] ?? null) ? strtolower($body['reason']) : '';
            $temporary = str_contains($reason, 'disconnect') || str_contains($reason, 'not connect')
                || str_contains($reason, 'rate limit') || str_contains($reason, 'server') || str_contains($reason, 'temporar');
            throw new FonnteException($temporary ? 'provider_temporarily_rejected' : 'provider_rejected', $temporary ? 'transient' : 'permanent');
        }
        if (in_array($response->status(), [401, 403, 404, 422], true)) {
            throw new FonnteException('http_'.$response->status(), 'permanent');
        }
        if ($response->status() === 429) {
            throw new FonnteException('http_429', 'transient');
        }
        throw new FonnteException($fallback, $classification);
    }
}
