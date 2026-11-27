<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\VerifyTurnstile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TurnstileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware(VerifyTurnstile::class);
        config()->set('services.turnstile.secret', 'server-secret');
        config()->set('services.turnstile.hostnames', 'example.test,other.test');
    }

    public function test_both_endpoints_reject_missing_tokens_and_missing_configuration(): void
    {
        Http::fake();
        foreach (['/login', '/forgot-password'] as $path) {
            $this->postJson($path, [])->assertForbidden();
            config()->set('services.turnstile.secret', '');
            $this->postJson($path, ['cf-turnstile-response' => 'token'])->assertForbidden();
            config()->set('services.turnstile.secret', 'server-secret');
        }
        Http::assertNothingSent();
    }

    public function test_each_form_requires_its_action_and_approved_hostname(): void
    {
        $result = [];
        Http::fake(function () use (&$result) {
            return Http::response($result);
        });
        foreach (['/login' => 'login', '/forgot-password' => 'forgot_password'] as $path => $action) {
            foreach ([
                ['success' => false, 'action' => $action, 'hostname' => 'example.test'],
                ['success' => true, 'action' => 'wrong', 'hostname' => 'example.test'],
                ['success' => true, 'action' => $action, 'hostname' => 'other.example'],
            ] as $candidate) {
                $result = $candidate;
                $this->postJson($path, ['cf-turnstile-response' => 'token'])->assertForbidden();
            }
            $result = ['success' => true, 'action' => $action, 'hostname' => 'example.test'];
            // Valid verification reaches Fortify's own validation.
            $this->postJson($path, ['cf-turnstile-response' => 'token'])->assertUnprocessable();
        }
    }

    public function test_siteverify_failure_and_oversized_tokens_fail_closed(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response([], 500)]);
        $this->postJson('/login', ['cf-turnstile-response' => 'token'])->assertForbidden();
        $this->postJson('/forgot-password', ['cf-turnstile-response' => str_repeat('a', 2049)])->assertForbidden();
        config()->set('services.turnstile.hostnames', '');
        $this->postJson('/login', ['cf-turnstile-response' => 'token'])->assertForbidden();
    }

    public function test_rejection_explains_how_to_retry_each_form(): void
    {
        foreach (['/login' => route('login'), '/forgot-password' => route('password.request')] as $path => $retryUrl) {
            $this->post($path)->assertForbidden()->assertInertia(fn (Assert $page) => $page
                ->component('errors/Forbidden')
                ->where('turnstileFailed', true)
                ->where('retryUrl', $retryUrl));
        }
    }

    public function test_tokens_are_not_flashed_with_validation_errors(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true, 'action' => 'login', 'hostname' => 'example.test'])]);
        $this->from('/login')->post('/login', ['email' => 'bad', 'cf-turnstile-response' => 'private-token'])
            ->assertRedirect('/login')->assertSessionHasErrors('email')
            ->assertSessionMissing('_old_input.cf-turnstile-response');
    }

    public function test_login_throttle_runs_before_siteverify(): void
    {
        Http::fake();
        $email = 'limited@example.test';
        RateLimiter::increment(md5('login'.$email.'|127.0.0.1'), amount: 5);
        $this->postJson('/login', ['email' => $email, 'password' => 'password', 'cf-turnstile-response' => 'token'])
            ->assertTooManyRequests();
        Http::assertNothingSent();
    }

    public function test_upstream_verification_is_bounded_per_ip(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true, 'action' => 'forgot_password', 'hostname' => 'example.test'])]);
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/forgot-password', ['cf-turnstile-response' => 'token'])->assertUnprocessable();
        }
        $this->postJson('/forgot-password', ['cf-turnstile-response' => 'token'])->assertTooManyRequests();
        Http::assertSentCount(30);
    }

    public function test_production_refuses_local_hostnames_even_with_different_case(): void
    {
        $this->app['env'] = 'production';
        config()->set('services.turnstile.hostnames', 'example.test,LOCALHOST');
        Http::fake();
        $this->assertFalse(app(\App\Services\TurnstileService::class)->verify('token', 'forgot_password', '127.0.0.1'));
        Http::assertNothingSent();
    }
}
