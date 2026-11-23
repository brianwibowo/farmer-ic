<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\RememberedDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RememberedDevicesTest extends TestCase
{
    use RefreshDatabase;

    public static function nonOwners(): array
    {
        return [['admin'], ['petugas'], ['superadmin']];
    }

    #[DataProvider('nonOwners')]
    public function test_non_owner_requires_opt_in_and_remembers_each_device_without_refresh(string $role): void
    {
        Route::get('/remembered-device-test', fn () => 'ok')->middleware(['web', 'auth']);
        $user = User::factory()->create(['role' => $role]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertCookieMissing(RememberedDeviceService::COOKIE);
        $this->resetBrowser();
        $first = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => true])->getCookie(RememberedDeviceService::COOKIE)->getValue();
        $this->resetBrowser();
        $second = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => true])->getCookie(RememberedDeviceService::COOKIE)->getValue();
        $this->assertNotSame($first, $second);
        $this->assertSame(2, DB::table('remembered_devices')->where('user_id', $user->id)->count());
        $this->resetBrowser();
        $this->travel(1)->days();
        $this->withCookie(RememberedDeviceService::COOKIE, $first)->get('/remembered-device-test')->assertOk()->assertCookieMissing(RememberedDeviceService::COOKIE);
        $this->post('/logout')->assertRedirect('/');
        $this->resetBrowser();
        $this->withCookie(RememberedDeviceService::COOKIE, $first)->get('/remembered-device-test')->assertRedirect('/login');
        $this->resetBrowser();
        $this->withCookie(RememberedDeviceService::COOKIE, $second)->get('/remembered-device-test')->assertOk();
    }

    private function resetBrowser(): void
    {
        $this->flushSession();
        Auth::forgetGuards();
        Cookie::flushQueuedCookies();
        unset($this->defaultCookies[RememberedDeviceService::COOKIE]);
    }

    #[DataProvider('nonOwners')]
    public function test_non_owner_can_sign_out_all_devices_without_affecting_other_accounts(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $other = User::factory()->create(['role' => 'owner']);
        $otherCookie = $this->post('/login', ['email' => $other->email, 'password' => 'password'])->getCookie(RememberedDeviceService::COOKIE)->getValue();
        $this->resetBrowser();
        $first = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => true])->getCookie(RememberedDeviceService::COOKIE)->getValue();
        $this->resetBrowser();
        $second = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => true])->getCookie(RememberedDeviceService::COOKIE)->getValue();
        config(['session.driver' => 'database']);
        $this->withCookie(RememberedDeviceService::COOKIE, $second)->post('/settings/logout-all-devices')->assertRedirect('/');
        $this->assertSame(0, DB::table('remembered_devices')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('remembered_devices')->where('user_id', $other->id)->count());
        config(['session.driver' => 'array']);
        foreach ([$first, $second] as $cookie) {
            $this->resetBrowser();
            $this->withCookie(RememberedDeviceService::COOKIE, $cookie)->get('/settings/profile')->assertRedirect('/login');
        }
        $this->resetBrowser();
        $this->withCookie(RememberedDeviceService::COOKIE, $otherCookie)->get('/settings/profile')->assertOk();
    }

    public function test_rename_preserves_existing_owner_device_and_cookie(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $cookie = $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->getCookie(RememberedDeviceService::COOKIE)->getValue();
        $migration = require database_path('migrations/2026_11_23_000002_extend_remembered_devices_to_all_roles.php');
        $migration->down();
        $this->assertSame(1, DB::table('owner_remembered_devices')->where('user_id', $owner->id)->count());
        $migration->up();
        $this->assertDatabaseHas('remembered_devices', ['user_id' => $owner->id, 'role_id' => $owner->role_id]);
        $this->resetBrowser();
        $this->withCookie(RememberedDeviceService::COOKIE, $cookie)->get('/settings/profile')->assertOk();
    }

    public function test_expired_remember_cookie_does_not_end_an_active_session(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => true])->assertCookie(RememberedDeviceService::COOKIE);
        $this->travel(401)->days();
        unset($this->defaultCookies[RememberedDeviceService::COOKIE]);
        $this->get('/settings/profile')->assertOk();
        $this->resetBrowser();
        $this->get('/settings/profile')->assertRedirect('/login');
    }

    #[DataProvider('nonOwners')]
    public function test_legacy_remember_cookie_cannot_restore_a_non_owner(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        Route::post('/legacy-remember-test', function () use ($user) {
            Auth::guard('web')->login($user, true);

            return response('ok');
        })->middleware('web');
        $response = $this->post('/legacy-remember-test');
        $name = Auth::guard('web')->getRecallerName();
        $cookie = $response->getCookie($name)->getValue();
        $this->resetBrowser();
        $this->withCookie($name, $cookie)->get('/settings/profile')->assertRedirect('/login');
    }
}
