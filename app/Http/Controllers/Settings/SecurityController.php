<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $role = app(RolePermissions::class)->roleName($user);

        $props = [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'securityPresentation' => [
                'surface' => in_array($role, ['owner', 'petugas'], true) ? 'mobile' : 'desktop',
                'returnUrl' => route('profile.edit'),
            ],
        ];

        return Inertia::render('settings/Security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
