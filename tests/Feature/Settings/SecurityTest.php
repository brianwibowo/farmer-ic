<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_is_displayed()
    {
        // The implemented screen is password settings; 2FA being disabled must not skip it.
        $user = User::factory()->create(['role' => 'petugas']);

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->has('passwordRules')
                ->missing('canManageTwoFactor')
                ->missing('twoFactorEnabled'),
            );
    }

    public function test_security_page_renders_without_two_factor_when_feature_is_disabled()
    {
        config(['fortify.features' => []]);

        $user = User::factory()->create(['role' => 'petugas']);

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->has('passwordRules')
                ->missing('canManageTwoFactor')
                ->missing('twoFactorEnabled')
                ->missing('requiresConfirmation'),
            );
    }

    public function test_only_petugas_uses_security_page_and_other_roles_redirect_to_profile(): void
    {
        foreach (['owner', 'admin', 'superadmin', 'petugas'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)->get(route('security.edit'));
            if ($role === 'petugas') {
                $response->assertOk()->assertInertia(fn (Assert $page) => $page
                    ->component('settings/Security')
                    ->where('securityPresentation.surface', 'mobile')
                    ->where('securityPresentation.returnUrl', '/settings/profile'));
            } else {
                $response->assertRedirect(route('profile.edit'));
            }
            $this->actingAs($user)->get(route('profile.edit'))->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('settings/Profile')->where('canLogoutAllDevices', true));
        }
    }

    public function test_password_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('security.edit'));
    }
}
