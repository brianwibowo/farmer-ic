<?php

namespace Tests\Feature\Users;

use App\Enums\TeamRole;
use App\Models\Farm;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['superadmin', 'owner', 'admin', 'petugas'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
        $this->developer = User::factory()->create(['role' => 'superadmin']);
        $this->developer->teams()->detach();
        $this->developer->forceFill(['current_team_id' => null])->save();
        $this->developer->unsetRelation('currentTeam');
        $this->actingAs($this->developer);
    }

    public function test_guests_and_other_roles_cannot_use_any_management_endpoint(): void
    {
        $target = User::factory()->create();
        auth()->logout();
        $this->get('/admin/user-management')->assertRedirect('/login');
        $this->post('/admin/user-management', $this->payload())->assertRedirect('/login');

        // Even if the general admin portal is enabled, global user management remains developer-only.
        Gate::define('admin.access', fn (User $user): bool => true);
        foreach (['owner', 'admin', 'petugas'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->assertFalse(Gate::forUser($actor)->allows('users.manageAll'));
            $this->actingAs($actor);
            foreach (['', '/create', '/'.$target->id, '/'.$target->id.'/edit'] as $suffix) {
                $this->get('/admin/user-management'.$suffix)->assertForbidden();
            }
            $this->post('/admin/user-management', $this->payload(['role' => 'superadmin']))->assertForbidden();
            $this->put('/admin/user-management/'.$target->id, $this->payload())->assertForbidden();
            $this->delete('/admin/user-management/'.$target->id)->assertForbidden();
            $this->get('/settings/profile')->assertOk()->assertInertia(fn (Assert $page) => $page->where('navigation.users', false));
        }
        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseMissing('users', ['email' => 'ratna@example.com']);
    }

    public function test_superadmin_without_membership_can_list_filter_and_read_all_users_safely(): void
    {
        $team = Team::factory()->create(['is_personal' => false]);
        $owner = User::factory()->create(['name' => 'Ratna 100%', 'email' => 'ratna.owner@example.com', 'role' => 'owner']);
        $team->members()->attach($owner, ['role' => TeamRole::Member->value]);
        User::factory()->count(16)->create(['role' => 'petugas']);

        $this->get('/admin/user-management')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('users/Index')->where('users.total', 18)->has('users.data', 15)
            ->where('summary.total', 18)->where('summary.superadmin', 1)->where('summary.petugas', 16)->where('navigation.users', true)
            ->where('currentTeam', null)->where('userManagementScope.multiTenant', true)->where('can.create', true)
            ->missing('users.data.0.password')->missing('users.data.0.two_factor_secret'));
        $this->get('/admin/user-management?search=%25')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 1)->where('users.data.0.id', $owner->id));
        $this->get('/admin/user-management?role=owner&team_id='.$team->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 1)->where('users.data.0.id', $owner->id));
        $this->get('/admin/user-management/'.$owner->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('users/Show')->where('managedUser.team_ids', [$team->id])->missing('managedUser.password'));
        $this->get('/admin/user-management/create')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('users/Form')->has('options.roles', 4)->has('options.teams', 1));
        $this->assertSame(18, User::visibleTo($this->developer)->count());
        $this->assertSame(0, User::visibleTo($owner)->count());
        $this->assertSame(0, Farm::visibleTo($owner)->count());
    }

    public function test_owner_can_be_assigned_many_tenants_and_many_admins_can_share_one_tenant(): void
    {
        $teams = Team::factory()->count(2)->create(['is_personal' => false]);
        $this->post('/admin/user-management', $this->payload(['team_ids' => $teams->modelKeys()]))->assertRedirect();
        $owner = User::where('email', 'ratna@example.com')->firstOrFail();
        $this->assertSame('owner', $owner->role->name);
        $this->assertEqualsCanonicalizing($teams->modelKeys(), $owner->teams()->pluck('teams.id')->all());
        $this->assertTrue(Hash::check('kelola-user-123', $owner->password));
        $this->assertSame($teams->first()->id, $owner->current_team_id);
        $this->assertSame(TeamRole::Member, $owner->teamMemberships()->first()->role);
        foreach (['admin.satu@example.com', 'admin.dua@example.com'] as $email) {
            $this->post('/admin/user-management', $this->payload(['email' => $email, 'role' => 'admin', 'team_ids' => [$teams->first()->id]]))->assertRedirect();
        }
        $this->assertSame(3, $teams->first()->members()->count());
        $this->assertFalse(in_array('role_id', (new User)->getFillable(), true));
    }

    public function test_multiple_petugas_can_share_a_farm_and_reassignment_replaces_tenant_and_farm(): void
    {
        $first = Team::factory()->create(['is_personal' => false]);
        $second = Team::factory()->create(['is_personal' => false]);
        $farm = $this->farm($first);
        $nextFarm = $this->farm($second, 'Kebun Utara');
        foreach (['petugas.satu@example.com', 'petugas.dua@example.com'] as $email) {
            $this->post('/admin/user-management', $this->payload([
                'email' => $email, 'role' => 'petugas', 'team_ids' => [$first->id], 'assigned_farm_id' => $farm->id,
            ]))->assertRedirect();
        }
        $target = User::where('email', 'petugas.satu@example.com')->firstOrFail()->load('assignedFarm');
        $this->assertSame(2, User::where('assigned_farm_id', $farm->id)->count());
        $this->get('/admin/user-management/'.$target->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('managedUser.assigned_farm_id', $farm->id)->has('options.farms', 2));
        $this->put('/admin/user-management/'.$target->id, $this->payload([
            'email' => $target->email, 'role' => 'petugas', 'team_ids' => [$second->id], 'assigned_farm_id' => $nextFarm->id, 'password' => null,
        ]))->assertRedirect('/admin/user-management/'.$target->id);
        $target->refresh()->load('assignedFarm');
        $this->assertSame($nextFarm->id, $target->assignedFarm->id);
        $this->assertSame($second->id, $target->current_team_id);
        $this->assertFalse($target->belongsToTeam($first));
        $this->assertTrue($target->belongsToTeam($second));
        $this->assertTrue(Gate::forUser($target)->allows('view', $nextFarm));
        $this->assertFalse(Gate::forUser($target)->allows('view', $farm));
    }

    public function test_invalid_tenant_counts_and_cross_tenant_farm_injection_roll_back(): void
    {
        $first = Team::factory()->create(['is_personal' => false]);
        $second = Team::factory()->create(['is_personal' => false]);
        $foreignFarm = $this->farm($second);
        foreach (['admin', 'petugas'] as $role) {
            $this->post('/admin/user-management', $this->payload(['role' => $role, 'team_ids' => [$first->id, $second->id]]))
                ->assertSessionHasErrors('team_ids');
        }
        $this->post('/admin/user-management', $this->payload([
            'role' => 'petugas', 'team_ids' => [$first->id], 'assigned_farm_id' => $foreignFarm->id,
        ]))->assertSessionHasErrors('assigned_farm_id');
        foreach (['owner', 'admin', 'superadmin'] as $role) {
            $this->post('/admin/user-management', $this->payload([
                'role' => $role, 'team_ids' => [$second->id], 'assigned_farm_id' => $foreignFarm->id,
            ]))->assertSessionHasErrors('assigned_farm_id');
        }
        $this->post('/admin/user-management', $this->payload(['role' => 'petugas', 'assigned_farm_id' => $foreignFarm->id]))
            ->assertSessionHasErrors('assigned_farm_id');
        $personal = Team::factory()->personal()->create();
        $this->post('/admin/user-management', $this->payload(['team_ids' => [$personal->id]]))->assertSessionHasErrors('team_ids.0');
        $second->delete();
        $this->post('/admin/user-management', $this->payload(['team_ids' => [$second->id]]))->assertSessionHasErrors('team_ids.0');
        $this->assertDatabaseMissing('users', ['email' => 'ratna@example.com']);
    }

    public function test_role_change_clears_farm_and_rejects_invalid_downgrade_without_partial_writes(): void
    {
        $first = Team::factory()->create(['is_personal' => false]);
        $second = Team::factory()->create(['is_personal' => false]);
        $this->post('/admin/user-management', $this->payload(['team_ids' => [$first->id, $second->id]]))->assertRedirect();
        $target = User::where('email', 'ratna@example.com')->firstOrFail();
        $this->put('/admin/user-management/'.$target->id, $this->payload(['role' => 'admin', 'team_ids' => [$first->id, $second->id]]))
            ->assertSessionHasErrors('team_ids');
        $this->assertSame('owner', $target->fresh()->role->name);
        $this->assertSame(2, $target->teams()->count());
        $farm = $this->farm($first);
        $this->put('/admin/user-management/'.$target->id, $this->payload([
            'role' => 'petugas', 'team_ids' => [$first->id], 'assigned_farm_id' => $farm->id,
        ]))->assertRedirect();
        $this->put('/admin/user-management/'.$target->id, $this->payload(['role' => 'admin', 'team_ids' => [$first->id]]))->assertRedirect();
        $this->assertNull($target->fresh()->assignedFarmId());
        $this->assertSame('admin', $target->fresh()->role->name);

        $legacy = User::factory()->create(['role' => 'owner']);
        $personal = $legacy->currentTeam;
        $first->members()->attach($legacy, ['role' => TeamRole::Owner->value]);
        $legacy->switchTeam($first);
        $this->put('/admin/user-management/'.$legacy->id, $this->payload([
            'email' => $legacy->email, 'role' => 'admin', 'team_ids' => [$first->id],
        ]))->assertRedirect();
        $this->assertTrue($legacy->fresh()->belongsToTeam($personal));
        $this->assertSame(TeamRole::Owner, $legacy->fresh()->teamRole($first));
        $this->assertSame($first->id, $legacy->fresh()->current_team_id);
    }

    public function test_password_changes_are_optional_hashed_and_invalidate_other_users_sessions(): void
    {
        $target = User::factory()->create(['role' => 'petugas']);
        $password = $target->password;
        $this->put('/admin/user-management/'.$target->id, $this->payload(['email' => $target->email, 'password' => null, 'password_confirmation' => null]))
            ->assertRedirect();
        $this->assertSame($password, $target->fresh()->password);
        DB::table('sessions')->insert(['id' => 'user-session', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);
        $this->put('/admin/user-management/'.$target->id, $this->payload(['email' => $target->email, 'password' => 'password-baru-456', 'password_confirmation' => 'password-baru-456']))
            ->assertRedirect();
        $this->assertTrue(Hash::check('password-baru-456', $target->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'user-session']);
    }

    public function test_profile_validation_rejects_unknown_roles_duplicate_email_and_sensitive_fields(): void
    {
        $this->post('/admin/user-management', $this->payload(['role' => 'outsider']))->assertSessionHasErrors('role');
        $this->post('/admin/user-management', $this->payload(['role' => []]))->assertSessionHasErrors('role');
        $this->post('/admin/user-management', $this->payload(['email' => $this->developer->email]))->assertSessionHasErrors('email');
        $this->post('/admin/user-management', $this->payload(['role_id' => $this->developer->role_id]))->assertSessionHasErrors('role_id');
        $this->post('/admin/user-management', $this->payload(['current_team_id' => 123]))->assertSessionHasErrors('current_team_id');
        $this->post('/admin/user-management', $this->payload(['password_confirmation' => 'salah']))->assertSessionHasErrors('password');
        $this->post('/admin/user-management', $this->payload(['name' => '   ']))->assertSessionHasErrors('name');
    }

    public function test_delete_is_guarded_and_referenced_users_are_preserved(): void
    {
        $this->delete('/admin/user-management/'.$this->developer->id)->assertSessionHasErrors('delete');
        $this->put('/admin/user-management/'.$this->developer->id, $this->payload(['email' => $this->developer->email, 'role' => 'admin']))
            ->assertSessionHasErrors('role');
        $target = User::factory()->create(['role' => 'petugas']);
        $team = Team::factory()->create(['is_personal' => false]);
        $team->members()->attach($target, ['role' => TeamRole::Member->value]);
        $referenced = $this->farm($team);
        $referenced->forceFill(['created_by' => $target->id])->save();
        DB::table('sessions')->insert(['id' => 'deleted-user-session', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);
        $this->delete('/admin/user-management/'.$target->id)->assertRedirect('/admin/user-management');
        $this->assertSoftDeleted('users', ['id' => $target->id]);
        $this->assertDatabaseHas('team_members', ['user_id' => $target->id, 'team_id' => $team->id]);
        $this->assertDatabaseHas('farms', ['id' => $referenced->id, 'created_by' => $target->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'deleted-user-session']);
        $this->assertNull(User::find($target->id));
        $this->get('/admin/user-management/'.$target->id)->assertNotFound();
        $this->post('/admin/user-management', $this->payload(['email' => $target->email]))->assertSessionHasErrors('email');
        auth()->logout();
        $this->post(route('login.store'), ['email' => $target->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_last_developer_integrity_guard_and_optional_unassigned_accounts(): void
    {
        $actor = User::factory()->create(['role' => 'superadmin'])->load('role');
        // Simulate an already-authorized request whose actor was concurrently deleted.
        User::query()->whereKey($actor->id)->delete();
        try {
            app(UserManagementService::class)->delete($actor, $this->developer);
            $this->fail('The last developer must be preserved.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }
        $this->post('/admin/user-management', $this->payload(['role' => 'petugas']))->assertRedirect();
        $target = User::where('email', 'ratna@example.com')->firstOrFail();
        $this->assertNull($target->current_team_id);
        $this->assertNull($target->assignedFarmId());
        $this->assertSame(0, Farm::visibleTo($target)->count());
        $this->post('/admin/user-management', $this->payload(['email' => 'developer.dua@example.com', 'role' => 'superadmin']))->assertRedirect();
        $second = User::where('email', 'developer.dua@example.com')->firstOrFail();
        $this->delete('/admin/user-management/'.$second->id)->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $this->developer->id]);
    }

    private function farm(Team $team, string $name = 'Kebun Pertiwi'): Farm
    {
        $farm = new Farm(['name' => $name, 'total_area_ha' => '5']);
        $farm->forceFill(['created_by' => $this->developer->id, 'team_id' => $team->id])->save();

        return $farm;
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Ratna Pertiwi', 'email' => 'ratna@example.com', 'role' => 'owner',
            'password' => 'kelola-user-123', 'password_confirmation' => 'kelola-user-123',
            'team_ids' => [], 'assigned_farm_id' => null,
        ], $overrides);
    }
}
