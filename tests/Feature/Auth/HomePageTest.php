<?php

namespace Tests\Feature\Auth;

use App\Enums\TeamRole;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Landing-page routing on the dedicated integrated PostgreSQL test schema. */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_open_login_instead_of_the_demo_dashboard(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')->where('auth.user', null));
    }

    public static function portalDestinations(): array
    {
        return [
            'admin' => ['admin', '/admin/dashboard'],
            'superadmin' => ['superadmin', '/admin/dashboard'],
            'petugas' => ['petugas', '/petugas'],
        ];
    }

    #[DataProvider('portalDestinations')]
    public function test_home_redirects_authenticated_users_to_their_portal(string $role, string $destination): void
    {
        $actor = User::factory()->create(['role' => $role]);
        if ($role === 'admin') {
            $team = Team::factory()->create(['is_personal' => false]);
            $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
            $actor->switchTeam($team);
        }

        $this->actingAs($actor)->get('/')->assertRedirect($destination);
        $this->get($destination)->assertOk();
        $this->assertSame($role === 'superadmin', Gate::forUser($actor)->allows('owner.access'));
    }

    public function test_owner_dashboard_uses_the_real_authenticated_identity(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farmer/Dashboard')
            ->where('auth.user.id', $owner->id)
            ->where('account', ['name' => $owner->name, 'email' => $owner->email])
            ->where('currentTeam.id', $owner->current_team_id)
            ->where('navigation.admin', false));
        $this->assertTrue(Gate::forUser($owner)->allows('owner.access'));
    }

    public function test_visiting_home_before_login_does_not_return_petugas_to_the_demo(): void
    {
        $petugas = User::factory()->create(['role' => 'petugas']);

        $this->get('/')->assertRedirect('/login');
        $this->post('/login', ['email' => $petugas->email, 'password' => 'password'])->assertRedirect('/');
        $this->get('/')->assertRedirect('/petugas');
        $this->get('/petugas')->assertOk();
    }

    public function test_admin_without_a_valid_tenant_cannot_fall_back_to_owner_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/')->assertForbidden();
    }

    public function test_missing_or_unknown_roles_cannot_open_owner_dashboard(): void
    {
        $actor = User::factory()->create(['role' => 'owner']);
        $actor->setRelation('role', null);
        $this->actingAs($actor)->get('/')->assertForbidden();

        $unknown = User::factory()->create(['role' => 'owner']);
        $unknown->setRelation('role', new Role(['name' => 'unrecognized']));
        $this->actingAs($unknown)->get('/')->assertForbidden();
    }

    public function test_removing_owner_role_configuration_denies_owner_dashboard(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $roles = app(RolePermissions::class)->roles();
        unset($roles['owner']);
        config(['access.roles' => $roles]);

        $this->actingAs($owner)->get('/')->assertForbidden();
    }
}
