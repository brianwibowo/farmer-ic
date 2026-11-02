<?php

namespace Tests\Feature\Notifications;

use App\Services\Notifications\FonnteClient;
use App\Services\Notifications\FonnteException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FonnteClientTest extends TestCase
{
    public static function failures(): array
    {
        return [
            '200 false' => [['status' => false, 'reason' => 'token invalid'], 200, 'permanent'],
            'target invalid' => [['status' => false, 'reason' => 'target invalid'], 200, 'permanent'],
            'quota' => [['status' => false, 'reason' => 'insufficient quota'], 200, 'permanent'],
            'disconnected' => [['status' => false, 'reason' => 'device disconnected'], 200, 'transient'],
            'explicit temporary rejection' => [['status' => false, 'reason' => 'temporary server error'], 503, 'transient'],
            '429' => [[], 429, 'transient'],
            '401' => [[], 401, 'permanent'],
            'ambiguous 503' => [[], 503, 'uncertain'],
            'malformed json' => ['not json', 200, 'uncertain'],
            'missing id' => [['status' => true], 200, 'uncertain'],
            'string status' => [['status' => 'true', 'id' => ['1']], 200, 'uncertain'],
            'multiple ids' => [['status' => true, 'id' => ['1', '2']], 200, 'uncertain'],
            'non-list ids' => [['status' => true, 'id' => ['message' => '1']], 200, 'uncertain'],
        ];
    }

    #[DataProvider('failures')]
    public function test_invalid_responses_are_classified_and_exceptions_are_sanitized(array|string $body, int $status, string $classification): void
    {
        config(['operational_notifications.fonnte.token' => 'fake-secret']);
        Http::preventStrayRequests();
        Http::fake(['api.fonnte.com/*' => Http::response($body, $status)]);
        try {
            (new FonnteClient)->send('6281234567890', 'Private field report');
            $this->fail('Invalid response must not be accepted.');
        } catch (FonnteException $exception) {
            $this->assertSame($classification, $exception->classification);
            foreach (['fake-secret', '6281234567890', 'Private field report'] as $private) {
                $this->assertStringNotContainsString($private, (string) $exception);
                $this->assertStringNotContainsString($private, json_encode($exception->getTrace(), JSON_PARTIAL_OUTPUT_ON_ERROR));
            }
        }
    }

    public static function connectionFailures(): array
    {
        return [
            ['cURL error 28: Operation timed out', 'uncertain'],
            ['cURL error 7: Connection refused', 'transient'],
            ['cURL error 6: Could not resolve host', 'transient'],
            ['cURL error 60: SSL certificate problem', 'permanent'],
            ['cURL error 77: Error setting certificate file', 'permanent'],
        ];
    }

    #[DataProvider('connectionFailures')]
    public function test_connection_outcomes_are_not_blindly_retried(string $message, string $classification): void
    {
        config(['operational_notifications.fonnte.token' => 'fake']);
        Http::fake(fn () => throw new ConnectionException($message));
        try {
            (new FonnteClient)->send('6281234567890', 'Report');
            $this->fail('Connection failure must not succeed.');
        } catch (FonnteException $exception) {
            $this->assertSame($classification, $exception->classification);
        }
    }

    public static function registrations(): array
    {
        return [
            'registered' => [['status' => true, 'registered' => ['6281234567890'], 'not_registered' => []], true],
            'not registered' => [['status' => true, 'registered' => [], 'not_registered' => ['6281234567890']], false],
        ];
    }

    #[DataProvider('registrations')]
    public function test_registration_is_checked_for_the_exact_number(array $response, bool $expected): void
    {
        config(['operational_notifications.fonnte.token' => 'fake-secret']);
        Http::preventStrayRequests();
        Http::fake(['api.fonnte.com/validate' => Http::response($response)]);
        $this->assertSame($expected, (new FonnteClient)->isRegistered('6281234567890'));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.fonnte.com/validate'
            && $request['target'] === '6281234567890' && $request['countryCode'] === '0');
        Http::assertSentCount(1);
    }

    public static function invalidRegistrations(): array
    {
        return [
            'wrong number' => [['status' => true, 'registered' => ['6280000000000'], 'not_registered' => []]],
            'missing arrays' => [['status' => true]],
            'empty results' => [['status' => true, 'registered' => [], 'not_registered' => []]],
            'contradictory' => [['status' => true, 'registered' => ['6281234567890'], 'not_registered' => ['6281234567890']]],
            'non-list' => [['status' => true, 'registered' => ['number' => '6281234567890'], 'not_registered' => []]],
        ];
    }

    #[DataProvider('invalidRegistrations')]
    public function test_ambiguous_registration_never_counts_as_a_registered_number(array $response): void
    {
        config(['operational_notifications.fonnte.token' => 'fake-secret']);
        Http::preventStrayRequests();
        Http::fake(['api.fonnte.com/validate' => Http::response($response)]);
        $this->expectException(FonnteException::class);
        $this->expectExceptionMessage('invalid_validation_response');
        (new FonnteClient)->isRegistered('6281234567890');
    }

    public function test_validation_timeout_can_be_retried_without_sending_a_message(): void
    {
        config(['operational_notifications.fonnte.token' => 'fake-secret']);
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));
        try {
            (new FonnteClient)->isRegistered('6281234567890');
            $this->fail('A validation timeout must not authorize sending.');
        } catch (FonnteException $exception) {
            $this->assertSame('transient', $exception->classification);
            $this->assertSame('validation_connection_unavailable', $exception->reasonCode);
        }
    }
}
