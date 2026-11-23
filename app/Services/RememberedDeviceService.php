<?php

namespace App\Services;

use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RememberedDeviceService
{
    public const COOKIE = 'owner_remember_device';

    public const SESSION = 'owner_remember_device_id';

    private function lifetimeMinutes(?User $user = null): int
    {
        $days = $user && app(RolePermissions::class)->roleName($user) !== 'owner' ? 400 : (int) config('auth.owner_remember_days', 400);

        return max(1, min(400, $days)) * 24 * 60;
    }

    public function issue(User $user, Request $request): void
    {
        $id = (string) Str::uuid();
        $token = bin2hex(random_bytes(32));
        DB::table('remembered_devices')->insert([
            'id' => $id, 'user_id' => $user->id,
            'role_id' => $user->role_id,
            'token_hash' => hash('sha256', $token),
            'password_hash' => hash('sha256', $user->password),
            'expires_at' => now()->addMinutes($this->lifetimeMinutes($user)),
            'last_refreshed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $request->session()->put(self::SESSION, $id);
        $this->queue($id.'|'.$token, $user);
    }

    public function resolve(Request $request): ?User
    {
        $cookie = $request->cookie(self::COOKIE);
        if (! is_string($cookie) || ! preg_match('/^([a-f0-9-]{36})\|([a-f0-9]{64})$/D', $cookie, $parts) || ! Str::isUuid($parts[1])) {
            return null;
        }
        $device = DB::table('remembered_devices')->where('id', $parts[1])->where('expires_at', '>', now())->first();
        if (! $device || ! hash_equals($device->token_hash, hash('sha256', $parts[2]))) {
            return null;
        }
        $user = User::query()->where('id', $device->user_id)->first();
        if (! $user || ! $user->is_active || ! app(RolePermissions::class)->isKnownRole((string) app(RolePermissions::class)->roleName($user))
            || $device->role_id !== $user->role_id
            || ! hash_equals($device->password_hash, hash('sha256', $user->password))) {
            return null;
        }

        return $user;
    }

    public function refresh(Request $request, User $user): void
    {
        if (app(RolePermissions::class)->roleName($user) !== 'owner') {
            return;
        }
        $cookie = $request->cookie(self::COOKIE);
        $id = $request->session()->get(self::SESSION);
        if (! is_string($cookie) || ! is_string($id) || ! str_starts_with($cookie, $id.'|')) {
            return;
        }
        $updated = DB::table('remembered_devices')->where('id', $id)->where('user_id', $user->id)
            ->where('expires_at', '>', now())->where('last_refreshed_at', '<=', now()->subDay())
            ->update(['expires_at' => now()->addMinutes($this->lifetimeMinutes()), 'last_refreshed_at' => now(), 'updated_at' => now()]);
        if ($updated) {
            $this->queue($cookie, $user);
        }
    }

    public function sessionDeviceIsValid(string $id, User $user): bool
    {
        if (! Str::isUuid($id) || ! $user->is_active || ! app(RolePermissions::class)->isKnownRole((string) app(RolePermissions::class)->roleName($user))) {
            return false;
        }
        $device = DB::table('remembered_devices')->where('id', $id)->where('user_id', $user->id)->first();

        return $device !== null && $device->role_id === $user->role_id
            && hash_equals($device->password_hash, hash('sha256', $user->password));
    }

    public function revokeCurrent(Request $request, User $user): void
    {
        $id = $request->session()->get(self::SESSION);
        if (is_string($id)) {
            DB::table('remembered_devices')->where('id', $id)->where('user_id', $user->id)->delete();
        }
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    public function revokeAll(User $user): void
    {
        DB::table('remembered_devices')->where('user_id', $user->id)->delete();
    }

    public function logoutEverywhere(User $user, Request $request): void
    {
        if (config('session.driver') !== 'database') {
            abort(503, 'Keluar dari semua perangkat membutuhkan penyimpanan sesi database.');
        }
        DB::transaction(function () use ($user): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->revokeAll($user);
            $user->forceFill(['remember_token' => Str::random(60)])->save();
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        });
        Cookie::queue(Cookie::forget(self::COOKIE));
        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function queue(string $value, User $user): void
    {
        Cookie::queue(Cookie::make(self::COOKIE, $value, $this->lifetimeMinutes($user), config('session.path'), config('session.domain'), config('session.secure'), true, false, config('session.same_site')));
    }
}
