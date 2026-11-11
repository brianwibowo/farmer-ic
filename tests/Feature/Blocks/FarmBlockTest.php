<?php

namespace Tests\Feature\Blocks;

use App\Enums\TeamRole;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\PlantObservation;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use App\Support\RolePermissions;
use Database\Seeders\BlockMappingActivityTypeSeeder;
use Database\Seeders\DemoFarmBlockSeeder;
use Database\Seeders\DemoFarmSeeder;
use Database\Seeders\DemoIdentitySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** HTTP, policy and persistence integration on the guarded PostgreSQL test schema. */
class FarmBlockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Team $team;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->team = Team::factory()->create();
        $this->join($this->admin);
        $this->farm = $this->farm();
        $this->actingAs($this->admin);
    }

    private function join(User $actor): void
    {
        $this->team->members()->attach($actor, ['role' => TeamRole::Member->value]);
        $actor->forceFill(['current_team_id' => $this->team->id])->save();
        $actor->unsetRelation('teams')->unsetRelation('currentTeam');
    }

    private function farm(?Team $team = null): Farm
    {
        $farm = new Farm(['name' => 'Kebun Percobaan', 'total_area_ha' => '10.0000']);
        $farm->forceFill(['team_id' => ($team ?? $this->team)->id, 'created_by' => $this->admin->id])->save();

        return $farm;
    }

    private function block(string $code = 'B01', string $area = '5.0000', ?Farm $farm = null): FarmBlock
    {
        $farm ??= $this->farm;

        $creator = $farm->team_id === $this->team->id ? $this->admin : User::factory()->create(['role' => 'superadmin']);

        return app(FarmBlockService::class)->create($farm, $creator, $farm->team, ['code' => $code, 'name' => 'Blok Utara', 'area_ha' => $area]);
    }

    private function url(?FarmBlock $block = null, ?Farm $farm = null): string
    {
        return '/kebun/'.($farm ?? $this->farm)->id.'/blocks'.($block ? '/'.$block->id : '');
    }

    private function constraints(): void
    {
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
    }

    public function test_farm_detail_growth_uses_latest_visible_observation_for_each_batch(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-08 17:05:00', 'UTC'));
        $block = $this->block();
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1'])->assertRedirect();
        $batch = PlantingCycle::firstOrFail();
        $commodity = Commodity::create(['name' => 'Jagung']);
        $variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Hibrida BISI-18']);
        $batch->forceFill(['variety_id' => $variety->id, 'status' => 'active', 'planted_area_ha' => 1, 'planted_at' => '2026-10-01', 'estimated_harvest_at' => '2027-01-12'])->save();
        $batch->stages()->where('position', '<=', 4)->update(['status' => 'completed', 'started_at' => '2026-09-30', 'completed_at' => '2026-10-01']);
        $batch->stages()->where('code', 'maintenance')->update(['status' => 'in_progress', 'started_at' => '2026-10-02', 'completed_at' => null]);
        PlantObservation::create(['planting_cycle_id' => $batch->id, 'observed_by' => $this->admin->id, 'observed_at' => '2026-11-01 08:00:00', 'growth_phase' => 'Kecambah', 'growth_percentage' => 20]);
        $latest = PlantObservation::create(['planting_cycle_id' => $batch->id, 'observed_by' => $this->admin->id, 'observed_at' => '2026-11-09 08:00:00', 'growth_phase' => 'Vegetatif', 'growth_percentage' => 42.5]);

        $otherFarm = $this->farm();
        $otherBlock = $this->block('B02', '2', $otherFarm);
        $this->post($this->url($otherBlock, $otherFarm).'/batches', ['reserved_area_ha' => '1'])->assertRedirect();
        $otherBatch = PlantingCycle::where('farm_id', $otherFarm->id)->firstOrFail();
        PlantObservation::create(['planting_cycle_id' => $otherBatch->id, 'observed_by' => $this->admin->id, 'observed_at' => '2026-11-10 08:00:00', 'growth_phase' => 'Panen', 'growth_percentage' => 99]);

        $url = '/admin/farm-management/'.$this->farm->id;
        $this->get($url.'?farm_id='.$otherFarm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('blocks', 1)->has('blocks.0.batches', 1)
            ->where('blocks.0.batches.0.growth.available', true)
            ->where('blocks.0.batches.0.growth.as_of', '2026-11-09')
            ->where('blocks.0.batches.0.growth.hst', 39)
            ->where('blocks.0.batches.0.growth.commodity', 'Jagung')
            ->where('blocks.0.batches.0.growth.variety', 'Hibrida BISI-18')
            ->where('blocks.0.batches.0.growth.estimated_harvest_at', '2027-01-12')
            ->where('blocks.0.batches.0.growth.observation.id', $latest->id)
            ->where('blocks.0.batches.0.growth.observation.phase', 'Vegetatif')
            ->where('blocks.0.batches.0.growth.observation.percentage', 42.5));
        $this->travel(1)->days();
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('blocks.0.batches.0.growth.hst', 40));
        $grants = config('access.roles.admin');
        unset($grants['plant_observations.view']);
        config(['access.roles.admin' => $grants]);
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('blocks.0.batches.0.growth.observation', null));
        $foreignFarm = $this->farm(Team::factory()->create());
        $this->get('/admin/farm-management/'.$foreignFarm->id)->assertForbidden();
        $this->constraints();
    }

    public function test_preparation_growth_has_no_invented_observation_or_harvest_date(): void
    {
        $block = $this->block();
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1'])->assertRedirect();
        $this->get('/admin/farm-management/'.$this->farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('blocks.0.batches.0.growth.available', false)
            ->where('blocks.0.batches.0.growth.hst', null)
            ->where('blocks.0.batches.0.growth.observation', null)
            ->where('blocks.0.batches.0.growth.estimated_harvest_at', null)
            ->where('blocks.0.batches.0.growth.commodity', null));
        $this->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page->missing('blocks.data.0.batches.0.growth'));
    }

    public function test_subblock_header_age_uses_actual_planting_before_growth_is_available_and_rolls_over_at_jakarta_midnight(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-08 16:59:00', 'UTC'));
        $block = $this->block();
        $this->post($this->url($block).'/batches', [
            'reserved_area_ha' => '1', 'planned_planting_at' => '2026-11-01',
        ])->assertRedirect();
        $batch = PlantingCycle::firstOrFail();
        $commodity = Commodity::create(['name' => 'Jagung Umur Tanam']);
        $variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'BISI-18 Umur Tanam']);
        $batch->forceFill([
            'variety_id' => $variety->id, 'status' => 'active',
            'planted_area_ha' => 1, 'planted_at' => '2026-11-07',
        ])->save();

        $url = '/admin/farm-management/'.$this->farm->id;
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('blocks.0.batches.0.age.hst', 1)
            ->where('blocks.0.batches.0.age.as_of', '2026-11-08')
            ->where('blocks.0.batches.0.age.status', 'available')
            ->where('blocks.0.batches.0.growth.available', false));

        $this->travel(1)->minutes();
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('blocks.0.batches.0.age.hst', 2)
            ->where('blocks.0.batches.0.age.as_of', '2026-11-09')
            ->where('blocks.0.batches.0.age.status', 'available'));
        $this->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->missing('blocks.data.0.batches.0.age'));
        $this->constraints();
    }

    #[DataProvider('subblockAges')]
    public function test_subblock_header_age_distinguishes_zero_missing_future_cancelled_and_harvested_planting(
        ?string $plantedAt, string $cycleStatus, string $ageStatus, ?int $hst,
    ): void {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-09 10:00:00', 'Asia/Jakarta'));
        $block = $this->block();
        $this->post($this->url($block).'/batches', [
            'reserved_area_ha' => '1', 'planned_planting_at' => '2026-11-01',
        ])->assertRedirect();
        $batch = PlantingCycle::firstOrFail();
        $commodity = Commodity::create(['name' => 'Jagung Status Umur']);
        $variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'BISI-18 Status Umur']);
        $batch->forceFill([
            'variety_id' => $plantedAt ? $variety->id : null,
            'status' => $cycleStatus, 'planted_area_ha' => $plantedAt ? 1 : 0,
            'planted_at' => $plantedAt,
        ])->save();

        $this->get('/admin/farm-management/'.$this->farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('blocks.0.batches.0.age.hst', $hst)
            ->where('blocks.0.batches.0.age.as_of', '2026-11-09')
            ->where('blocks.0.batches.0.age.status', $ageStatus));
        $this->constraints();
    }

    public static function subblockAges(): array
    {
        return [
            'planting day is zero' => ['2026-11-09', 'active', 'available', 0],
            'planned date is not actual planting' => [null, 'planned', 'not_planted', null],
            'future actual date has no negative age' => ['2026-11-10', 'active', 'future', null],
            'cancelled cycle has no current age' => ['2026-11-07', 'cancelled', 'cancelled', null],
            'harvested history uses its actual date' => ['2026-11-01', 'harvested', 'available', 8],
        ];
    }

    public function test_subblock_header_age_is_independent_per_batch_and_never_exposes_other_farms_or_hidden_cycles(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-09 10:00:00', 'Asia/Jakarta'));
        $block = $this->block();
        $commodity = Commodity::create(['name' => 'Jagung Cakupan Umur']);
        $variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'BISI-18 Cakupan Umur']);
        $batches = [];
        foreach (['2026-11-01', '2026-11-05'] as $date) {
            $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1'])->assertRedirect();
            $batch = PlantingCycle::where('block_id', $block->id)->orderByDesc('batch_number')->firstOrFail();
            $batch->forceFill([
                'variety_id' => $variety->id, 'status' => 'active',
                'planted_area_ha' => 1, 'planted_at' => $date,
            ])->save();
            $batches[] = $batch;
        }
        $otherFarm = $this->farm();
        $otherBlock = $this->block('B02', '2', $otherFarm);
        $this->post($this->url($otherBlock, $otherFarm).'/batches', ['reserved_area_ha' => '1'])->assertRedirect();

        $url = '/admin/farm-management/'.$this->farm->id;
        $this->get($url.'?farm_id='.$otherFarm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('blocks', 1)->has('blocks.0.batches', 2)
            ->where('blocks.0.batches.0.id', $batches[0]->id)
            ->where('blocks.0.batches.0.age.hst', 8)
            ->where('blocks.0.batches.1.id', $batches[1]->id)
            ->where('blocks.0.batches.1.age.hst', 4));
        $foreignFarm = $this->farm(Team::factory()->create());
        $this->get('/admin/farm-management/'.$foreignFarm->id)->assertForbidden();

        $grants = config('access.roles.admin');
        unset($grants['planting_cycles.view']);
        config(['access.roles.admin' => $grants]);
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('blocks', 1)->has('blocks.0.batches', 0));
        $this->constraints();
    }

    public function test_growth_is_blocked_until_planting_is_completed_and_maintenance_has_started(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-09 10:00:00', 'Asia/Jakarta'));
        $block = $this->block();
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1'])->assertRedirect();
        $batch = PlantingCycle::firstOrFail();
        $commodity = Commodity::create(['name' => 'Jagung']);
        $variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Hibrida BISI-18']);
        $batch->forceFill(['variety_id' => $variety->id, 'status' => 'active', 'planted_area_ha' => 1, 'planted_at' => '2026-11-09'])->save();
        PlantObservation::create(['planting_cycle_id' => $batch->id, 'observed_by' => $this->admin->id, 'observed_at' => now(), 'growth_phase' => 'Vegetatif', 'growth_percentage' => 20]);
        $url = '/admin/farm-management/'.$this->farm->id;
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('blocks.0.batches.0.growth.available', false)->where('blocks.0.batches.0.growth.observation', null));
        $batch->stages()->where('position', '<=', 4)->update(['status' => 'completed', 'started_at' => '2026-11-08', 'completed_at' => '2026-11-09']);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('blocks.0.batches.0.growth.available', false));
        $batch->stages()->where('code', 'maintenance')->update(['status' => 'in_progress', 'started_at' => '2026-11-09']);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('blocks.0.batches.0.growth.available', true)->where('blocks.0.batches.0.growth.hst', 0));
        $batch->forceFill(['planted_at' => '2026-11-10'])->save();
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('blocks.0.batches.0.growth.available', false)->where('blocks.0.batches.0.growth.hst', null));
        $batch->forceFill(['planted_at' => '2026-11-09', 'status' => 'cancelled'])->save();
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('blocks.0.batches.0.growth.available', false));
    }

    public function test_preparation_batch_edit_persists_without_changing_progress_and_reuses_its_capacity(): void
    {
        $block = $this->block('B01', '5');
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '4'])->assertRedirect();
        $batch = PlantingCycle::firstOrFail();
        $stages = PlantingCycleStage::orderBy('position')->get()->toArray();
        $url = $this->url($block).'/batches/'.$batch->id;
        $this->put($url, ['area_value' => '50000', 'area_unit' => 'm2', 'subblock_label' => ' c ', 'planned_planting_at' => '2026-12-05'])->assertSessionHasNoErrors()->assertRedirect($this->url());
        $this->assertSame('5.0000', $batch->fresh()->getAttribute('reserved_area_ha'));
        $this->assertSame('C', $batch->fresh()->getAttribute('subblock_label'));
        $this->assertSame('2026-12-05', $batch->fresh()->planned_planting_at?->toDateString());
        $this->get($this->url())->assertInertia(fn (Assert $page) => $page->where('blocks.data.0.batches.0.planned_planting_at', '2026-12-05'));
        $this->assertSame($stages, PlantingCycleStage::orderBy('position')->get()->toArray());
        $this->put($url, ['reserved_area_ha' => '5.0001'])->assertSessionHasErrors('reserved_area_ha');
        $this->put($url, ['reserved_area_ha' => '4', 'status' => 'active', 'farm_id' => $this->farm->id])->assertSessionHasErrors(['status', 'farm_id']);
        $this->constraints();
    }

    #[DataProvider('roles')]
    public function test_batch_update_obeys_policy_and_assigned_farm(string $role, bool $unused): void
    {
        $block = $this->block();
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1'])->assertRedirect();
        $batch = PlantingCycle::firstOrFail();
        $actor = User::factory()->create(['role' => $role]);
        if ($role !== 'superadmin') {
            $this->join($actor);
        }
        $actor->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->actingAs($actor);
        $allowed = $role !== 'owner';
        $this->get($this->url())->assertInertia(fn (Assert $page) => $page->where('batchPermissions.'.$batch->id, $allowed));
        $response = $this->put($this->url($block).'/batches/'.$batch->id, ['reserved_area_ha' => '2']);
        $allowed ? $response->assertRedirect() : $response->assertForbidden();
        if ($role === 'petugas') {
            $actor->forceFill(['assigned_farm_id' => null])->save();
            $this->put($this->url($block).'/batches/'.$batch->id, ['reserved_area_ha' => '1'])->assertForbidden();
        }
        $this->constraints();
    }

    public function test_batch_update_rejects_duplicate_label_wrong_parent_cross_team_and_started_planting(): void
    {
        $block = $this->block();
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1', 'subblock_label' => 'A'])->assertRedirect();
        $batch = PlantingCycle::firstOrFail();
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1', 'subblock_label' => 'B'])->assertRedirect();
        $url = $this->url($block).'/batches/'.$batch->id;
        $this->put($url, ['reserved_area_ha' => '1', 'subblock_label' => 'B'])->assertSessionHasErrors('subblock_label');
        $other = $this->block('B02');
        $this->put($this->url($other).'/batches/'.$batch->id, ['reserved_area_ha' => '1'])->assertNotFound();
        $foreign = User::factory()->create(['role' => 'admin']);
        $this->actingAs($foreign)->put($url, ['reserved_area_ha' => '1', 'team_id' => $this->team->id])->assertSessionHasErrors('team_id');
        $this->put($url, ['reserved_area_ha' => '1'])->assertForbidden();
        $this->actingAs($this->admin);
        $commodity = Commodity::create(['name' => 'Jagung Persiapan']);
        $variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Bisi Persiapan']);
        $batch->forceFill(['variety_id' => $variety->id, 'planted_at' => '2026-11-03', 'planted_area_ha' => '1', 'status' => 'active'])->save();
        $this->put($url, ['reserved_area_ha' => '2'])->assertSessionHasErrors('subblock_label');
        $this->assertSame('1.0000', $batch->fresh()->getAttribute('reserved_area_ha'));
        $this->constraints();
    }

    public function test_batches_are_managed_inline_in_farm_detail_and_block_list(): void
    {
        $block = $this->block();
        $base = $this->url($block).'/batches';
        $farmDetail = '/admin/farm-management/'.$this->farm->id;
        $this->post($base, ['area_value' => '10000', 'area_unit' => 'm2', 'planned_planting_at' => '2026-12-05', 'return_to' => 'farm'])->assertSessionHasNoErrors()->assertRedirect($farmDetail);
        $batch = PlantingCycle::firstOrFail();
        $this->get($farmDetail)->assertInertia(fn (Assert $page) => $page->has('blocks', 1)
            ->where('blocks.0.can.createBatch', true)->where('blocks.0.batches.0.id', $batch->id)
            ->where('blocks.0.batches.0.planned_planting_at', '2026-12-05')->has('blocks.0.batches.0.stages', 6)
            ->where('blocks.0.batches.0.stages.1.status', 'upcoming')->where('batchPermissions.'.$batch->id, true));
        $this->put($base.'/'.$batch->id, ['reserved_area_ha' => '2', 'subblock_label' => 'C', 'return_to' => 'farm'])->assertSessionHasNoErrors()->assertRedirect($farmDetail);
        $this->assertSame('C', $batch->fresh()->getAttribute('subblock_label'));
        $other = $this->block('B02');
        $this->post($this->url($other).'/batches', ['reserved_area_ha' => '1', 'return_to' => 'blocks'])->assertRedirect($this->url());
        $this->get($this->url().'?search=B02')->assertInertia(fn (Assert $page) => $page
            ->has('blocks.data', 1)->where('blocks.data.0.id', $other->id)->has('blocks.data.0.batches', 1)
            ->where('blocks.data.0.batches.0.subblock_label', 'A')->has('blocks.data.0.batches.0.stages', 6));
        $this->post($base, ['reserved_area_ha' => '1', 'return_to' => 'https://example.invalid'])->assertSessionHasErrors('return_to');
        $this->assertDatabaseCount('planting_cycles', 2);
        $this->get($this->url($block))->assertRedirect($this->url());
        $this->constraints();
    }

    public function test_block_area_units_are_converted_on_create_and_update(): void
    {
        $this->post($this->url(), ['code' => 'M01', 'name' => 'Blok Konversi', 'area_value' => '12500', 'area_unit' => 'm2'])->assertRedirect();
        $block = FarmBlock::where('code', 'M01')->firstOrFail();
        $this->assertSame('1.2500', $block->area_ha);

        $this->patch($this->url($block), ['area_value' => '0,5001', 'area_unit' => 'ha'])->assertRedirect();
        $this->assertSame('0.5001', $block->fresh()->area_ha);
        $this->patch($this->url($block), ['area_value' => '1', 'area_unit' => 'm2'])->assertRedirect();
        $this->assertSame('0.0001', $block->fresh()->area_ha);
    }

    public function test_block_area_units_reject_invalid_or_conflicting_inputs_without_writing(): void
    {
        foreach ([
            ['area_value' => '1.5', 'area_unit' => 'm2'],
            ['area_value' => '0', 'area_unit' => 'ha'],
            ['area_value' => '1.00001', 'area_unit' => 'ha'],
            ['area_value' => '50.000', 'area_unit' => 'm2'],
            ['area_value' => '1e4', 'area_unit' => 'm2'],
            ['area_value' => '1', 'area_unit' => 'acre'],
            ['area_value' => '1'],
            ['area_unit' => 'm2'],
            ['area_value' => '10000', 'area_unit' => 'm2', 'area_ha' => '2'],
        ] as $area) {
            $this->post($this->url(), ['code' => 'M01', 'name' => 'Blok Konversi', ...$area])->assertSessionHasErrors();
            $this->assertDatabaseMissing('farm_blocks', ['code' => 'M01']);
        }
        $this->post($this->url(), ['code' => 'M01', 'name' => 'Blok Konversi', 'area_value' => '100001', 'area_unit' => 'm2'])->assertSessionHasErrors('area_ha');
        $this->assertDatabaseMissing('farm_blocks', ['code' => 'M01']);
    }

    public function test_admin_crud_normalizes_code_and_starts_land_clearing(): void
    {
        $this->get($this->url().'/create')->assertInertia(fn (Assert $page) => $page->component('farmer/blocks/Form')->where('block', null));
        $this->post($this->url(), ['code' => ' b01 ', 'name' => 'Blok Utara', 'area_ha' => '3.1250'])->assertRedirect();
        $block = FarmBlock::firstOrFail();
        $this->assertSame('B01', $block->code);
        $this->assertSame('3.1250', $block->area_ha);
        $this->assertSame($this->admin->id, $block->created_by);
        $this->assertNotNull($block->land_clearing_started_at);
        $this->assertNull($block->land_clearing_completed_at);
        $this->get($this->url($block))->assertRedirect($this->url());
        $this->get($this->url())->assertInertia(fn (Assert $page) => $page->component('farmer/blocks/Index')->where('blocks.data.0.can.update', true)->has('blocks.data.0.batches', 0));
        $this->get($this->url($block).'/edit')->assertInertia(fn (Assert $page) => $page->component('farmer/blocks/Form')->where('block.id', $block->id));
        $this->patch($this->url($block), ['name' => 'Blok Timur'])->assertRedirect($this->url($block));
        $this->assertSame('Blok Timur', $block->refresh()->name);
        $this->delete($this->url($block))->assertRedirect($this->url());
        $this->assertDatabaseMissing('farm_blocks', ['id' => $block->id]);
        $this->constraints();
    }

    public static function roles(): array
    {
        return ['owner' => ['owner', false], 'admin' => ['admin', true], 'petugas' => ['petugas', false], 'superadmin' => ['superadmin', true]];
    }

    public function test_pic_petugas_can_crud_blocks_only_in_their_assigned_farm(): void
    {
        $block = $this->block();
        $otherFarm = $this->farm();
        $otherBlock = $this->block('OTHER', '1', $otherFarm);
        $foreignFarm = $this->farm(Team::factory()->create());
        $foreignBlock = $this->block('FOREIGN', '1', $foreignFarm);
        $actor = User::factory()->create(['role' => 'petugas']);
        $this->join($actor);
        $actor->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->farm->forceFill(['pic_user_id' => $actor->id])->save();
        $this->actingAs($actor);

        $this->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->where('can.create', true)->where('blocks.total', 1)->where('summary.total', 1)
            ->where('blocks.data.0.id', $block->id)
            ->where('blocks.data.0.can.update', true)->where('blocks.data.0.can.delete', true));
        $this->get($this->url($block))->assertRedirect($this->url());
        $this->get($this->url().'/create')->assertOk();
        $this->get($this->url($block).'/edit')->assertOk();
        $this->post($this->url(), ['code' => 'PIC01', 'name' => 'Blok Kelola PIC', 'area_ha' => '1'])->assertSessionHasNoErrors()->assertRedirect();
        $created = FarmBlock::where('code', 'PIC01')->firstOrFail();
        $this->assertSame($this->farm->id, $created->farm_id);
        $this->assertSame($actor->id, $created->created_by);
        $this->patch($this->url($created), ['name' => 'Blok PIC Diperbarui'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Blok PIC Diperbarui', $created->fresh()->name);
        $this->delete($this->url($created))->assertRedirect();
        $this->assertDatabaseMissing('farm_blocks', ['id' => $created->id]);

        foreach ([[$otherFarm, $otherBlock], [$foreignFarm, $foreignBlock]] as [$farm, $target]) {
            $this->get($this->url(null, $farm))->assertForbidden();
            $this->get($this->url($target, $farm))->assertForbidden();
            $this->get($this->url(null, $farm).'/create')->assertForbidden();
            $this->post($this->url(null, $farm), ['code' => 'DENIED', 'name' => 'Blok Terlarang', 'area_ha' => '1'])->assertForbidden();
            $this->patch($this->url($target, $farm), ['name' => 'Tidak Boleh'])->assertForbidden();
            $this->delete($this->url($target, $farm))->assertForbidden();
        }

        $this->post($this->url(), ['code' => 'FORGED', 'name' => 'Blok Input Palsu', 'area_ha' => '1',
            'farm_id' => $otherFarm->id, 'team_id' => $foreignFarm->team_id])->assertSessionHasErrors(['farm_id', 'team_id']);
        $this->assertSame([$block->id], FarmBlock::visibleTo($actor, $this->team)->pluck('id')->all());
        $this->constraints();
    }

    public function test_pic_status_does_not_replace_grants_membership_or_assignment_and_is_refreshed(): void
    {
        $block = $this->block();
        $actor = User::factory()->create(['role' => 'petugas']);
        $this->join($actor);
        $actor->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->farm->forceFill(['pic_user_id' => $actor->id])->save();
        $actor->load('assignedFarm');
        $this->assertTrue($actor->isPic());

        $roles = app(RolePermissions::class)->roles();
        foreach (['create', 'update', 'delete'] as $ability) {
            $subject = $ability === 'create' ? [FarmBlock::class, $this->farm] : $block;
            $this->assertTrue(Gate::forUser($actor)->allows($ability, $subject));
            unset($roles['petugas']["farm_blocks.$ability"]);
            config(['access.roles' => $roles]);
            $this->assertFalse(Gate::forUser($actor)->allows($ability, $subject));
            $roles['petugas']["farm_blocks.$ability"] = 'invalid';
            config(['access.roles' => $roles]);
            $this->assertFalse(Gate::forUser($actor)->allows($ability, $subject));
            $roles['petugas']["farm_blocks.$ability"] = 'own';
            config(['access.roles' => $roles]);
        }
        unset($roles['petugas']['farm_blocks.view']);
        config(['access.roles' => $roles]);
        $this->assertFalse(Gate::forUser($actor)->allows('update', $block));
        $this->assertFalse(Gate::forUser($actor)->allows('delete', $block));
        $this->assertSame(0, FarmBlock::visibleTo($actor, $this->team)->count());
        $roles['petugas']['farm_blocks.view'] = 'own';
        config(['access.roles' => $roles]);

        // Revocation must be observed even with the old farm relation in memory.
        Farm::whereKey($this->farm->id)->update(['pic_user_id' => null]);
        $this->assertFalse(Gate::forUser($actor)->allows('update', $block));
        $this->assertFalse(Gate::forUser($actor)->allows('delete', $block));
        $this->assertFalse(Gate::forUser($actor)->allows('create', [FarmBlock::class, $this->farm]));
        $this->assertTrue(Gate::forUser($actor)->allows('view', $block));
        Farm::whereKey($this->farm->id)->update(['pic_user_id' => $actor->id]);

        $actor->forceFill(['assigned_farm_id' => null])->save();
        $this->assertFalse(Gate::forUser($actor)->allows('update', $block));
        $this->assertSame(0, FarmBlock::visibleTo($actor, $this->team)->count());
        $actor->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->team->members()->detach($actor);
        $actor->unsetRelation('teams');
        $this->assertFalse(Gate::forUser($actor)->allows('update', $block));
        $this->assertSame(0, FarmBlock::visibleTo($actor, $this->team)->count());
        $this->constraints();
    }

    #[DataProvider('roles')]
    public function test_role_permissions_and_http_denials(string $role, bool $mutates): void
    {
        $block = $this->block();
        $actor = User::factory()->create(['role' => $role]);
        if ($role !== 'superadmin') {
            $this->join($actor);
        }
        $actor->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->actingAs($actor);
        $this->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.create', $mutates));
        $this->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page->where('blocks.data.0.can.update', $mutates));
        $this->assertSame($mutates, Gate::forUser($actor)->allows('create', [FarmBlock::class, $this->farm]));
        $this->assertFalse($role !== 'superadmin' && Gate::forUser($actor)->allows('configure', $block));
        $payload = ['code' => 'B02', 'name' => 'Blok Selatan', 'area_ha' => '1'];
        if ($mutates) {
            $this->post($this->url(), $payload)->assertRedirect();
            $this->patch($this->url($block), ['name' => 'Blok Revisi'])->assertRedirect();
            $this->delete($this->url($block))->assertRedirect();
        } else {
            $this->get($this->url().'/create')->assertForbidden();
            $this->get($this->url($block).'/edit')->assertForbidden();
            $this->post($this->url(), $payload)->assertForbidden();
            $this->patch($this->url($block), ['name' => 'Tidak Boleh'])->assertForbidden();
            $this->delete($this->url($block))->assertForbidden();
        }
        $this->constraints();
    }

    public function test_petugas_creates_independent_batches_without_approval_or_invented_planting_data(): void
    {
        $block = $this->block();
        $actor = User::factory()->create(['role' => 'petugas']);
        $this->join($actor);
        $actor->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->actingAs($actor)->post($this->url($block).'/batches', ['reserved_area_ha' => '1.2500', 'planned_planting_at' => '2026-11-15'])->assertRedirect($this->url());
        $first = PlantingCycle::firstOrFail();
        $this->assertSame(1, $first->batch_number);
        $this->assertSame('A', $first->subblock_label);
        $this->assertSame($actor->id, $first->created_by);
        $this->assertSame('planned', $first->status);
        $this->assertSame('0.0000', $first->planted_area_ha);
        $this->assertSame('2026-11-15', $first->planned_planting_at?->toDateString());
        $this->assertNull($first->planted_at);
        $this->assertNull($first->variety_id);
        $this->assertNull($block->refresh()->land_clearing_completed_at);
        $this->travel(1)->hours();
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1.5000'])->assertRedirect();
        $this->assertNull($block->refresh()->land_clearing_completed_at);
        $this->assertSame(2, PlantingCycle::count());
        $this->assertSame(12, PlantingCycleStage::count());
        $this->get($this->url())->assertInertia(fn (Assert $page) => $page->where('blocks.data.0.can.update', false)->where('blocks.data.0.can.createBatch', true)
            ->where('blocks.data.0.batches.0.stages.0.status', 'upcoming')->where('blocks.data.0.batches.0.stages.1.status', 'upcoming')
            ->where('blocks.data.0.batches.0.stages.2.status', 'upcoming')->where('blocks.data.0.batches.0.stages.2.started_at', null)
            ->where('blocks.data.0.batches.1.batch_number', 2)->where('blocks.data.0.batches.1.subblock_label', 'B'));
        $this->constraints();
    }

    public function test_cancelled_batch_continues_the_sequence_without_reusing_a_block_label(): void
    {
        $block = $this->block();
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1'])->assertRedirect();
        PlantingCycle::query()->update(['status' => 'cancelled']);
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '2'])->assertRedirect();
        $other = $this->block('B02');
        $this->post($this->url($other).'/batches', ['reserved_area_ha' => '1'])->assertRedirect();
        $this->assertSame([1, 2, 3], PlantingCycle::orderBy('batch_number')->pluck('batch_number')->all());
        $this->assertSame(['A', 'B', 'A'], PlantingCycle::orderBy('batch_number')->pluck('subblock_label')->all());
        $this->constraints();
    }

    public function test_capacity_guards_resize_deactivation_reactivation_and_history(): void
    {
        $block = $this->block('B01', '6');
        $other = $this->block('B02', '4');
        $this->post($this->url(), ['code' => 'B03', 'name' => 'Berlebih', 'area_ha' => '.0001'])->assertSessionHasErrors('area_ha');
        $this->patch($this->url($other), ['is_active' => false])->assertRedirect();
        $this->patch($this->url($block), ['area_ha' => '10'])->assertRedirect();
        $this->patch($this->url($other), ['is_active' => true])->assertSessionHasErrors('area_ha');
        $this->post($this->url($other).'/batches', ['reserved_area_ha' => '1'])->assertSessionHasErrors('reserved_area_ha');
        $this->patch('/admin/farm-management/'.$this->farm->id, ['total_area_ha' => '9.9999'])->assertSessionHasErrors('total_area_ha');
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '3'])->assertRedirect();
        $this->patch($this->url($block), ['area_ha' => '2.9999'])->assertSessionHasErrors('area_ha');
        $this->patch($this->url($block), ['is_active' => false])->assertSessionHasErrors('is_active');
        $this->delete($this->url($block))->assertSessionHasErrors('delete');
        $this->deleteJson('/api/kebun/'.$this->farm->id)->assertConflict();
        $this->constraints();
    }

    public function test_rejected_batch_creation_rolls_back_transition_and_sequence(): void
    {
        $block = $this->block('B01', '2');
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '2.0001'])->assertSessionHasErrors('reserved_area_ha');
        $this->assertNull($block->refresh()->land_clearing_completed_at);
        $this->assertDatabaseCount('planting_cycles', 0);
        $this->assertDatabaseCount('planting_cycle_stages', 0);
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1', 'subblock_label' => ' a '])->assertRedirect();
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1', 'subblock_label' => 'A'])->assertSessionHasErrors('subblock_label');
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1'])->assertRedirect();
        $this->assertSame([1, 2], PlantingCycle::orderBy('batch_number')->pluck('batch_number')->all());
        $this->constraints();
    }

    private function legacyCapacityBlock(): FarmBlock
    {
        $this->farm->forceFill(['total_area_ha' => '12.5000'])->save();
        $commodity = Commodity::create(['name' => 'Jagung Uji Kapasitas']);
        $variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Bisi Uji Kapasitas']);
        PlantingCycle::create(['farm_id' => $this->farm->id, 'variety_id' => $variety->id,
            'planted_at' => '2026-10-01', 'planted_area_ha' => '8.5000', 'status' => 'active']);

        return $this->block('B01', '10');
    }

    public function test_capacity_summary_explains_active_planting_and_rejects_overallocation(): void
    {
        $block = $this->legacyCapacityBlock();
        $this->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('blocks.data.0.batch_capacity.farm_total_ha', '12.5000')->where('blocks.data.0.batch_capacity.block_remaining_ha', '10.0000')
            ->where('blocks.data.0.batch_capacity.legacy_active_ha', '8.5000')->where('blocks.data.0.batch_capacity.farm_remaining_ha', '4.0000')
            ->where('blocks.data.0.batch_capacity.available_ha', '4.0000'));
        $this->post($this->url($block).'/batches', ['area_value' => '50000', 'area_unit' => 'm2'])
            ->assertSessionHasErrors(['reserved_area_ha' => 'Luas batch 5 ha melebihi sisa kapasitas kebun 4 ha. 8,5 ha masih digunakan tanam aktif di luar batch. Maksimal batch baru 4 ha.']);
        $this->assertSame(1, PlantingCycle::count());
        $this->assertNull($block->fresh()->land_clearing_completed_at);
        $this->post($this->url($block).'/batches', ['area_value' => '40000', 'area_unit' => 'm2'])->assertSessionHasNoErrors()->assertRedirect($this->url());
        $this->assertDatabaseHas('planting_cycles', ['farm_id' => $this->farm->id, 'reserved_area_ha' => '4.0000', 'status' => 'planned', 'planted_area_ha' => '0.0000']);
        $this->get($this->url())->assertInertia(fn (Assert $page) => $page->where('blocks.data.0.batch_capacity.available_ha', '0.0000'));
        $this->constraints();
    }

    public function test_unit_inputs_keep_exact_area_and_block_limit(): void
    {
        $block = $this->block('B01', '10');
        foreach ([['0,0001', 'ha', '0.0001'], ['49999', 'm2', '4.9999'], ['5', 'ha', '5.0000']] as [$value, $unit, $expected]) {
            $this->post($this->url($block).'/batches', ['area_value' => $value, 'area_unit' => $unit])->assertSessionHasNoErrors();
            $this->assertSame($expected, PlantingCycle::orderByDesc('batch_number')->firstOrFail()->reserved_area_ha);
        }
        $this->post($this->url($block).'/batches', ['area_value' => '1', 'area_unit' => 'm2'])
            ->assertSessionHasErrors(['reserved_area_ha' => 'Luas batch 0,0001 ha melebihi sisa kapasitas blok 0 ha. Maksimal batch baru 0 ha.']);
        $this->assertSame(3, PlantingCycle::count());
        $this->constraints();
    }

    public static function invalidAreaInputs(): array
    {
        return [
            [['area_value' => '0.5', 'area_unit' => 'm2']],
            [['area_value' => '1.00001', 'area_unit' => 'ha']],
            [['area_value' => '-1', 'area_unit' => 'ha']],
            [['area_value' => '5', 'area_unit' => 'meter']],
            [['area_value' => '5']], [['area_unit' => 'ha']],
            [['area_value' => '0', 'area_unit' => 'm2']],
            [['area_value' => '1e4', 'area_unit' => 'ha']],
            [['area_value' => ['5'], 'area_unit' => 'ha']],
            [['area_value' => '50000', 'area_unit' => 'm2', 'reserved_area_ha' => '1']],
        ];
    }

    #[DataProvider('invalidAreaInputs')]
    public function test_invalid_or_conflicting_unit_payloads_do_not_create_batches(array $payload): void
    {
        $block = $this->block();
        $this->post($this->url($block).'/batches', $payload)->assertSessionHasErrors();
        $this->assertDatabaseCount('planting_cycles', 0);
    }

    public function test_scoped_lists_totals_search_and_directory_do_not_expose_other_farms(): void
    {
        $this->block('B01', '2');
        $this->block('B02', '3');
        $other = $this->farm();
        $this->block('B03', '9', $other);
        $this->get($this->url().'?per_page=1')->assertInertia(fn (Assert $page) => $page->where('blocks.total', 2)->has('blocks.data', 1)->where('summary.total', 2)->where('summary.active_area_ha', '5.0000'));
        $this->get($this->url().'?search=B02')->assertInertia(fn (Assert $page) => $page->where('blocks.total', 1));
        $this->get($this->url().'?search=%25')->assertInertia(fn (Assert $page) => $page->where('blocks.total', 0));
        $actor = User::factory()->create(['role' => 'petugas']);
        $this->join($actor);
        $actor->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->actingAs($actor)->get('/blocks')->assertInertia(fn (Assert $page) => $page->where('farms.total', 1)->where('farms.data.0.id', $this->farm->id));
        $this->get($this->url(null, $other))->assertForbidden();
    }

    public function test_cross_team_nested_id_and_missing_assignment_are_denied(): void
    {
        $block = $this->block();
        $foreignTeam = Team::factory()->create();
        $foreign = $this->farm($foreignTeam);
        $foreignBlock = $this->block('B99', '5', $foreign);
        $this->get($this->url($foreignBlock, $foreign))->assertForbidden();
        $this->patch($this->url($foreignBlock, $foreign), ['name' => 'Bocor'])->assertForbidden();
        $this->post($this->url($foreignBlock, $foreign).'/batches', ['reserved_area_ha' => '1'])->assertForbidden();
        $this->get($this->url($foreignBlock))->assertNotFound();
        $this->post($this->url($foreignBlock).'/batches', ['reserved_area_ha' => '1'])->assertNotFound();
        $actor = User::factory()->create(['role' => 'petugas']);
        $this->join($actor);
        $this->actingAs($actor)->get($this->url($block))->assertForbidden();
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1'])->assertForbidden();
        $owner = User::factory()->create(['role' => 'owner']);
        $this->join($owner);
        $this->actingAs($owner)->post($this->url($block).'/batches', ['reserved_area_ha' => '1'])->assertForbidden();
        $this->assertDatabaseCount('planting_cycles', 0);
    }

    public function test_validation_rejects_forged_server_fields_and_invalid_values(): void
    {
        $block = $this->block();
        $this->post($this->url(), ['code' => 'b01', 'name' => 'Duplikat', 'area_ha' => '1'])->assertSessionHasErrors('code');
        $this->post($this->url(), ['code' => 'B 02', 'name' => '', 'area_ha' => '1.00001', 'farm_id' => $this->farm->id, 'created_by' => $this->admin->id])->assertSessionHasErrors(['code', 'name', 'area_ha', 'farm_id', 'created_by']);
        $this->patch($this->url($block), ['land_clearing_completed_at' => now()->toISOString(), 'current_stage' => 'harvest'])->assertSessionHasErrors(['land_clearing_completed_at', 'current_stage']);
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '-1', 'farm_id' => $this->farm->id, 'batch_number' => 99, 'status' => 'active', 'stages' => ['harvest'], 'planned_planting_at' => '15-11-2026'])->assertSessionHasErrors(['reserved_area_ha', 'farm_id', 'batch_number', 'status', 'stages', 'planned_planting_at']);
        $this->getJson($this->url().'?per_page=101')->assertUnprocessable();
    }

    public function test_default_deny_and_query_scope_reuse_matrix(): void
    {
        $block = $this->block();
        $this->assertFalse(Gate::forUser($this->admin)->allows('configure', $block));
        $this->assertFalse(Gate::forUser($this->admin)->allows('create', FarmBlock::class));
        $this->assertFalse(Gate::forUser($this->admin)->allows('create', [FarmBlock::class, $this->team]));
        $roles = app(RolePermissions::class)->roles();
        $roles['admin']['farm_blocks.view'] = 'invalid';
        config(['access.roles' => $roles]);
        $this->assertSame(0, FarmBlock::visibleTo($this->admin, $this->team)->count());
        $this->assertFalse(Gate::forUser($this->admin)->allows('update', $block));
        unset($roles['admin']['farm_blocks.view']);
        config(['access.roles' => $roles]);
        $this->get($this->url())->assertForbidden();
    }

    public function test_demo_seeder_is_idempotent_and_does_not_rewrite_existing_blocks(): void
    {
        $this->seed([DemoIdentitySeeder::class, DemoFarmSeeder::class, DemoFarmBlockSeeder::class]);
        $demo = Farm::where('name', 'Kebun Cileuksa')->firstOrFail();
        $this->assertSame('80.000', $demo->getAttribute('target_volume_ton'));
        $this->assertSame(Commodity::where('name', 'Jagung')->value('id'), $demo->getAttribute('commodity_id'));
        $ids = FarmBlock::where('farm_id', $demo->id)->pluck('id')->all();
        $batchIds = PlantingCycle::where('farm_id', $demo->id)->orderBy('batch_number')->pluck('id')->all();
        $this->assertCount(3, $batchIds);
        $this->assertSame(0, PlantingCycle::where('farm_id', $demo->id)->whereNull('block_id')->count());
        $this->assertSame(['harvested', 'active', 'planned'], PlantingCycle::where('farm_id', $demo->id)->orderBy('batch_number')->pluck('status')->all());
        $this->assertSame(18, PlantingCycleStage::whereIn('planting_cycle_id', $batchIds)->count());
        $this->seed(DemoFarmBlockSeeder::class);
        $this->assertSame($ids, FarmBlock::where('farm_id', $demo->id)->pluck('id')->all());
        $this->assertSame(2, count($ids));
        $this->assertSame($batchIds, PlantingCycle::where('farm_id', $demo->id)->orderBy('batch_number')->pluck('id')->all());
        $this->constraints();
    }

    public function test_guest_routes_require_login(): void
    {
        $block = $this->block();
        auth()->logout();
        $this->get('/blocks')->assertRedirect('/login');
        $this->get($this->url())->assertRedirect('/login');
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '1'])->assertRedirect('/login');
    }

    public function test_mapping_reference_is_optional_same_farm_and_keeps_measurement_snapshot(): void
    {
        $this->seed(BlockMappingActivityTypeSeeder::class);
        $block = $this->block();
        $petugas = User::factory()->create(['role' => 'petugas']);
        $this->join($petugas);
        $petugas->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $payload = ['work_date' => now('Asia/Jakarta')->format('Y-m-d'), 'start_time' => '08:00', 'end_time' => '10:00', 'worker_count' => 2,
            'activities' => [['type' => 'block_mapping', 'obstacle_status' => 'none',
                'block_mapping' => ['block_id' => $block->id, 'block_name' => 'Nama saat pengukuran', 'length_m' => '100', 'width_m' => '100']]]];
        $this->actingAs($petugas)->post('/kebun/'.$this->farm->id.'/daily-logs', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($this->admin)->patch($this->url($block), ['name' => 'Nama Baru'])->assertRedirect();
        $this->assertDatabaseHas('block_mapping_details', ['block_id' => $block->id, 'block_name' => 'Nama saat pengukuran']);
        $this->delete($this->url($block))->assertSessionHasErrors('delete');
        $other = $this->farm();
        $otherBlock = $this->block('B99', '1', $other);
        $payload['activities'][0]['block_mapping']['block_id'] = $otherBlock->id;
        $this->actingAs($petugas)->post('/kebun/'.$this->farm->id.'/daily-logs', $payload)->assertSessionHasErrors('activities.0.block_mapping.block_id');
        $this->assertDatabaseCount('daily_activity_logs', 1);
        $this->constraints();
    }

    public function test_database_constraints_reject_bypassed_capacity_cross_farm_and_incomplete_timeline(): void
    {
        $block = $this->block('B01', '10');
        $this->post($this->url($block).'/batches', ['reserved_area_ha' => '5'])->assertRedirect();
        $batch = PlantingCycle::firstOrFail();
        $other = $this->farm();
        $invalidWrites = [
            fn () => DB::table('farm_blocks')->where('id', $block->id)->update(['area_ha' => '10.0001']),
            fn () => DB::table('planting_cycles')->where('id', $batch->id)->update(['farm_id' => $other->id]),
            fn () => DB::table('planting_cycle_stages')->where('planting_cycle_id', $batch->id)->where('code', 'harvest')->delete(),
        ];
        foreach ($invalidWrites as $write) {
            DB::beginTransaction();
            try {
                $write();
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
                $this->fail('Database accepted an invalid block/batch write.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->getCode());
            } finally {
                DB::rollBack();
            }
        }
        $this->constraints();
    }
}
