<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\User;
use App\Services\RememberedDeviceService;
use App\Support\RolePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $role = app(RolePermissions::class)->roleName($user);
        if ($role !== 'petugas') {
            return to_route('profile.edit');
        }

        $props = [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'securityPresentation' => [
                'surface' => 'mobile',
                'returnUrl' => route('profile.edit', absolute: false),
            ],
        ];

        return Inertia::render('settings/Security', $props);
    }

    public function logoutAllDevices(Request $request, RememberedDeviceService $devices): RedirectResponse
    {
        Gate::authorize('account.logoutAllDevices');
        $devices->logoutEverywhere($request->user(), $request);

        return redirect('/');
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $request->user()->update(['password' => $request->password]);
            app(RememberedDeviceService::class)->revokeAll($request->user());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kata sandi berhasil diperbarui.']);

        return back();
    }
}
