<?php

namespace Tests\Feature\Authorization;

use App\Contracts\KebunAssignments;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Support\Access\ResourceAccess;
use App\Support\Access\UnconfiguredKebunAssignments;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\InMemoryKebunAssignments;
use Tests\TestCase;

class StaticAccessTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryKebunAssignments $assignments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assignments = new InMemoryKebunAssignments;
        $this->app->instance(KebunAssignments::class, $this->assignments);
    }

    /** @param list<string> $allowed */
    #[DataProvider('roleGrants')]
    public function test_every_resource_action_obeys_the_role_matrix(string $role, array $allowed): void
    {
        $user = User::factory()->create(['role' => $role]);
        $team = $user->currentTeam;
        $this->assignments->assign($user, 'kebun-cianjur');

        $resources = [
            'kebun', 'lahan', 'activities', 'plantings', 'plantings.progress',
            'harvests', 'finance.sales', 'finance.purchases', 'users', 'sops', 'forms',
        ];

        foreach ($resources as $resource) {
            $subject = new ResourceAccess($resource, $team, 'kebun-cianjur');

            foreach (['view', 'create', 'update', 'delete', 'configure'] as $action) {
                $permission = $resource.'.'.$action;
                $this->assertSame(
                    in_array($permission, $allowed, true),
                    Gate::forUser($user)->allows($action, $subject),
                    $role.' '.$permission,
                );
            }

            $this->assertSame(
                in_array($resource.'.view', $allowed, true),
                Gate::forUser($user)->allows('viewAny', new ResourceAccess($resource, $team)),
            );
        }

        $this->assertSame($role === 'owner', Gate::forUser($user)->allows('ai.access', $team));
        $this->assertFalse(Gate::forUser($user)->allows('ai.access'));
        $this->assertFalse(Gate::forUser($user)->allows('undefined.ability'));
        $this->assertFalse(Gate::forUser($user)->allows('view', new ResourceAccess('unknown', $team)));
        $this->assertFalse(Gate::forUser($user)->allows('forceDelete', new ResourceAccess('kebun', $team)));
    }

    /** @return array<string, array{string, list<string>}> */
    public static function roleGrants(): array
    {
        return [
            'owner' => ['owner', [
                'kebun.view', 'lahan.view', 'activities.view', 'plantings.view',
                'plantings.progress.view', 'harvests.view', 'finance.sales.view',
                'finance.purchases.view', 'users.view', 'sops.view', 'forms.view',
            ]],
            'superadmin' => ['superadmin', [
                'users.view', 'users.create', 'users.update', 'users.delete',
                'kebun.view', 'kebun.create', 'kebun.update', 'kebun.delete',
                'lahan.view', 'lahan.create', 'lahan.update', 'lahan.delete',
                'sops.view', 'sops.create', 'sops.update', 'sops.delete',
                'forms.view', 'forms.configure',
            ]],
            'admin' => ['admin', [
                'kebun.view', 'lahan.view', 'finance.sales.view', 'finance.sales.create',
                'finance.purchases.view', 'finance.purchases.create',
            ]],
            'petugas' => ['petugas', [
                'kebun.view', 'lahan.view', 'activities.view', 'activities.create',
                'plantings.view', 'plantings.create', 'plantings.update',
                'plantings.progress.view', 'plantings.progress.create',
                'plantings.progress.update', 'harvests.view', 'harvests.create',
            ]],
        ];
    }

    #[DataProvider('roles')]
    public function test_no_role_can_cross_the_team_boundary(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $otherTeam = Team::factory()->create();
        $this->assignments->assign($user, 'kebun-cianjur');
        $subject = new ResourceAccess('kebun', $otherTeam, 'kebun-cianjur');

        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $action) {
            $this->assertFalse(Gate::forUser($user)->allows($action, $subject));
        }

        $this->assertFalse(Gate::forUser($user)->allows('ai.access', $otherTeam));
    }

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return [
            'owner' => ['owner'], 'superadmin' => ['superadmin'],
            'admin' => ['admin'], 'petugas' => ['petugas'],
        ];
    }

    public function test_any_scope_covers_unassigned_kebun_within_the_team(): void
    {
        foreach (['owner', 'superadmin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $subject = new ResourceAccess('kebun', $user->currentTeam, 'unassigned-kebun');

            $this->assertTrue(Gate::forUser($user)->allows('view', $subject));
            $this->assertSame($role === 'superadmin', Gate::forUser($user)->allows('update', $subject));
        }
    }

    public function test_admin_can_access_multiple_assignments_but_not_other_kebun(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->assignments->assign($user, 'kebun-cianjur', 'kebun-lembang');

        foreach (['finance.sales', 'finance.purchases'] as $resource) {
            foreach (['kebun-cianjur', 'kebun-lembang', 'unassigned-kebun', null] as $id) {
                $subject = new ResourceAccess($resource, $user->currentTeam, $id);
                $expected = in_array($id, ['kebun-cianjur', 'kebun-lembang'], true);

                $this->assertSame($expected, Gate::forUser($user)->allows('view', $subject));
                $this->assertSame($expected, Gate::forUser($user)->allows('create', $subject));
            }
        }
    }

    public function test_petugas_is_limited_to_one_assigned_kebun_for_reads_and_writes(): void
    {
        $user = User::factory()->create(['role' => 'petugas']);
        $subject = new ResourceAccess('plantings', $user->currentTeam, 'kebun-cianjur');

        foreach ([[], ['kebun-lembang'], ['kebun-cianjur'], ['kebun-cianjur', 'kebun-lembang']] as $ids) {
            $this->assignments->assign($user, ...$ids);

            foreach (['view', 'create', 'update'] as $action) {
                $this->assertSame($ids === ['kebun-cianjur'], Gate::forUser($user)->allows($action, $subject));
            }
        }

        $this->assignments->assign($user, 'kebun-cianjur');
        $this->assertFalse(Gate::forUser($user)->allows('create', new ResourceAccess('plantings', $user->currentTeam)));
    }

    public function test_default_assignment_adapter_denies_own_records_until_integration(): void
    {
        $this->app->bind(KebunAssignments::class, UnconfiguredKebunAssignments::class);
        $user = User::factory()->create(['role' => 'petugas']);
        $subject = new ResourceAccess('activities', $user->currentTeam, 'kebun-cianjur');

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', $subject));
        $this->assertFalse(Gate::forUser($user)->allows('view', $subject));
        $this->assertFalse(Gate::forUser($user)->allows('create', $subject));
    }

    public function test_role_is_nullable_persisted_and_protected_from_mass_assignment(): void
    {
        $user = User::factory()->create();
        $this->assertNull($user->fresh()->role);

        $user->fill(['name' => 'Dewi Lestari', 'role' => 'owner']);
        $user->save();
        $this->assertNull($user->fresh()->role);

        // Deliberate backend assignment, never copied from public input.
        $user->role = 'admin';
        $user->save();
        $this->assertSame('admin', $user->fresh()->role);
    }

    public function test_missing_unknown_and_team_roles_never_grant_domain_access(): void
    {
        $user = User::factory()->create();
        $permissions = app(RolePermissions::class);

        // The factory grants TeamRole::Owner, which must not grant domain owner.
        $this->assertSame(TeamRole::Owner, $user->teamRole($user->currentTeam));

        foreach ([null, '', 'unknown', 'Owner', ' owner'] as $role) {
            $user->role = $role;
            $this->assertNull($permissions->scope($user, 'kebun.view'));
            $this->assertFalse(Gate::forUser($user)->allows('ai.access', $user->currentTeam));
            $this->assertFalse(Gate::forUser($user)->allows('view', new ResourceAccess('kebun', $user->currentTeam)));
        }
    }

    public function test_malformed_config_and_invalid_scopes_deny_access(): void
    {
        $user = User::factory()->create(['role' => 'owner']);
        $permissions = app(RolePermissions::class);

        foreach (['all', '', true, 1, [], null] as $scope) {
            $this->setGrant('owner', 'kebun.view', $scope);
            $this->assertNull($permissions->scope($user, 'kebun.view'));
        }

        foreach ([null, 'owner', true, ['owner' => 'any']] as $roles) {
            config(['access.roles' => $roles]);
            $this->assertNull($permissions->scope($user, 'kebun.view'));
        }
    }

    public function test_config_changes_take_effect_without_a_separate_grant_list(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $subject = new ResourceAccess('finance.sales', $user->currentTeam, 'unassigned-kebun');

        $this->assertFalse(Gate::forUser($user)->allows('create', $subject));
        $this->setGrant('admin', 'finance.sales.create', 'any');
        $this->assertTrue(Gate::forUser($user)->allows('create', $subject));
        $this->setGrant('admin', 'finance.sales.create', null);
        $this->assertFalse(Gate::forUser($user)->allows('create', $subject));

        $owner = User::factory()->create(['role' => 'owner']);
        $this->assertTrue(Gate::forUser($owner)->allows('ai.access', $owner->currentTeam));
        $this->setGrant('owner', 'ai.access', null);
        $this->assertFalse(Gate::forUser($owner)->allows('ai.access', $owner->currentTeam));
    }

    public function test_mutations_require_record_visibility_as_well_as_the_mutation_grant(): void
    {
        $user = User::factory()->create(['role' => 'petugas']);
        $this->assignments->assign($user, 'kebun-cianjur');
        $this->setGrant('petugas', 'plantings.update', 'any');

        $this->assertFalse(Gate::forUser($user)->allows(
            'update', new ResourceAccess('plantings', $user->currentTeam, 'kebun-lembang'),
        ));
    }

    public function test_stale_team_membership_and_deleted_teams_deny_access(): void
    {
        $user = User::factory()->create(['role' => 'owner']);
        $team = $user->currentTeam;
        $subject = new ResourceAccess('kebun', $team);
        $this->assertTrue(Gate::forUser($user)->allows('view', $subject));

        $team->members()->detach($user);
        $this->assertFalse(Gate::forUser($user)->allows('view', $subject));

        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $team->delete();
        $this->assertFalse(Gate::forUser($user)->allows('view', $subject));
        $this->assertFalse(Gate::forUser($user)->allows('ai.access', $team));
    }

    public function test_guests_cannot_use_the_gate_or_resource_policy(): void
    {
        $team = Team::factory()->create();
        $this->assertFalse(Gate::forUser(null)->allows('ai.access', $team));
        $this->assertFalse(Gate::forUser(null)->allows('view', new ResourceAccess('kebun', $team)));
    }

    public function test_gate_authorize_returns_403_before_the_action_runs(): void
    {
        $user = User::factory()->create(['role' => 'petugas']);
        $this->assignments->assign($user, 'kebun-cianjur');
        $subject = new ResourceAccess('activities', $user->currentTeam, 'kebun-lembang');
        $executed = false;

        // Test-only route: application routes are deliberately not wired yet.
        Route::post('/__tests/static-access', function () use ($subject, &$executed) {
            Gate::authorize('create', $subject);
            $executed = true;

            return response()->noContent();
        })->middleware('auth');

        $this->actingAs($user)->post('/__tests/static-access', ['role' => 'owner'])->assertForbidden();
        $this->assertFalse($executed);

        $this->assignments->assign($user, 'kebun-lembang');
        $this->actingAs($user)->post('/__tests/static-access')->assertNoContent();
        $this->assertTrue($executed);
    }

    private function setGrant(string $role, string $permission, mixed $scope): void
    {
        $roles = config('access.roles');
        $roles[$role][$permission] = $scope;
        config(['access.roles' => $roles]);
    }
}
