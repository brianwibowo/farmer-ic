<?php

namespace Tests\Feature\Users;

use App\Enums\TeamRole;
use App\Models\Farm;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\UserManagementService;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Team $tenant;

    private Team $otherTenant;

    private User $admin;

    private User $colleague;

    private User $petugas;

    private User $owner;

    private User $developer;

    private User $foreign;

    private Farm $farm;

    private Farm $foreignFarm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Team::factory()->create(['name' => 'Pertiwi Agro', 'is_personal' => false]);
        $this->otherTenant = Team::factory()->create(['name' => 'Lestari Agro', 'is_personal' => false]);
        $this->admin = $this->actor('admin', $this->tenant, 'Admin Pertiwi');
        $this->colleague = $this->actor('admin', $this->tenant, 'Admin Rekan');
        $this->petugas = $this->actor('petugas', $this->tenant, 'Petugas Pertiwi');
        $this->owner = $this->actor('owner', $this->tenant, 'Owner Pertiwi');
        $this->developer = $this->actor('superadmin', $this->tenant, 'Developer');
        $this->foreign = $this->actor('petugas', $this->otherTenant, 'Petugas Rahasia');
        $this->farm = $this->farm($this->tenant, 'Kebun Pertiwi');
        $this->foreignFarm = $this->farm($this->otherTenant, 'Kebun Rahasia');
        $this->actingAs($this->admin);
    }

    public function test_admin_lists_only_admin_and_petugas_of_its_tenant_with_matching_totals_and_options(): void
    {
        User::factory()->create(['role' => 'admin']);
        $broken = $this->actor('petugas', $this->tenant, 'Petugas Dua Tenant');
        $this->otherTenant->members()->attach($broken, ['role' => TeamRole::Member->value]);
        $this->get('/admin/user-management')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('users/Index')->where('navigation.users', true)->where('navigation.admin', true)
            ->where('userManagementScope.global', false)->where('userManagementScope.multiTenant', false)->where('userManagementScope.team.id', $this->tenant->id)
            ->where('users.total', 3)->where('summary.total', 3)->where('summary.admin', 2)
            ->where('summary.petugas', 1)->where('summary.owner', 0)->has('options.roles', 2)->has('options.teams', 1)
            ->where('options.teams.0.id', $this->tenant->id)
            ->where('users.data', fn ($rows) => collect($rows)->every(fn ($row) => in_array($row['id'], [$this->admin->id, $this->colleague->id, $this->petugas->id], true))));
        $this->get('/admin/user-management?team_id='.$this->otherTenant->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 0)->where('summary.total', 3));
        $this->get('/admin/user-management?search=Rahasia')->assertOk()->assertInertia(fn (Assert $page) => $page->where('users.total', 0));
        $this->get('/admin/user-management?role=owner')->assertOk()->assertInertia(fn (Assert $page) => $page->where('users.total', 0));
        $this->get('/admin/user-management/create')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('can.configure', true)->has('options.farms', 1)->where('options.farms.0.id', $this->farm->id)
            ->where('options.roles.0.value', 'admin')->where('options.roles.1.value', 'petugas'));
        $this->assertSame(3, User::visibleTo($this->admin, $this->tenant)->count());
        $this->assertSame(0, User::visibleTo($this->admin, $this->otherTenant)->count());
        $this->assertSame(0, User::visibleTo($this->admin)->count());
        $this->assertFalse(Gate::forUser($this->admin)->allows('users.manageAll'));
    }

    public function test_cross_tenant_and_higher_role_targets_are_denied_on_all_record_actions(): void
    {
        foreach ([$this->owner, $this->developer, $this->foreign] as $target) {
            $this->get('/admin/user-management/'.$target->id)->assertForbidden();
            $this->get('/admin/user-management/'.$target->id.'/edit')->assertForbidden();
            // Early policy admission must run before validating hidden target input.
            $this->put('/admin/user-management/'.$target->id, [])->assertForbidden();
            $this->delete('/admin/user-management/'.$target->id)->assertForbidden();
            $this->assertDatabaseHas('users', ['id' => $target->id]);
        }
    }

    public function test_farm_filter_only_returns_assigned_users_in_the_admin_tenant(): void
    {
        $this->petugas->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        foreach (range(1, 15) as $index) {
            $member = $this->actor('petugas', $this->tenant, 'Petugas Kebun '.$index);
            $member->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        }
        $this->foreign->forceFill(['assigned_farm_id' => $this->foreignFarm->id])->save();
        // Even inconsistent assignments cannot make a foreign farm visible to the admin.
        $this->colleague->forceFill(['assigned_farm_id' => $this->foreignFarm->id])->save();

        $this->get('/admin/user-management?farm_id='.$this->farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('options.farms', 1)->where('options.farms.0.id', $this->farm->id)
            ->where('filters.farm_id', $this->farm->id)->where('users.total', 16)->has('users.data', 15)
            ->where('summary.total', 18)->where('users.next_page_url', fn ($url) => str_contains($url, 'farm_id='.$this->farm->id)));
        $this->get('/admin/user-management?farm_id='.$this->farm->id.'&page=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 16)->has('users.data', 1));
        $this->get('/admin/user-management?farm_id='.$this->foreignFarm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 0)->has('options.farms', 1));
        $this->get('/admin/user-management?farm_id=not-a-uuid')->assertSessionHasErrors('farm_id');
    }

    public function test_superadmin_can_filter_by_farm_across_tenants(): void
    {
        $this->petugas->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->foreign->forceFill(['assigned_farm_id' => $this->foreignFarm->id])->save();
        $this->actingAs($this->developer)->get('/admin/user-management?farm_id='.$this->foreignFarm->id)
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('options.farms', 2)->where('users.total', 1)->where('users.data.0.id', $this->foreign->id));
    }

    public function test_admin_crud_and_petugas_assignment_work_within_the_fixed_tenant(): void
    {
        foreach (['admin', 'petugas'] as $role) {
            $email = 'baru.'.$role.'@example.com';
            $this->post('/admin/user-management', $this->payload(['role' => $role, 'email' => $email]))->assertRedirect();
            $target = User::where('email', $email)->firstOrFail();
            $this->assertSame([$this->tenant->id], $target->teams()->pluck('teams.id')->all());
            $this->assertSame($this->tenant->id, $target->current_team_id);
            $this->assertTrue(Hash::check('password-user-123', $target->password));
            $this->get('/admin/user-management/'.$target->id)->assertOk();
            $this->get('/admin/user-management/'.$target->id.'/edit')->assertOk();
            $data = $this->payload(['email' => $email, 'name' => 'Nama Diperbarui', 'role' => $role, 'password' => null]);
            if ($role === 'petugas') {
                $data['assigned_farm_id'] = $this->farm->id;
            }
            $this->put('/admin/user-management/'.$target->id, $data)->assertRedirect();
            $this->assertSame('Nama Diperbarui', $target->fresh()->name);
            $this->assertSame($role === 'petugas' ? $this->farm->id : null, $target->fresh()->assignedFarmId());
            $this->delete('/admin/user-management/'.$target->id)->assertRedirect('/admin/user-management');
            $this->assertSoftDeleted('users', ['id' => $target->id]);
        }
    }

    public function test_restore_is_scoped_and_soft_delete_preserves_references(): void
    {
        $this->petugas->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->farm->forceFill(['created_by' => $this->petugas->id])->save();
        $notificationId = (string) Str::uuid();
        DB::table('notifications')->insert(['id' => $notificationId, 'user_id' => $this->petugas->id, 'created_at' => now()]);
        $conversationId = (string) Str::uuid();
        $messageId = (string) Str::uuid();
        DB::table('ai_conversations')->insert(['id' => $conversationId, 'user_id' => $this->petugas->id, 'team_id' => $this->tenant->id, 'created_at' => now()]);
        DB::table('ai_messages')->insert(['id' => $messageId, 'ai_conversation_id' => $conversationId, 'user_id' => $this->petugas->id, 'role' => 'user', 'created_at' => now()]);
        $this->delete('/admin/user-management/'.$this->petugas->id)->assertRedirect();
        $this->foreign->delete();
        $this->owner->delete();
        $this->assertSoftDeleted('users', ['id' => $this->petugas->id]);
        $this->assertDatabaseHas('team_members', ['team_id' => $this->tenant->id, 'user_id' => $this->petugas->id]);
        $this->assertDatabaseHas('farms', ['id' => $this->farm->id, 'created_by' => $this->petugas->id]);
        $this->assertDatabaseHas('notifications', ['id' => $notificationId, 'user_id' => $this->petugas->id]);
        $this->assertDatabaseHas('ai_conversations', ['id' => $conversationId, 'user_id' => $this->petugas->id]);
        $this->assertDatabaseHas('ai_messages', ['id' => $messageId, 'user_id' => $this->petugas->id, 'ai_conversation_id' => $conversationId]);
        $this->get('/admin/user-management/deleted')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('users/Deleted')->where('users.total', 1)->where('users.data.0.id', $this->petugas->id)->where('users.data.0.can_restore', true));
        $this->patch('/admin/user-management/'.$this->foreign->id.'/restore')->assertForbidden();
        $this->patch('/admin/user-management/'.$this->owner->id.'/restore')->assertForbidden();
        $this->patch('/admin/user-management/'.$this->petugas->id.'/restore')->assertRedirect('/admin/user-management/deleted');
        $this->assertNotSoftDeleted('users', ['id' => $this->petugas->id]);
        $this->assertSame($this->farm->id, $this->petugas->fresh()->assignedFarmId());
        $this->assertDatabaseHas('notifications', ['id' => $notificationId]);
        $this->patch('/admin/user-management/'.$this->petugas->id.'/restore')->assertForbidden();
        $this->actingAs($this->developer)->get('/admin/user-management/deleted')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 2));
        $this->patch('/admin/user-management/'.$this->foreign->id.'/restore')->assertRedirect();
        $this->patch('/admin/user-management/'.$this->owner->id.'/restore')->assertRedirect();
        $this->assertNotSoftDeleted('users', ['id' => $this->foreign->id]);
    }

    public function test_restore_requires_grant_and_denies_other_roles(): void
    {
        $this->petugas->delete();
        $roles = app(RolePermissions::class)->roles();
        unset($roles['admin']['users.restore']);
        config(['access.roles' => $roles]);
        $this->get('/admin/user-management/deleted')->assertForbidden();
        $this->patch('/admin/user-management/'.$this->petugas->id.'/restore')->assertForbidden();
        $this->actingAs($this->owner)->get('/admin/user-management/deleted')->assertForbidden();
        $this->patch('/admin/user-management/'.$this->petugas->id.'/restore')->assertForbidden();
        $this->actingAs($this->foreign)->get('/admin/user-management/deleted')->assertForbidden();
        $this->patch('/admin/user-management/'.$this->petugas->id.'/restore')->assertForbidden();
        $this->assertSoftDeleted('users', ['id' => $this->petugas->id]);
    }

    public function test_admin_cannot_promote_users_or_move_or_remove_tenant_memberships(): void
    {
        foreach (['owner', 'superadmin'] as $role) {
            $this->post('/admin/user-management', $this->payload(['role' => $role]))->assertForbidden();
            $this->put('/admin/user-management/'.$this->petugas->id, $this->payload(['email' => $this->petugas->email, 'role' => $role]))->assertForbidden();
        }
        foreach ([[], [$this->otherTenant->id], [$this->tenant->id, $this->otherTenant->id]] as $ids) {
            $this->post('/admin/user-management', $this->payload(['team_ids' => $ids]))->assertForbidden();
            $this->put('/admin/user-management/'.$this->petugas->id, $this->payload(['email' => $this->petugas->email, 'team_ids' => $ids]))->assertForbidden();
        }
        $this->assertSame('petugas', $this->petugas->fresh()->role->name);
        $this->assertTrue($this->petugas->belongsToTeam($this->tenant));
        $this->assertFalse($this->petugas->belongsToTeam($this->otherTenant));
        $this->assertDatabaseMissing('users', ['email' => 'user.baru@example.com']);
    }

    public function test_only_petugas_can_be_assigned_to_a_farm_in_the_same_tenant(): void
    {
        $this->put('/admin/user-management/'.$this->petugas->id, $this->payload([
            'email' => $this->petugas->email, 'assigned_farm_id' => $this->foreignFarm->id,
        ]))->assertForbidden();
        $this->post('/admin/user-management', $this->payload(['assigned_farm_id' => $this->foreignFarm->id]))->assertForbidden();
        $this->put('/admin/user-management/'.$this->colleague->id, $this->payload([
            'email' => $this->colleague->email, 'role' => 'admin', 'assigned_farm_id' => $this->farm->id,
        ]))->assertForbidden();
        $this->put('/admin/user-management/'.$this->petugas->id, $this->payload([
            'email' => $this->petugas->email, 'assigned_farm_id' => $this->farm->id,
        ]))->assertRedirect();
        $this->assertSame($this->farm->id, $this->petugas->fresh()->assignedFarmId());
    }

    public function test_identity_edits_and_assignment_edits_follow_their_separate_matrix_grants(): void
    {
        $roles = app(RolePermissions::class)->roles();
        unset($roles['admin']['users.configure']);
        config(['access.roles' => $roles]);
        $this->get('/admin/user-management/'.$this->petugas->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.configure', false));
        $this->put('/admin/user-management/'.$this->petugas->id, $this->payload([
            'email' => $this->petugas->email, 'name' => 'Profil Masih Bisa Diubah',
        ]))->assertRedirect();
        $this->put('/admin/user-management/'.$this->petugas->id, $this->payload([
            'email' => $this->petugas->email, 'assigned_farm_id' => $this->farm->id,
        ]))->assertForbidden();
        unset($roles['admin']['users.create'], $roles['admin']['users.delete']);
        config(['access.roles' => $roles]);
        $this->get('/admin/user-management/create')->assertForbidden();
        $this->post('/admin/user-management', [])->assertForbidden();
        $this->delete('/admin/user-management/'.$this->petugas->id)->assertForbidden();
        unset($roles['admin']['users.view']);
        config(['access.roles' => $roles]);
        $this->get('/admin/user-management')->assertForbidden();
        $this->assertSame(0, User::visibleTo($this->admin, $this->tenant)->count());
    }

    public function test_farm_and_multitenant_corruption_do_not_leak_foreign_data(): void
    {
        $this->petugas->forceFill(['assigned_farm_id' => $this->foreignFarm->id])->save();
        $this->get('/admin/user-management/'.$this->petugas->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('managedUser.assigned_farm', null)->where('managedUser.assigned_farm_id', null)
            ->where('managedUser.team_ids', [$this->tenant->id]));
        $this->otherTenant->members()->attach($this->petugas, ['role' => TeamRole::Member->value]);
        $this->get('/admin/user-management/'.$this->petugas->id)->assertForbidden();
        $this->get('/admin/user-management')->assertOk()->assertInertia(fn (Assert $page) => $page->where('users.total', 2));
    }

    public function test_membership_context_cannot_be_forged_and_admin_without_one_tenant_is_denied(): void
    {
        $this->admin->forceFill(['current_team_id' => $this->otherTenant->id])->save();
        $this->get('/admin/user-management')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('userManagementScope.team.id', $this->tenant->id)->where('users.total', 3));
        $this->otherTenant->members()->attach($this->admin, ['role' => TeamRole::Member->value]);
        $this->get('/admin/user-management')->assertForbidden();
        $this->otherTenant->members()->detach($this->admin);
        $this->tenant->members()->detach($this->admin);
        $this->get('/admin/user-management')->assertForbidden();
        $this->actingAs($this->owner)->get('/admin/user-management')->assertOk();
        $this->actingAs($this->petugas)->get('/admin/user-management')->assertForbidden();
    }

    public function test_owner_can_read_users_across_associated_tenants_without_leaking_other_tenants(): void
    {
        $this->otherTenant->members()->attach($this->owner, ['role' => TeamRole::Member->value]);
        $outside = Team::factory()->create(['name' => 'Tenant Rahasia', 'is_personal' => false]);
        $outsideFarm = $this->farm($outside, 'Kebun Rahasia Lain');
        $outsider = $this->actor('petugas', $outside, 'User Rahasia Lain');
        $outsider->forceFill(['assigned_farm_id' => $outsideFarm->id])->save();
        $shared = $this->actor('petugas', $this->tenant, 'User Tenant Bersama');
        $outside->members()->attach($shared, ['role' => TeamRole::Member->value]);
        $shared->forceFill(['assigned_farm_id' => $outsideFarm->id])->save();
        $this->petugas->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->foreign->forceFill(['assigned_farm_id' => $this->foreignFarm->id])->save();
        $this->owner->forceFill(['current_team_id' => null])->save();
        $this->owner->unsetRelation('currentTeam');
        $this->actingAs($this->owner);

        $this->get('/admin/user-management')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('users/Index')->where('navigation.users', true)->where('userManagementScope.global', false)
            ->where('userManagementScope.multiTenant', true)->where('users.total', 6)->where('summary.total', 6)
            ->has('options.teams', 2)->has('options.farms', 2)->has('options.roles', 3)->where('can.create', false)
            ->where('users.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['id'] !== $outsider->id
                && ! $row['can']['update'] && ! $row['can']['delete'])));
        $this->get('/admin/user-management?search=User%20Rahasia%20Lain')->assertOk()->assertInertia(fn (Assert $page) => $page->where('users.total', 0));
        $this->get('/admin/user-management?team_id='.$this->otherTenant->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 2));
        $this->get('/admin/user-management?farm_id='.$this->foreignFarm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 1)->where('users.data.0.id', $this->foreign->id));
        $this->get('/admin/user-management?team_id='.$outside->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('users.total', 0));
        $this->get('/admin/user-management?team_id=2147483647')->assertOk()->assertInertia(fn (Assert $page) => $page->where('users.total', 0));
        $this->get('/admin/user-management?farm_id='.$outsideFarm->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('users.total', 0));
        $this->get('/admin/user-management/'.$shared->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('managedUser.team_ids', [$this->tenant->id])->where('managedUser.assigned_farm', null)
            ->where('managedUser.assigned_farm_id', null)->where('can.update', false)->where('can.delete', false));
        $this->get('/admin/user-management/'.$outsider->id)->assertForbidden();
        $personalOnly = User::factory()->create(['role' => 'petugas']);
        $this->owner->teams()->where('teams.is_personal', true)->firstOrFail()
            ->members()->attach($personalOnly, ['role' => TeamRole::Member->value]);
        $this->assertFalse(Gate::forUser($this->owner)->allows('view', [$personalOnly, true]));
        $this->get('/admin/user-management/'.$personalOnly->id)->assertForbidden();
        $this->assertFalse(Gate::forUser($this->owner)->allows('access.global'));
        $this->assertSame(0, User::visibleTo($this->owner)->count());
    }

    public function test_owner_cannot_see_superadmins_even_when_they_share_a_tenant(): void
    {
        $this->actingAs($this->owner);
        $this->get('/admin/user-management')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 4)->where('summary.superadmin', 0)->where('users.total', 4)
            ->has('options.roles', 3)
            ->where('options.roles', fn ($roles) => collect($roles)->every(fn ($role) => $role['value'] !== 'superadmin'))
            ->where('users.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['id'] !== $this->developer->id)));
        $this->get('/admin/user-management?role=superadmin')->assertOk()->assertInertia(fn (Assert $page) => $page->where('users.total', 0));
        $this->get('/admin/user-management/'.$this->developer->id)->assertForbidden();
        $this->assertFalse(Gate::forUser($this->owner)->allows('view', $this->developer));
        $this->assertFalse(User::visibleTo($this->owner, $this->tenant)->whereKey($this->developer->id)->exists());
        $this->actingAs($this->developer)->get('/admin/user-management')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('options.roles', 4)
            ->where('users.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['id'] === $this->developer->id)));
    }

    public function test_owner_user_management_is_read_only_and_requires_a_grant_and_tenant_membership(): void
    {
        $this->actingAs($this->owner);
        $this->get('/admin/user-management/create')->assertForbidden();
        $this->post('/admin/user-management', $this->payload())->assertForbidden();
        $this->get('/admin/user-management/'.$this->petugas->id.'/edit')->assertForbidden();
        $this->put('/admin/user-management/'.$this->petugas->id, $this->payload(['email' => $this->petugas->email]))->assertForbidden();
        $this->delete('/admin/user-management/'.$this->petugas->id)->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $this->petugas->id]);
        $this->get('/admin/farm-management')->assertForbidden();
        $roles = app(RolePermissions::class)->roles();
        unset($roles['owner']['users.view']);
        config(['access.roles' => $roles]);
        $this->get('/admin/user-management')->assertForbidden();
        $this->assertFalse(Gate::forUser($this->owner)->allows('users.viewAcrossTeams'));
        $this->assertFalse(Gate::forUser($this->owner)->allows('viewAny', [User::class, null]));
        $roles['owner']['users.view'] = 'any';
        config(['access.roles' => $roles]);
        $this->tenant->members()->detach($this->owner);
        $this->get('/admin/user-management')->assertForbidden();
    }

    public function test_admin_can_edit_own_profile_but_cannot_demote_or_delete_itself(): void
    {
        $this->put('/admin/user-management/'.$this->admin->id, $this->payload([
            'email' => $this->admin->email, 'name' => 'Admin Terkini', 'role' => 'admin',
        ]))->assertRedirect();
        $this->assertSame('Admin Terkini', $this->admin->fresh()->name);
        $this->put('/admin/user-management/'.$this->admin->id, $this->payload([
            'email' => $this->admin->email, 'role' => 'petugas',
        ]))->assertForbidden();
        $this->delete('/admin/user-management/'.$this->admin->id)->assertSessionHasErrors('delete');
    }

    public function test_existing_roles_are_immutable_for_tenant_admins_even_with_configure_permission(): void
    {
        foreach ([[$this->petugas, 'admin'], [$this->colleague, 'petugas']] as [$target, $role]) {
            $this->get('/admin/user-management/'.$target->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('can.configure', true)->where('can.changeRole', false)->has('options.roles', 1));
            $this->put('/admin/user-management/'.$target->id, $this->payload([
                'email' => $target->email, 'role' => $role,
            ]))->assertForbidden();
            $this->assertSame($target->role_id, $target->fresh()->role_id);
            $desired = Role::where('name', $role)->firstOrFail();
            $this->assertFalse(Gate::forUser($this->admin)->allows('configure', [$target, $desired]));
        }

        // A role changed concurrently must not be overwritten by an already-authorized edit.
        $stale = $this->petugas->fresh();
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        User::whereKey($stale->id)->update(['role_id' => $adminRole->id]);
        try {
            app(UserManagementService::class)->save($this->admin, $this->payload([
                'email' => $stale->email,
            ]), $stale, $this->tenant);
            $this->fail('A concurrent role change must not be overwritten.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }
        $this->assertSame($adminRole->id, $stale->fresh()->role_id);
    }

    private function actor(string $role, Team $team, string $name): User
    {
        $user = User::factory()->create(['role' => $role, 'name' => $name]);
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $user->switchTeam($team);

        return $user;
    }

    private function farm(Team $team, string $name): Farm
    {
        $farm = new Farm(['name' => $name, 'total_area_ha' => 5]);
        $farm->forceFill(['created_by' => $this->developer->id, 'team_id' => $team->id])->save();

        return $farm;
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'User Tenant Pertiwi', 'email' => 'user.baru@example.com',
            'password' => 'password-user-123', 'password_confirmation' => 'password-user-123',
            'role' => 'petugas', 'team_ids' => [$this->tenant->id], 'assigned_farm_id' => null,
        ], $overrides);
    }
}
