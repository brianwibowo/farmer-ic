<?php

namespace Tests\Feature\Farms;

use App\Models\ActivityType;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\FarmCycle;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use App\Services\FarmCycleService;
use App\Services\PlantingCycleTaskService;
use Database\Seeders\FarmCycleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/** Integrated PostgreSQL tests on the guarded, separate testing database. */
class FarmCycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $petugas;

    private Team $team;

    private Farm $farm;

    private FarmBlock $block;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.ssr.enabled' => false]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->team = $this->admin->currentTeam;
        $this->team->update(['is_personal' => false]);
        $this->farm = $this->farm('Lahan Siklus Utara');
        $this->block = app(FarmBlockService::class)->create($this->farm, $this->admin, $this->team, ['name' => 'Blok Jagung', 'area_ha' => '10']);
        $this->petugas = User::factory()->create(['role' => 'petugas']);
        $this->team->members()->attach($this->petugas, ['role' => 'member']);
        $this->petugas->forceFill(['assigned_farm_id' => $this->farm->id, 'current_team_id' => $this->team->id])->save();
    }

    private function farm(string $name): Farm
    {
        $farm = new Farm;
        $farm->forceFill(['name' => $name, 'land_tenure' => 'owned', 'total_area_ha' => '20', 'created_by' => $this->admin->id, 'team_id' => $this->team->id])->save();

        return $farm;
    }

    private function url(): string
    {
        return '/kebun/'.$this->farm->id.'/blocks/'.$this->block->id.'/batches';
    }

    private function create(array $data = []): PlantingCycle
    {
        $this->actingAs($this->admin)->post($this->url(), $data)->assertSessionHasNoErrors()->assertRedirect();

        return PlantingCycle::where('farm_id', $this->farm->id)->orderByDesc('batch_number')->firstOrFail();
    }

    public function test_manual_cycles_group_batches_and_never_reset_batch_numbers(): void
    {
        $one = $this->create(['create_farm_cycle' => true]);
        $two = $this->create(['farm_cycle_id' => $one->farm_cycle_id]);
        $three = $this->create(['create_farm_cycle' => true]);
        $four = $this->create(['farm_cycle_id' => $one->farm_cycle_id]);
        $this->assertSame([1, 2, 3, 4], [$one->batch_number, $two->batch_number, $three->batch_number, $four->batch_number]);
        $this->assertSame($one->farm_cycle_id, $two->farm_cycle_id);
        $this->assertSame($one->farm_cycle_id, $four->farm_cycle_id);
        $this->assertNotSame($one->farm_cycle_id, $three->farm_cycle_id);
        $this->assertSame(2, $three->farmCycle->cycle_number);
        $this->assertSame('planned', $three->status);
        $this->get('/admin/farm-management/'.$this->farm->id)->assertInertia(fn (Assert $page) => $page
            ->where('farmCycleOptions.0.cycle_number', 2)->where('farmCycleOptions.0.batch_count', 1)
            ->where('farmCycleOptions.1.batch_count', 3)->where('canCreateFarmCycle', true));
        // Omitting the selector for older clients joins the latest cycle without advancing it.
        $this->assertSame($three->farm_cycle_id, $this->create()->farm_cycle_id);
        $this->assertDatabaseCount('farm_cycles', 2);
    }

    public function test_petugas_can_create_and_select_cycles_without_admin_approval_on_assigned_farm_only(): void
    {
        $url = '/petugas/blocks/'.$this->block->id.'/subbloks';
        $this->actingAs($this->petugas)->post($url, ['return_to' => 'kebun', 'create_farm_cycle' => true])->assertSessionHasNoErrors()->assertRedirect();
        $cycle = FarmCycle::firstOrFail();
        $this->post($url, ['return_to' => 'kebun', 'farm_cycle_id' => $cycle->id])->assertSessionHasNoErrors();
        $this->get('/petugas/kebun')->assertInertia(fn (Assert $page) => $page
            ->where('farmCycleOptions.0.batch_count', 2)->where('canCreateFarmCycle', true)
            ->where('garden.batches.data.0.farm_cycle_number', 1));
        $other = $this->farm('Lahan Tidak Ditugaskan');
        $otherBlock = app(FarmBlockService::class)->create($other, $this->admin, $this->team, ['name' => 'Blok Selatan', 'area_ha' => '5']);
        $this->post('/petugas/blocks/'.$otherBlock->id.'/subbloks', ['create_farm_cycle' => true])->assertForbidden();
        $this->assertSame(0, FarmCycle::visibleTo($this->petugas, $this->team)->where('farm_id', $other->id)->count());
    }

    public static function roles(): array
    {
        return [['owner', false], ['admin', true], ['petugas', true], ['superadmin', true]];
    }

    #[DataProvider('roles')]
    public function test_policy_roles_and_default_deny(string $role, bool $canCreate): void
    {
        $batch = $this->create(['create_farm_cycle' => true]);
        $actor = User::factory()->create(['role' => $role]);
        if ($role !== 'superadmin') {
            $this->team->members()->attach($actor, ['role' => 'member']);
        }
        $actor->forceFill(['assigned_farm_id' => $role === 'petugas' ? $this->farm->id : null, 'current_team_id' => $this->team->id])->save();
        $cycle = $batch->farmCycle;
        $this->assertTrue(Gate::forUser($actor)->allows('view', $cycle));
        $this->assertSame($canCreate, Gate::forUser($actor)->allows('create', [FarmCycle::class, $this->farm]));
        foreach (['update', 'delete', 'configure'] as $ability) {
            $this->assertSame($role === 'superadmin', Gate::forUser($actor)->allows($ability, $cycle));
        }
        if ($role === 'owner') {
            $this->actingAs($actor)->post($this->url(), ['create_farm_cycle' => true])->assertForbidden();
        }
    }

    public function test_forged_cycle_from_another_farm_and_client_number_are_rejected(): void
    {
        $one = $this->create(['create_farm_cycle' => true]);
        $other = $this->farm('Lahan Kedua');
        $otherCycle = FarmCycle::create(['farm_id' => $other->id, 'cycle_number' => 1, 'created_by' => $this->admin->id, 'created_at' => now()]);
        foreach ([['farm_cycle_id' => $otherCycle->id], ['farm_cycle_id' => 'invalid'], ['farm_cycle_id' => $one->farm_cycle_id, 'create_farm_cycle' => true], ['cycle_number' => 88], ['farm_id' => $other->id]] as $payload) {
            $this->postJson($this->url(), $payload)->assertUnprocessable();
        }
        $foreignTeam = Team::query()->create(['name' => 'Tenant Terpisah', 'is_personal' => false]);
        $other->forceFill(['team_id' => $foreignTeam->id])->save();
        $this->assertFalse(Gate::forUser($this->admin)->allows('view', $otherCycle->fresh()));
        $this->assertSame(0, FarmCycle::visibleTo($this->admin, $this->team)->whereKey($otherCycle->id)->count());
        $this->postJson($this->url(), ['farm_cycle_id' => $otherCycle->id])->assertUnprocessable();
        $this->assertDatabaseCount('planting_cycles', 1);
    }

    public function test_missing_grants_deny_new_cycles_but_existing_selection_does_not_need_create_grant(): void
    {
        $one = $this->create(['create_farm_cycle' => true]);
        $grants = config('access.roles.admin');
        unset($grants['farm_cycles.create']);
        config(['access.roles.admin' => $grants]);
        $this->post($this->url(), ['create_farm_cycle' => true])->assertForbidden();
        $this->post($this->url(), ['farm_cycle_id' => $one->farm_cycle_id])->assertSessionHasNoErrors();
        unset($grants['farm_cycles.view']);
        config(['access.roles.admin' => $grants]);
        $this->assertSame([], app(FarmCycleService::class)->options($this->farm, $this->admin, $this->team));
        $this->post($this->url(), ['farm_cycle_id' => $one->farm_cycle_id])->assertForbidden();
        $this->assertDatabaseCount('farm_cycles', 1);
        $this->assertDatabaseCount('planting_cycles', 2);
    }

    public function test_cycle_cannot_be_changed_when_editing_a_batch(): void
    {
        $one = $this->create(['create_farm_cycle' => true]);
        $two = $this->create(['create_farm_cycle' => true]);
        $this->putJson($this->url().'/'.$one->id, ['farm_cycle_id' => $two->farm_cycle_id])->assertUnprocessable();
        $this->assertSame($one->farm_cycle_id, $one->fresh()->farm_cycle_id);
    }

    public function test_cycle_creation_rolls_back_with_batch_and_sop_failure(): void
    {
        $this->mock(PlantingCycleTaskService::class)->shouldReceive('generateForSubblok')->once()->andThrow(new RuntimeException('Simulated SOP failure'));
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->admin)->post($this->url(), ['create_farm_cycle' => true]);
            $this->fail('Expected a simulated failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated SOP failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('farm_cycles', 0);
        $this->assertDatabaseCount('planting_cycles', 0);
        $this->assertDatabaseCount('planting_cycle_stages', 0);
    }

    public function test_legacy_seed_backfill_is_idempotent_and_database_enforces_farm_boundary(): void
    {
        $batch = $this->create();
        $cycleId = $batch->farm_cycle_id;
        $batch->forceFill(['farm_cycle_id' => null])->save();
        $this->seed(FarmCycleSeeder::class);
        $this->seed(FarmCycleSeeder::class);
        $this->assertSame($cycleId, $batch->fresh()->farm_cycle_id);
        $this->assertDatabaseCount('farm_cycles', 1);
        $other = $this->farm('Lahan Batas Database');
        $foreign = FarmCycle::create(['farm_id' => $other->id, 'cycle_number' => 1, 'created_by' => $this->admin->id, 'created_at' => now()]);
        $this->expectException(QueryException::class);
        DB::table('planting_cycles')->where('id', $batch->id)->update(['farm_cycle_id' => $foreign->id]);
    }

    public function test_harvest_summary_uses_actual_reports_in_each_cycle_and_scoped_details(): void
    {
        $one = $this->create(['create_farm_cycle' => true]);
        $two = $this->create(['farm_cycle_id' => $one->farm_cycle_id]);
        $three = $this->create(['create_farm_cycle' => true]);
        $type = ActivityType::firstOrCreate(['code' => 'harvest'], ['name' => 'Panen', 'detail_key' => 'harvest', 'is_selectable' => true, 'sort_order' => 60]);
        foreach ([[$one, '2.125'], [$one, '1.500'], [$two, '3.000'], [$three, '8.750']] as [$batch, $weight]) {
            $activity = FarmActivity::create(['farm_id' => $this->farm->id, 'planting_cycle_id' => $batch->id, 'activity_type_id' => $type->id, 'performed_by' => $this->admin->id, 'activity_date' => now(), 'status' => 'completed']);
            $activity->harvestDetail()->create(['harvested_area_ha' => '0.1', 'gross_weight_ton' => $weight]);
        }
        $options = app(FarmCycleService::class)->options($this->farm, $this->admin, $this->team);
        $this->assertSame('8.750', $options[0]['harvested_ton']);
        $this->assertSame('6.625', $options[1]['harvested_ton']);
        $this->get('/admin/farm-management/'.$this->farm->id.'/siklus')->assertInertia(fn (Assert $page) => $page
            ->component('farms/Cycles')->where('cycles.total', 2)
            ->where('cycles.data.0.harvested_ton', '8.750')
            ->where('cycles.data.1.harvested_ton', '6.625')
            ->where('cycles.data.1.batch_count', 2));
        $grants = config('access.roles.admin');
        unset($grants['harvest_details.view']);
        config(['access.roles.admin' => $grants]);
        $this->assertSame('0.000', app(FarmCycleService::class)->options($this->farm, $this->admin, $this->team)[0]['harvested_ton']);
        $this->get('/admin/farm-management/'.$this->farm->id.'/siklus')->assertInertia(fn (Assert $page) => $page
            ->where('cycles.data.0.harvested_ton', '0.000'));
    }

    public function test_cycle_detail_pagination_is_newest_first_and_excludes_other_farms(): void
    {
        for ($number = 1; $number <= 8; $number++) {
            FarmCycle::create(['farm_id' => $this->farm->id, 'cycle_number' => $number, 'created_by' => $this->admin->id, 'created_at' => now()]);
        }
        $other = $this->farm('Lahan Lain');
        FarmCycle::create(['farm_id' => $other->id, 'cycle_number' => 99, 'created_by' => $this->admin->id, 'created_at' => now()]);
        $url = '/admin/farm-management/'.$this->farm->id.'/siklus';
        $this->actingAs($this->admin)->get($url)->assertInertia(fn (Assert $page) => $page
            ->component('farms/Cycles')->where('farm.id', $this->farm->id)
            ->where('cycles.total', 8)->where('cycles.last_page', 2)->has('cycles.data', 6)
            ->where('cycles.data.0.cycle_number', 8)->where('cycles.data.5.cycle_number', 3)
            ->where('cycles.data.0.batch_count', 0)
            ->where('backUrl', '/admin/farm-management/'.$this->farm->id));
        $this->get($url.'?page=2')->assertInertia(fn (Assert $page) => $page
            ->has('cycles.data', 2)->where('cycles.data.0.cycle_number', 2)->where('cycles.data.1.cycle_number', 1));
        $this->get($url.'?page=3')->assertInertia(fn (Assert $page) => $page
            ->has('cycles.data', 0)->where('cycles.total', 8));
        $foreignTeam = Team::create(['name' => 'Tenant Lain', 'is_personal' => false]);
        $other->forceFill(['team_id' => $foreignTeam->id])->save();
        $this->get('/admin/farm-management/'.$other->id.'/siklus')->assertForbidden();
    }

    public function test_petugas_cycle_details_use_only_the_persisted_assignment(): void
    {
        $this->create(['create_farm_cycle' => true]);
        $other = $this->farm('Lahan Tidak Ditugaskan');
        FarmCycle::create(['farm_id' => $other->id, 'cycle_number' => 99, 'created_by' => $this->admin->id, 'created_at' => now()]);
        $this->actingAs($this->petugas)->get('/petugas/kebun')->assertInertia(fn (Assert $page) => $page
            ->where('farmCyclesUrl', '/petugas/kebun/siklus'));
        $this->get('/petugas/kebun/siklus?farm_id='.$other->id.'&team_id=999')->assertInertia(fn (Assert $page) => $page
            ->component('petugas/Cycles')->where('farm.id', $this->farm->id)
            ->where('cycles.total', 1)->where('cycles.data.0.cycle_number', 1)
            ->where('backUrl', '/petugas/kebun'));
        $this->get('/admin/farm-management/'.$this->farm->id.'/siklus')->assertForbidden();
        $this->petugas->forceFill(['assigned_farm_id' => null])->save();
        $this->get('/petugas/kebun/siklus')->assertForbidden();
    }

    public function test_cycle_listing_denies_missing_grant_and_validates_page(): void
    {
        $url = '/admin/farm-management/'.$this->farm->id.'/siklus';
        $this->get($url)->assertRedirect('/login');
        $this->actingAs($this->admin)->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('cycles.total', 0)->has('cycles.data', 0));
        foreach (['0', '-1', 'abc', '100001'] as $page) {
            $this->getJson($url.'?page='.$page)->assertUnprocessable();
        }
        $grants = config('access.roles.admin');
        unset($grants['farm_cycles.view']);
        config(['access.roles.admin' => $grants]);
        $this->get($url)->assertForbidden();
    }

    public function test_owner_cycle_details_keep_the_existing_current_team_boundary(): void
    {
        $this->create(['create_farm_cycle' => true]);
        $owner = User::factory()->create(['role' => 'owner']);
        $this->team->members()->attach($owner, ['role' => 'member']);
        $owner->forceFill(['current_team_id' => $this->team->id])->save();
        $url = '/kebun/'.$this->farm->id.'/siklus';
        $this->actingAs($owner)->get($url)->assertInertia(fn (Assert $page) => $page
            ->component('farms/Cycles')->where('cycles.total', 1)
            ->where('backUrl', '/kebun/'.$this->farm->id));
        $this->get('/admin/farm-management/'.$this->farm->id.'/siklus')->assertForbidden();
        $foreignTeam = Team::create(['name' => 'Tenant Asing', 'is_personal' => false]);
        $other = $this->farm('Lahan Tenant Asing');
        $other->forceFill(['team_id' => $foreignTeam->id])->save();
        $this->get('/kebun/'.$other->id.'/siklus')->assertForbidden();
        $foreignTeam->members()->attach($owner, ['role' => 'member']);
        $this->get('/kebun/'.$other->id.'/siklus')->assertNotFound();
    }
}
