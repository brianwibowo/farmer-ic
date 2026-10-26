<?php

namespace Tests\Feature\Authorization;

use App\Enums\TeamRole;
use App\Models\AiConversation;
use App\Models\AiKnowledgeDocument;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Policies\ResourcePolicy;
use App\Support\RolePermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

class ModelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Model> */
    private array $records = [];

    /** @var list<string> */
    private const MASTER = ['farms', 'activity_types', 'commodities', 'crop_varieties', 'financial_categories', 'users', 'teams', 'memberships', 'team_invitations'];

    /** @var list<string> */
    private const OPERATIONS = ['farm_activities', 'activity_photos', 'planting_cycles', 'planting_details', 'fertilization_details', 'irrigation_details', 'pesticide_details', 'incident_details', 'harvest_details', 'plant_observations', 'weather_records', 'farm_risks', 'operational_targets'];

    protected function setUp(): void
    {
        parent::setUp();

        // Focused relational fixtures; never migrate the isolated PostgreSQL baseline.
        foreach ($this->models() as $class) {
            $model = new $class;
            if (Schema::hasTable($model->getTable())) {
                continue;
            }
            Schema::create($model->getTable(), function (Blueprint $table) use ($model): void {
                $table->string($model->getKeyName())->primary();
                $columns = array_unique([...$model->getFillable(), 'created_at', 'updated_at', 'created_by', 'user_id']);
                foreach ($columns as $column) {
                    if ($column !== $model->getKeyName()) {
                        $table->string($column)->nullable();
                    }
                }
            });
        }

        // SQLite policy fixtures only. Do not execute the deferred authorization migration.
        Schema::table('farms', function (Blueprint $table): void {
            $table->foreignId('team_id')->nullable()->constrained('teams');
        });
        Schema::create('farm_user', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->unique(['user_id', 'farm_id']);
        });
    }

    /** @return list<class-string<Model>> */
    private function models(): array
    {
        $models = [];
        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');
            if (is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $models[] = $class;
            }
        }
        $models[] = \App\Models\Farmer\User::class;

        return $models;
    }

    private function actor(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function farm(Team $team): Farm
    {
        $id = (string) Str::uuid();
        DB::table('farms')->insert(['id' => $id, 'name' => 'Kebun Cianjur '.$id, 'team_id' => $team->id]);

        return Farm::findOrFail($id);
    }

    /** @param class-string<Model> $class */
    private function record(string $class, User $user, Team $team, Farm $farm): Model
    {
        $key = $farm->id.$class;
        if (isset($this->records[$key])) {
            return $this->records[$key];
        }
        $name = class_basename($class);
        $record = match ($name) {
            'User' => $class::findOrFail($user->id),
            'Team' => $team,
            'Farm' => $farm,
            'Membership' => $team->memberships()->where('user_id', $user->id)->firstOrFail(),
            default => null,
        };
        if ($record !== null) {
            return $this->records[$key] = $record;
        }
        $model = new $class;
        $attributes = [$model->getKeyName() => (string) Str::uuid()];
        if (Str::startsWith($name, 'Ai') || $name === 'TeamInvitation') {
            $attributes = [];
        }
        $attributes += match ($name) {
            'FarmActivity', 'PlantingCycle', 'WeatherRecord', 'FinancialBudget', 'FinancialProjection', 'FinancialTransaction', 'OperationalTarget' => ['farm_id' => $farm->id],
            'FarmRisk', 'PlantObservation' => ['planting_cycle_id' => $this->record(PlantingCycle::class, $user, $team, $farm)->getKey()],
            'ActivityPhoto', 'PlantingDetail', 'FertilizationDetail', 'IrrigationDetail', 'PesticideDetail', 'IncidentDetail', 'HarvestDetail' => ['activity_id' => $this->record(FarmActivity::class, $user, $team, $farm)->getKey()],
            'Notification', 'AuditLog' => ['user_id' => $user->id],
            'AiConversation' => ['team_id' => $team->id, 'user_id' => $user->id, 'status' => 'active'],
            'AiMessage' => ['ai_conversation_id' => $this->record(AiConversation::class, $user, $team, $farm)->getKey(), 'role' => 'user', 'user_id' => $user->id],
            'AiAttachment' => ['ai_conversation_id' => $this->record(AiConversation::class, $user, $team, $farm)->getKey(), 'user_id' => $user->id, 'disk' => 'local', 'path' => 'ai/seed.png', 'original_name' => 'seed.png', 'mime_type' => 'image/png', 'size' => 16, 'sha256' => hash('sha256', $farm->id)],
            'AiKnowledgeDocument' => ['team_id' => $team->id, 'created_by' => $user->id, 'title' => 'Pedoman kebun', 'content' => 'Pedoman pemeliharaan tanaman', 'content_hash' => hash('sha256', $farm->id)],
            'AiKnowledgeChunk' => ['team_id' => $team->id, 'ai_knowledge_document_id' => $this->record(AiKnowledgeDocument::class, $user, $team, $farm)->getKey(), 'chunk_index' => 0, 'content' => 'Pedoman pemeliharaan', 'content_hash' => hash('sha256', $farm->id), 'chunking_version' => 'v1'],
            'AiUsageEvent' => ['team_id' => $team->id, 'user_id' => $user->id, 'event_type' => 'completion'],
            'TeamInvitation' => ['team_id' => $team->id, 'invited_by' => $user->id, 'code' => Str::random(64), 'email' => Str::uuid().'@example.com', 'role' => TeamRole::Member->value],
            default => [],
        };
        if ($model->getKeyName() === 'activity_id') {
            $attributes['activity_id'] = $this->record(FarmActivity::class, $user, $team, $farm)->getKey();
        }
        if ($model->getIncrementing()) {
            $id = DB::table($model->getTable())->insertGetId($attributes);
        } else {
            DB::table($model->getTable())->insert($attributes);
            $id = $attributes[$model->getKeyName()];
        }

        return $this->records[$key] = $class::findOrFail($id);
    }

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return ['owner' => ['owner'], 'superadmin' => ['superadmin'], 'admin' => ['admin'], 'petugas' => ['petugas']];
    }

    #[DataProvider('roles')]
    public function test_all_models_have_native_policies_and_obey_the_role_matrix(string $role): void
    {
        $user = $this->actor($role);
        $team = $user->currentTeam;
        $team->update(['is_personal' => false]);
        $farm = $this->farm($team);
        $user->farms()->attach($farm);

        foreach ($this->models() as $class) {
            $record = $this->record($class, $user, $team, $farm);
            $prefix = class_basename($class) === 'Membership' ? 'memberships' : $record->getTable();
            $policy = Gate::getPolicyFor($class);
            $this->assertInstanceOf(ResourcePolicy::class, $policy, $class);
            $view = match ($role) {
                'owner' => true,
                'superadmin' => in_array($prefix, [...self::MASTER, 'roles', 'audit_logs', 'notifications'], true),
                'admin' => in_array($prefix, ['farms', 'activity_types', 'commodities', 'crop_varieties', 'financial_categories', 'teams', 'notifications', 'financial_transactions', 'financial_budgets', 'financial_projections'], true),
                'petugas' => in_array($prefix, [...self::OPERATIONS, 'farms', 'activity_types', 'commodities', 'crop_varieties', 'financial_categories', 'teams', 'notifications'], true),
            };
            $unlinked = $class === \App\Models\Farmer\User::class || $prefix === 'notifications';
            if ($unlinked) {
                $view = false;
            }
            $this->assertSame($view, Gate::forUser($user)->allows('view', $record), $role.' '.$prefix.'.view');
            $this->assertSame($view, Gate::forUser($user)->allows('viewAny', [$class, $team]), $role.' '.$prefix.'.viewAny');
            $this->assertSame($view, $class::visibleTo($user, $team)->whereKey($record->getKey())->exists(), $role.' '.$prefix.'.visibleTo');

            $create = match ($role) {
                'owner' => in_array($prefix, ['ai_conversations', 'ai_messages', 'ai_attachments', 'ai_knowledge_documents'], true),
                'superadmin' => in_array($prefix, self::MASTER, true),
                'admin' => in_array($prefix, ['financial_transactions', 'financial_projections', 'financial_budgets'], true),
                'petugas' => in_array($prefix, self::OPERATIONS, true),
            };
            $create = $create && ! $unlinked;
            $parent = match (class_basename($class)) {
                'FarmActivity', 'PlantingCycle', 'WeatherRecord', 'FinancialTransaction', 'FinancialBudget', 'FinancialProjection', 'OperationalTarget' => $farm,
                'PlantObservation', 'FarmRisk' => $this->record(PlantingCycle::class, $user, $team, $farm),
                'ActivityPhoto', 'PlantingDetail', 'FertilizationDetail', 'IrrigationDetail', 'PesticideDetail', 'IncidentDetail', 'HarvestDetail' => $this->record(FarmActivity::class, $user, $team, $farm),
                'CropVariety' => $this->record(Commodity::class, $user, $team, $farm),
                'AiMessage', 'AiAttachment' => $this->record(AiConversation::class, $user, $team, $farm),
                'AiKnowledgeChunk' => $this->record(AiKnowledgeDocument::class, $user, $team, $farm),
                default => $team,
            };
            $this->assertSame($create, Gate::forUser($user)->allows('create', [$class, $parent]), $role.' '.$prefix.'.create');
            $update = ($role === 'superadmin' && in_array($prefix, self::MASTER, true))
                || ($role === 'owner' && $create)
                || ($role === 'petugas' && in_array($prefix, ['planting_cycles', 'plant_observations', 'planting_details', 'notifications'], true))
                || ($role === 'admin' && $prefix === 'notifications');
            $delete = ($role === 'superadmin' && in_array($prefix, self::MASTER, true)) || ($role === 'owner' && $create);
            $update = $update && ! $unlinked;
            $delete = $delete && ! $unlinked;
            $this->assertSame($update, Gate::forUser($user)->allows('update', $record), $role.' '.$prefix.'.update');
            $this->assertSame($delete, Gate::forUser($user)->allows('delete', $record), $role.' '.$prefix.'.delete');
            $this->assertFalse(Gate::forUser($user)->allows('configure', $record));
            $this->assertFalse(Gate::forUser($user)->allows('forceDelete', $record));
        }
        $this->assertSame($role === 'owner', Gate::forUser($user)->allows('ai.access', $team));
        $this->assertFalse(Gate::forUser($user)->allows('ai.access'));
    }

    public function test_config_has_exact_roles_known_models_abilities_and_scopes(): void
    {
        $roles = app(RolePermissions::class)->roles();
        $this->assertSame(['owner', 'superadmin', 'admin', 'petugas'], array_keys($roles));
        $prefixes = [];
        foreach ($this->models() as $class) {
            $prefixes[] = class_basename($class) === 'Membership' ? 'memberships' : (new $class)->getTable();
        }
        foreach ($roles as $role => $grants) {
            foreach ($grants as $permission => $scope) {
                if ($permission !== 'ai.access') {
                    [$prefix, $ability] = explode('.', $permission);
                    $this->assertContains($prefix, $prefixes);
                    $this->assertContains($ability, ['view', 'create', 'update', 'delete', 'configure', 'leave']);
                } else {
                    $this->assertSame('owner', $role);
                }
                $this->assertContains($scope, ['any', 'own']);
            }
        }
        $this->assertSame([], Gate::policies());
        $this->assertTrue(Gate::has('ai.access'));
    }

    public function test_scope_filters_totals_counts_exports_and_cross_team_records(): void
    {
        $admin = $this->actor('admin');
        $team = $admin->currentTeam;
        $first = $this->farm($team);
        $second = $this->farm($team);
        $unassigned = $this->farm($team);
        $foreign = $this->farm(Team::factory()->create());
        $admin->farms()->attach([$first->id, $second->id, $foreign->id]);
        $this->assertSame(2, Farm::visibleTo($admin, $team)->count());
        $this->assertSame(2, Farm::visibleTo($admin, $team)->paginate(1)->total());
        $this->assertEqualsCanonicalizing([$first->id, $second->id], Farm::visibleTo($admin, $team)->pluck('id')->all());
        $this->assertFalse(Gate::forUser($admin)->allows('view', $unassigned));
        $this->assertFalse(Gate::forUser($admin)->allows('view', $foreign));

        $owner = $this->actor('owner');
        $team->members()->attach($owner, ['role' => TeamRole::Member->value]);
        $this->assertSame(3, Farm::visibleTo($owner, $team)->count());
        $this->assertFalse(Gate::forUser($owner)->allows('view', $foreign));
        $this->assertFalse(Gate::forUser($owner)->allows('ai.access', $foreign->team));
    }

    public function test_each_model_preserves_its_persisted_team_or_global_boundary(): void
    {
        $owner = $this->actor('owner');
        $otherOwner = $this->actor('owner');
        $team = $otherOwner->currentTeam;
        $farm = $this->farm($team);
        $global = ['activity_types', 'commodities', 'crop_varieties', 'financial_categories', 'roles', 'audit_logs'];

        foreach ($this->models() as $class) {
            $record = $this->record($class, $otherOwner, $team, $farm);
            $this->assertSame(in_array($record->getTable(), $global, true), Gate::forUser($owner)->allows('view', $record), $class);
            $this->assertFalse(Gate::forUser($owner)->allows('viewAny', [$class, $team]), $class);
            $this->assertSame(0, $class::visibleTo($owner, $team)->count(), $class);
        }
    }

    public function test_petugas_assignment_limit_parent_checks_and_assignment_queries_are_cached(): void
    {
        $user = $this->actor('petugas');
        $farm = $this->farm($user->currentTeam);
        $other = $this->farm(Team::factory()->create());
        $planting = $this->record(PlantingCycle::class, $user, $user->currentTeam, $farm);
        $this->assertFalse(Gate::forUser($user)->allows('view', $planting));
        $user->unsetRelation('farms');
        $user->farms()->attach($farm);
        $this->assertTrue(Gate::forUser($user)->allows('view', $planting));
        DB::flushQueryLog();
        DB::enableQueryLog();
        for ($index = 0; $index < 3; $index++) {
            $this->assertTrue(Gate::forUser($user)->allows('update', $planting));
        }
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(0, array_filter($queries, fn ($query) => str_contains($query['query'], 'farm_user')));
        $this->assertFalse(Gate::forUser($user)->allows('create', [PlantingCycle::class, $other]));
        $this->assertFalse(Gate::forUser($user)->allows('create', PlantingCycle::class));
        $user->farms()->attach($other);
        $user->unsetRelation('farms');
        $this->assertFalse(Gate::forUser($user)->allows('view', $planting));
        $this->assertSame(0, PlantingCycle::visibleTo($user, $user->currentTeam)->count());
    }

    public function test_real_403_ignores_forged_scope_role_and_user_input(): void
    {
        $user = $this->actor('petugas');
        $allowed = $this->farm($user->currentTeam);
        $other = $this->farm($user->currentTeam);
        $user->farms()->attach($allowed);
        $planting = $this->record(PlantingCycle::class, $user, $user->currentTeam, $other);
        $executed = false;
        Route::post('/__tests/plantings', function () use ($planting, &$executed) {
            Gate::authorize('update', $planting);
            $executed = true;

            return response()->noContent();
        })->middleware('auth');
        $this->actingAs($user)->post('/__tests/plantings', ['farm_id' => $allowed->id, 'team_id' => $user->current_team_id, 'role' => 'owner', 'user_id' => $user->id])->assertForbidden();
        $this->assertFalse($executed);
        $user->farms()->sync([$other->id]);
        $user->unsetRelation('farms');
        $this->post('/__tests/plantings')->assertNoContent();
        $this->assertTrue($executed);
    }

    public function test_invalid_grants_missing_roles_guests_and_revoked_memberships_deny(): void
    {
        $user = $this->actor('owner');
        $farm = $this->farm($user->currentTeam);
        foreach ([null, '', 'unknown'] as $role) {
            $user->role = $role;
            $this->assertFalse(Gate::forUser($user)->allows('view', $farm));
            $this->assertSame(0, Farm::visibleTo($user, $user->currentTeam)->count());
        }
        $user->role = 'owner';
        $roles = app(RolePermissions::class)->roles();
        $roles['owner']['farms.view'] = 'invalid';
        config(['access.roles' => $roles]);
        $this->assertFalse(Gate::forUser($user)->allows('view', $farm));
        $this->assertFalse(Gate::forUser(null)->allows('view', $farm));
        $this->assertFalse(Gate::forUser(null)->allows('ai.access', $user->currentTeam));
        $roles['owner']['farms.view'] = 'any';
        config(['access.roles' => $roles]);
        $this->assertTrue(Gate::forUser($user)->allows('view', $farm));
        $farm->team->members()->detach($user);
        $this->assertFalse(Gate::forUser($user)->allows('view', $farm));
    }

    public function test_visibility_is_required_for_writes_and_roles_stay_guarded(): void
    {
        $user = $this->actor('petugas');
        $farm = $this->farm($user->currentTeam);
        $roles = app(RolePermissions::class)->roles();
        $roles['petugas']['planting_cycles.update'] = 'any';
        config(['access.roles' => $roles]);
        $planting = $this->record(PlantingCycle::class, $user, $user->currentTeam, $farm);
        $this->assertFalse(Gate::forUser($user)->allows('update', $planting));
        $user->fill(['role' => 'owner'])->save();
        $this->assertSame('petugas', $user->fresh()->role);
        $fresh = User::factory()->create();
        $this->assertNull($fresh->role);
        $this->assertFalse(Gate::forUser($fresh)->allows('ai.access', $fresh->currentTeam));
    }

    public function test_knowledge_routes_use_the_specific_policy_ability_and_filter_the_list(): void
    {
        $owner = $this->actor('owner');
        $team = $owner->currentTeam;
        $farm = $this->farm($team);
        $document = $this->record(AiKnowledgeDocument::class, $owner, $team, $farm);
        $otherOwner = $this->actor('owner');
        $this->record(AiKnowledgeDocument::class, $otherOwner, $otherOwner->currentTeam, $this->farm($otherOwner->currentTeam));
        $this->actingAs($owner)->getJson(route('ai.knowledge.index', $team->slug))->assertOk()->assertJsonCount(1, 'data');

        $roles = app(RolePermissions::class)->roles();
        unset($roles['owner']['ai_knowledge_documents.update']);
        config(['access.roles' => $roles]);
        $this->patchJson(route('ai.knowledge.update', [$team->slug, $document]), ['title' => 'Changed guide', 'content' => 'Updated guide content'])->assertForbidden();
        $this->assertSame('Pedoman kebun', $document->fresh()->title);
        $this->assertTrue(Gate::forUser($owner)->allows('create', [AiKnowledgeDocument::class, $team]));

        $admin = $this->actor('superadmin');
        $team->members()->attach($admin, ['role' => TeamRole::Owner->value]);
        $this->actingAs($admin)->getJson(route('ai.knowledge.index', $team->slug))->assertForbidden();
    }

    public function test_missing_assignment_migration_denies_own_but_any_still_works(): void
    {
        Schema::drop('farm_user');
        $admin = $this->actor('admin');
        $farm = $this->farm($admin->currentTeam);
        $this->assertFalse(Gate::forUser($admin)->allows('view', $farm));
        $this->assertSame(0, Farm::visibleTo($admin, $admin->currentTeam)->count());
        $owner = $this->actor('owner');
        $admin->currentTeam->members()->attach($owner, ['role' => TeamRole::Member->value]);
        $this->assertTrue(Gate::forUser($owner)->allows('view', $farm));
    }

    public function test_pivot_enforces_unique_assignments(): void
    {
        $user = $this->actor('admin');
        $farm = $this->farm($user->currentTeam);
        $user->farms()->attach($farm);
        $this->expectException(QueryException::class);
        $user->farms()->attach($farm);
    }

    public function test_application_role_is_independent_from_team_ownership(): void
    {
        $owner = $this->actor('owner');
        $team = $owner->currentTeam;
        $this->actingAs($owner)->patch(route('teams.update', $team), ['name' => 'Forbidden rename'])->assertForbidden();
        $this->assertFalse($owner->toTeamPermissions($team)->canUpdateTeam);

        $superadmin = $this->actor('superadmin');
        $team->members()->attach($superadmin, ['role' => TeamRole::Member->value]);
        $this->assertTrue($superadmin->toTeamPermissions($team)->canUpdateTeam);
        $this->actingAs($superadmin)->patch(route('teams.update', $team), ['name' => 'Authorized rename'])->assertRedirect();
        $this->assertSame('Authorized rename', $team->fresh()->name);
    }

    public function test_domain_user_and_recipient_need_a_persisted_native_identity_link(): void
    {
        $actor = $this->actor('owner');
        // Deliberately load the same numeric ID through the projection. Equality is not a link.
        $projection = \App\Models\Farmer\User::findOrFail($actor->id);
        $farm = $this->farm($actor->currentTeam);
        $notification = $this->record(\App\Models\Notification::class, $actor, $actor->currentTeam, $farm);
        $this->assertFalse(method_exists($projection, 'teams'));
        $this->assertNotInstanceOf(\Illuminate\Contracts\Auth\Authenticatable::class, $projection);
        foreach (['view', 'update', 'delete', 'configure'] as $ability) {
            $this->assertFalse(Gate::forUser($actor)->allows($ability, $projection));
            $this->assertFalse(Gate::forUser($actor)->allows($ability, $notification));
        }
        $this->assertFalse(Gate::forUser($actor)->allows('viewAny', [\App\Models\Farmer\User::class, $actor->currentTeam]));
        $this->assertFalse(Gate::forUser($actor)->allows('create', [\App\Models\Farmer\User::class, $actor->currentTeam]));
        $this->assertSame(0, \App\Models\Farmer\User::visibleTo($actor, $actor->currentTeam)->count());
        $this->assertSame(0, \App\Models\Notification::visibleTo($actor, $actor->currentTeam)->count());
        $this->assertTrue(Gate::forUser($actor)->allows('view', $actor));

        $parameters = (new \ReflectionMethod(ResourcePolicy::class, 'view'))->getParameters();
        $this->assertSame(\App\Models\User::class, (string) $parameters[0]->getType());
    }

    public function test_shared_records_require_an_explicit_grant_and_any_real_membership(): void
    {
        $actor = $this->actor('owner');
        $originalTeam = $actor->currentTeam;
        $farm = $this->farm($originalTeam);
        $classes = [\App\Models\Commodity::class, \App\Models\CropVariety::class,
            \App\Models\FinancialCategory::class, \App\Models\ActivityType::class,
            \App\Models\Role::class, \App\Models\AuditLog::class];
        $records = array_map(fn (string $class) => $this->record($class, $actor, $originalTeam, $farm), $classes);
        $grants = app(RolePermissions::class)->roles();
        $withoutGrant = $grants;
        foreach ($records as $record) {
            unset($withoutGrant['owner'][$record->getTable().'.view']);
        }
        config(['access.roles' => $withoutGrant]);
        foreach ($records as $record) {
            $this->assertFalse(Gate::forUser($actor)->allows('view', $record));
        }
        config(['access.roles' => $grants]);
        $actor->teams()->detach(); // Keep a stale currentTeam pointer to prove it is not authority.
        foreach ($records as $record) {
            $this->assertFalse(Gate::forUser($actor)->allows('view', $record));
        }
        $anotherTeam = Team::factory()->create();
        $anotherTeam->members()->attach($actor, ['role' => TeamRole::Member->value]);
        $actor->current_team_id = null;
        $actor->unsetRelation('currentTeam');
        foreach ($records as $record) {
            $this->assertTrue(Gate::forUser($actor)->allows('view', $record));
            $this->assertSame(1, $record::visibleTo($actor, $anotherTeam)->whereKey($record->getKey())->count());
        }
        $this->assertFalse(Gate::forUser($actor)->allows('view', $farm));
        $actor->role = 'petugas'; // Catalog grants must never act as farm grants.
        $anotherFarm = $this->farm($anotherTeam);
        $this->assertTrue(Gate::forUser($actor)->allows('view', $records[0]));
        $this->assertFalse(Gate::forUser($actor)->allows('view', $anotherFarm));
    }
}
