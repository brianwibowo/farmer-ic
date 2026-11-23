<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\RememberedDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OwnerRememberDeviceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_login_automatically_creates_a_device_remember_cookie(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $response = $this->post(route('login.store'), [
            'email' => $owner->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('owner.executive-summary'));
        $response->assertCookie('owner_remember_device');
        $device = DB::table('remembered_devices')->where('user_id', $owner->id)->first();
        $this->assertNotNull($device);
        $this->assertEqualsWithDelta(now()->addDays(400)->timestamp, \Carbon\Carbon::parse($device->expires_at)->timestamp, 2);
    }

    public function test_owner_login_does_not_issue_the_shared_laravel_remember_cookie(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $response = $this->post('/login', ['email' => $owner->email, 'password' => 'password', 'remember' => true]);
        $response->assertCookie(RememberedDeviceService::COOKIE);
        $response->assertCookieExpired(Auth::guard('web')->getRecallerName());
    }

    public function test_two_devices_restore_independently_and_logout_revokes_only_one(): void
    {
        Route::get('/test-owner-device', fn () => 'ok')->middleware(['web', 'auth']);
        $owner = User::factory()->create(['role' => 'owner']);
        $laptop = $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->getCookie(RememberedDeviceService::COOKIE)->getValue();
        $this->resetBrowser();
        $phone = $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->getCookie(RememberedDeviceService::COOKIE)->getValue();
        $this->assertNotSame($laptop, $phone);
        $this->assertSame(2, DB::table('remembered_devices')->where('user_id', $owner->id)->count());

        $this->resetBrowser();
        $this->withCookie(RememberedDeviceService::COOKIE, $laptop)->get('/test-owner-device')->assertOk();
        $this->post('/logout')->assertRedirect('/');
        $this->assertSame(1, DB::table('remembered_devices')->where('user_id', $owner->id)->count());

        $this->resetBrowser();
        $this->withCookie(RememberedDeviceService::COOKIE, $laptop)->get('/test-owner-device')->assertRedirect('/login');
        $this->resetBrowser();
        $this->withCookie(RememberedDeviceService::COOKIE, $phone)->get('/test-owner-device')->assertOk();
    }

    public function test_owner_device_is_refreshed_at_most_once_per_day(): void
    {
        Route::get('/test-owner-device', fn () => 'ok')->middleware(['web', 'auth']);
        $owner = User::factory()->create(['role' => 'owner']);
        $cookie = $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->getCookie(RememberedDeviceService::COOKIE)->getValue();
        $this->resetBrowser();
        $this->withCookie(RememberedDeviceService::COOKIE, $cookie)->get('/test-owner-device')->assertCookieMissing(RememberedDeviceService::COOKIE);
        $this->travel(1)->days();
        $this->withCookie(RememberedDeviceService::COOKIE, $cookie)->get('/test-owner-device')->assertCookie(RememberedDeviceService::COOKIE);
        Cookie::flushQueuedCookies();
        $this->withCookie(RememberedDeviceService::COOKIE, $cookie)->get('/test-owner-device')->assertCookieMissing(RememberedDeviceService::COOKIE);
    }

    public function test_database_session_expiry_restores_owner_from_device_cookie(): void
    {
        config(['session.driver' => 'database', 'session.lifetime' => 120]);
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        Auth::forgetGuards();
        Route::get('/test-owner-device', fn () => 'ok')->middleware(['web', 'auth']);
        $owner = User::factory()->create(['role' => 'owner']);
        $response = $this->post('/login', ['email' => $owner->email, 'password' => 'password']);
        $remember = $response->getCookie(RememberedDeviceService::COOKIE)->getValue();
        $session = $response->getCookie(config('session.cookie'))->getValue();
        $this->travel(121)->minutes();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        Auth::forgetGuards();
        Cookie::flushQueuedCookies();
        $restored = $this->withCookies([config('session.cookie') => $session, RememberedDeviceService::COOKIE => $remember])
            ->get('/test-owner-device')->assertOk();
        $this->assertAuthenticatedAs($owner);
        $this->assertNotSame($session, $restored->getCookie(config('session.cookie'))->getValue());
    }

    public function test_non_owner_remember_me_uses_a_device_token(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => true]);
        $response->assertCookie(RememberedDeviceService::COOKIE);
        $response->assertCookieExpired(Auth::guard('web')->getRecallerName());
        $this->assertSame(1, DB::table('remembered_devices')->count());
    }

    public function test_logout_all_revokes_both_devices_and_database_sessions(): void
    {
        Route::get('/test-owner-device', fn () => 'ok')->middleware(['web', 'auth']);
        $owner = User::factory()->create(['role' => 'owner']);
        $laptop = $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->getCookie(RememberedDeviceService::COOKIE)->getValue();
        $this->resetBrowser();
        $phone = $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->getCookie(RememberedDeviceService::COOKIE)->getValue();
        DB::table('sessions')->insert([
            ['id' => 'test-laptop', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'test-phone', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()],
        ]);
        config(['session.driver' => 'database']);
        $this->withCookie(RememberedDeviceService::COOKIE, $phone)->post('/settings/logout-all-devices')->assertRedirect('/');
        $this->assertSame(0, DB::table('remembered_devices')->where('user_id', $owner->id)->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $owner->id)->count());
        config(['session.driver' => 'array']);
        foreach ([$laptop, $phone] as $cookie) {
            $this->resetBrowser();
            $this->withCookie(RememberedDeviceService::COOKIE, $cookie)->get('/test-owner-device')->assertRedirect('/login');
        }
    }

    public function test_expired_or_tampered_device_cookie_cannot_restore_login(): void
    {
        Route::get('/test-owner-device', fn () => 'ok')->middleware(['web', 'auth']);
        $owner = User::factory()->create(['role' => 'owner']);
        $cookie = $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->getCookie(RememberedDeviceService::COOKIE)->getValue();
        $this->resetBrowser();
        $tampered = substr($cookie, 0, -1).($cookie[-1] === 'a' ? 'b' : 'a');
        $this->withCookie(RememberedDeviceService::COOKIE, $tampered)->get('/test-owner-device')->assertRedirect('/login');
        DB::table('remembered_devices')->where('user_id', $owner->id)->update(['expires_at' => now()->subMinute()]);
        $this->resetBrowser();
        $this->withCookie(RememberedDeviceService::COOKIE, $cookie)->get('/test-owner-device')->assertRedirect('/login');
    }

    public function test_password_change_revokes_owner_devices(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $cookie = $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->getCookie(RememberedDeviceService::COOKIE)->getValue();
        $this->withCookie(RememberedDeviceService::COOKIE, $cookie)->put('/settings/password', [
            'current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('remembered_devices')->where('user_id', $owner->id)->count());
        $this->resetBrowser();
        $this->withCookie(RememberedDeviceService::COOKIE, $cookie)->get('/settings/security')->assertRedirect('/login');
    }

    public function test_guest_cannot_use_logout_all_route(): void
    {
        $this->post('/settings/logout-all-devices')->assertRedirect('/login');
    }

    public function test_owner_legacy_remember_cookie_cannot_bypass_device_revocation(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        Route::post('/test-legacy-login', function () use ($owner) {
            Auth::guard('web')->login($owner, true);

            return response('ok');
        })->middleware('web');
        $response = $this->post('/test-legacy-login');
        $name = Auth::guard('web')->getRecallerName();
        $legacyCookie = $response->getCookie($name)->getValue();
        $this->resetBrowser();
        $this->withCookie($name, $legacyCookie)->get('/settings/security')->assertRedirect('/login');
    }

    public function test_changed_owner_role_cannot_restore_with_old_device_cookie(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $cookie = $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->getCookie(RememberedDeviceService::COOKIE)->getValue();
        $admin = \App\Models\Role::query()->firstOrCreate(['name' => 'admin']);
        $owner->forceFill(['role_id' => $admin->id])->save();
        $this->resetBrowser();
        $this->withCookie(RememberedDeviceService::COOKIE, $cookie)->get('/settings/security')->assertRedirect('/login');
    }

    private function resetBrowser(): void
    {
        $this->flushSession();
        Auth::forgetGuards();
        Cookie::flushQueuedCookies();
        unset($this->defaultCookies[RememberedDeviceService::COOKIE]);
    }
}
