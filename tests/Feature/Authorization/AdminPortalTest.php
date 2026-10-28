<?php

namespace Tests\Feature\Authorization;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_urls_and_guests(): void
    {
        $this->assertSame('/admin/dashboard', route('dashboard', absolute: false));
        $this->assertSame('/admin/farm-management', route('farm-management.index', absolute: false));
        foreach (['/admin', '/admin/dashboard', '/admin/farm-management', '/admin/farm-management/create'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_admin_and_superadmin_can_enter_and_get_admin_navigation(): void
    {
        foreach (['admin', 'superadmin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/admin')->assertRedirect('/admin/dashboard');
            $this->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')->where('navigation.admin', true)->where('navigation.farms', true));
            $this->get('/admin/farm-management')->assertOk();
        }
    }

    public function test_owner_petugas_and_team_owners_cannot_enter_admin(): void
    {
        foreach (['owner', 'petugas'] as $role) {
            // The factory makes this actor a Team owner. That does not confer an application Admin role.
            $user = User::factory()->create(['role' => $role]);
            $this->assertTrue($user->ownsTeam($user->currentTeam));
            $this->assertFalse(Gate::forUser($user)->allows('admin.access'));
            $this->actingAs($user);
            foreach (['/admin', '/admin/dashboard', '/admin/farm-management', '/admin/farm-management/create'] as $url) {
                $this->get($url)->assertForbidden();
            }
            $this->post('/admin/farm-management', [])->assertForbidden();
            $this->get('/settings/profile')->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('navigation.admin', false)->where('navigation.farms', false));
            $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('farmer/Dashboard')->where('navigation.admin', false)->where('navigation.farms', false));
        }
    }

    public function test_admin_still_needs_valid_persisted_team_membership(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $team = $admin->currentTeam;
        $team->members()->updateExistingPivot($admin->id, ['role' => TeamRole::Member->value]);
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
        $team->members()->detach($admin->id);
        $this->get('/admin/dashboard')->assertForbidden();
        $this->get('/admin/farm-management')->assertForbidden();

        $team->members()->attach($admin, ['role' => TeamRole::Member->value]);
        foreach (range(1, 2) as $index) {
            Team::factory()->create(['is_personal' => false])->members()->attach($admin, ['role' => TeamRole::Member->value]);
        }
        $this->get('/admin/dashboard')->assertForbidden();
    }

    public function test_missing_application_role_and_current_team_deny(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->forceFill(['current_team_id' => null])->save();
        $user->unsetRelation('currentTeam');
        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
        // Missing relations must deny without inserting an invalid NULL role_id into the integrated schema.
        $user->setRelation('role', null);
        $this->assertFalse(Gate::forUser($user)->allows('admin.access'));
    }

    public function test_unprefixed_admin_pages_are_no_longer_registered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/farm-management')->assertNotFound();
        $this->get('/'.$admin->currentTeam->slug.'/dashboard')->assertNotFound();
    }

    public function test_login_and_authenticated_guest_routes_use_the_correct_landing_page(): void
    {
        foreach (['owner', 'petugas', 'admin', 'superadmin'] as $role) {
            auth()->logout();
            $user = User::factory()->create(['role' => $role]);
            $destination = in_array($role, ['admin', 'superadmin'], true) ? '/admin/dashboard' : '/';
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect($destination);
            $this->get('/login')->assertRedirect($destination);
        }
    }

    public function test_owner_login_discards_an_intended_admin_url_but_preserves_other_intended_pages(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->withSession(['url.intended' => url('/admin/farm-management')])
            ->post('/login', ['email' => $owner->email, 'password' => 'password'])->assertRedirect('/');
        auth()->logout();
        $this->withSession(['url.intended' => url('/kebun')])
            ->post('/login', ['email' => $owner->email, 'password' => 'password'])->assertRedirect('/kebun');
    }
}
