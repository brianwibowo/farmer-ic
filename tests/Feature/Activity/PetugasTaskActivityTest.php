<?php

namespace Tests\Feature\Activity;

use App\Enums\TeamRole;
use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\HarvestDetail;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Integration coverage against the guarded PostgreSQL schema, not SQLite fixtures. */
class PetugasTaskActivityTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Team $team;

    private Farm $farm;

    private PlantingCycle $cycle;

    private PlantingCycleTask $task;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->team = Team::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->team->members()->attach($admin, ['role' => TeamRole::Member->value]);
        $admin->forceFill(['current_team_id' => $this->team->id])->save();
        $this->farm = new Farm(['name' => 'Kebun Pelaporan SOP', 'land_tenure' => 'owned', 'total_area_ha' => '10']);
        $this->farm->forceFill(['team_id' => $this->team->id, 'created_by' => $admin->id])->save();
        $block = app(FarmBlockService::class)->create($this->farm, $admin, $this->team, ['code' => 'B01', 'name' => 'Blok Utara', 'area_ha' => '5']);
        $this->cycle = app(FarmBlockService::class)->createBatch($block, $admin, $this->team, ['reserved_area_ha' => '2', 'planned_planting_at' => today()->subDays(21)->toDateString()]);
        $commodity = Commodity::create(['name' => 'Jagung']);
        $variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Bisi 18']);
        $this->cycle->forceFill(['planted_at' => today()->subDays(21), 'planted_area_ha' => '2', 'variety_id' => $variety->id, 'status' => 'active'])->save();
        $this->task = $this->newTask();
        $this->actor = User::factory()->create(['role' => 'petugas']);
        $this->team->members()->attach($this->actor, ['role' => TeamRole::Member->value]);
        $this->actor->forceFill(['current_team_id' => $this->team->id, 'assigned_farm_id' => $this->farm->id])->save();
        foreach (['irrigation', 'follow_up_fertilization', 'growth_check', 'pest_control', 'harvest'] as $code) {
            ActivityType::firstOrCreate(['code' => $code], ['name' => $code]);
        }
    }

    public function test_activity_sequence_enforces_prerequisites_and_allows_exceptions(): void
    {
        $this->actingAs($this->actor);
        $this->cycle->forceFill(['planted_at' => null, 'planted_area_ha' => 0, 'status' => 'planned'])->save();
        $base = [...$this->payload(), 'cycle_id' => $this->cycle->id];
        $blockBase = [...$this->payload(), 'block_id' => $this->cycle->plantingRound->block_id];
        $planting = ['work_date' => today()->toDateString(), 'start_time' => '07:00', 'worker_count' => 3,
            'variety_id' => $this->cycle->variety_id, 'area_ha' => 1, 'seed_name' => 'Bisi 18', 'quantity_kg' => 5,
            'photos' => [UploadedFile::fake()->image('tanam.jpg')]];
        $plantingUrl = '/petugas/subbloks/'.$this->cycle->id.'/penanaman';
        $this->post($plantingUrl, $planting)->assertSessionHasErrors('cycle');
        $soil = [...$blockBase, 'details' => ['area' => 100, 'area_unit' => 'm²', 'method' => 'Manual', 'depth_cm' => 10]];
        $this->post('/petugas/aktivitas/mandiri/loosening', $soil)->assertSessionHasErrors('block_id');
        $this->post('/petugas/aktivitas/mandiri/incident', [...$base, 'details' => ['area' => 100, 'area_unit' => 'm²', 'incident_category' => 'Cuaca', 'description' => 'Hujan lebat', 'severity' => 'Ringan']])->assertSessionHasNoErrors();
        $this->post('/petugas/aktivitas/mandiri/land_cleaning', [...$blockBase, 'details' => ['area' => 100, 'area_unit' => 'm²', 'method' => 'Manual']])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('farm_activities', ['block_id' => $blockBase['block_id'], 'planting_cycle_id' => null]);
        $reason = app(\App\Services\Activity\ActivitySequenceService::class)->blockedReason($this->actor, $this->cycle->refresh(), 'planting');
        $this->assertStringContainsString('pengolahan tanah', $reason);
        $this->post($plantingUrl, $planting)->assertSessionHasErrors('cycle');
        $this->post('/petugas/aktivitas/mandiri/loosening', $soil)->assertSessionHasNoErrors();
        $this->assertNull(app(\App\Services\Activity\ActivitySequenceService::class)->blockedReason($this->actor, $this->cycle->refresh(), 'planting'));
        $this->post($plantingUrl, $planting)->assertSessionHasNoErrors();
        $harvest = [...$base, 'details' => ['area' => 100, 'area_unit' => 'm²', 'weight' => 500, 'weight_unit' => 'kg']];
        $this->post('/petugas/aktivitas/mandiri/harvest', $harvest)->assertSessionHasErrors('cycle_id');
        $this->task->update(['status' => 'in_progress']);
        $this->post('/petugas/aktivitas/mandiri/harvest', $harvest)->assertSessionHasErrors('cycle_id');
        $this->task->update(['status' => 'skipped']);
        $this->post('/petugas/aktivitas/mandiri/harvest', $harvest)->assertSessionHasNoErrors();
    }

    public function test_sop_harvest_is_blocked_until_maintenance_tasks_finish(): void
    {
        $harvest = $this->newTask(['planting_cycle_stage_id' => $this->cycle->stages()->where('code', 'harvest')->firstOrFail()->id,
            'activity_category' => 'harvest', 'activity_subcategory' => null, 'name' => 'Panen']);
        $data = [...$this->payload(), 'details' => ['area_ha' => 1, 'weight_ton' => 1, 'method' => 'Manual']];
        $url = '/petugas/tasks/'.$harvest->id.'/aktivitas';
        $this->actingAs($this->actor)->post($url, $data)->assertSessionHasErrors('task');
        $this->get('/petugas/tasks/'.$harvest->id)->assertInertia(fn (Assert $page) => $page->where('canRecord', false)->where('blockedReason', 'Selesaikan seluruh tugas SOP perawatan atau minta Admin melewati tugas yang tidak diperlukan sebelum panen.'));
        $this->task->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        $this->post($url, $data)->assertSessionHasNoErrors();
    }

    public function test_block_preparation_is_shared_with_subblocks_but_not_other_blocks(): void
    {
        $admin = User::findOrFail($this->farm->created_by);
        $block = $this->cycle->plantingRound->block;
        $other = app(FarmBlockService::class)->create($this->farm, $admin, $this->team, ['code' => 'B02', 'name' => 'Blok Selatan', 'area_ha' => '4']);
        $second = app(FarmBlockService::class)->createBatch($block, $admin, $this->team, ['reserved_area_ha' => '1', 'planned_planting_at' => today()->toDateString()]);
        $sequence = app(\App\Services\Activity\ActivitySequenceService::class);
        $this->actingAs($this->actor);
        $timeline = fn ($cycle) => collect(app(\App\Services\Activity\BlockPreparationTimeline::class)->forCycles($this->actor, $this->team, collect([$cycle]))[$cycle->id]);
        $stale = $second->stages()->where('code', 'land_clearing')->firstOrFail();
        $stale->forceFill(['status' => 'completed', 'started_at' => now(), 'completed_at' => now()])->save();
        $this->assertSame('upcoming', $timeline($second)['land_clearing']['status']);
        $this->assertSame('upcoming', $timeline($second)['soil_preparation']['status']);
        $this->assertSame('completed', $stale->fresh()->status);
        $this->post('/petugas/aktivitas/mandiri/land_cleaning', [...$this->payload(), 'block_id' => $block->id,
            'details' => ['area' => 100, 'area_unit' => 'm²', 'method' => 'Manual']])->assertSessionHasNoErrors();
        $this->assertSame('completed', $timeline($second)['land_clearing']['status']);
        $this->assertSame('upcoming', $timeline($second)['soil_preparation']['status']);
        $this->assertNotNull($sequence->blockReason($this->actor, $other, 'loosening'));
        $this->post('/petugas/aktivitas/mandiri/loosening', [...$this->payload(), 'block_id' => $block->id,
            'details' => ['area' => 100, 'area_unit' => 'm²', 'method' => 'Manual', 'depth_cm' => 10]])->assertSessionHasNoErrors();
        $this->assertSame('completed', $timeline($second)['soil_preparation']['status']);
        $overview = app(FarmBlockService::class)->overview($this->farm, $admin, $this->team)->firstWhere('id', $block->id);
        $stages = collect(collect($overview->getAttribute('batches'))->firstWhere('id', $second->id)['stages'])->keyBy('code');
        $this->assertSame('completed', $stages['land_clearing']['status']);
        $this->assertSame('completed', $stages['soil_preparation']['status']);
        $this->cycle->forceFill(['planted_at' => null, 'planted_area_ha' => 0, 'status' => 'planned'])->save();
        $this->assertNull($sequence->blockedReason($this->actor, $this->cycle, 'planting'));
        $this->assertNull($sequence->blockedReason($this->actor, $second, 'planting'));
        $this->assertNotNull($sequence->blockReason($this->actor, $other, 'planting'));
    }

    public function test_petugas_cannot_report_preparation_on_another_farm_block(): void
    {
        $admin = User::findOrFail($this->farm->created_by);
        $otherFarm = new Farm(['name' => 'Kebun Selatan', 'land_tenure' => 'owned', 'total_area_ha' => '5']);
        $otherFarm->forceFill(['team_id' => $this->team->id, 'created_by' => $admin->id])->save();
        $block = app(FarmBlockService::class)->create($otherFarm, $admin, $this->team, ['code' => 'S01', 'name' => 'Blok Selatan', 'area_ha' => '4']);
        $this->actingAs($this->actor)->post('/petugas/aktivitas/mandiri/land_cleaning', [...$this->payload(), 'block_id' => $block->id,
            'details' => ['area' => 100, 'area_unit' => 'm²', 'method' => 'Manual']])->assertForbidden();
        $this->assertDatabaseCount('farm_activities', 0);
    }

    public function test_database_rejects_activity_block_that_disagrees_with_cycle(): void
    {
        $admin = User::findOrFail($this->farm->created_by);
        $other = app(FarmBlockService::class)->create($this->farm, $admin, $this->team, ['code' => 'B02', 'name' => 'Blok Selatan', 'area_ha' => '4']);
        $this->actingAs($this->actor)->post('/petugas/aktivitas/mandiri/incident', [...$this->payload(), 'cycle_id' => $this->cycle->id,
            'details' => ['area' => 100, 'area_unit' => 'm²', 'incident_category' => 'Cuaca', 'description' => 'Hujan lebat', 'severity' => 'Ringan']])->assertSessionHasNoErrors();
        $activity = FarmActivity::firstOrFail();
        $this->assertSame($this->cycle->plantingRound->block_id, $activity->getAttribute('block_id'));
        $this->expectException(QueryException::class);
        DB::table('farm_activities')->where('id', $activity->id)->update(['block_id' => $other->id]);
    }

    public function test_no_maintenance_tasks_do_not_block_harvest(): void
    {
        $this->task->delete();
        $this->actingAs($this->actor)->post('/petugas/aktivitas/mandiri/harvest', [...$this->payload(), 'cycle_id' => $this->cycle->id,
            'details' => ['area' => 100, 'area_unit' => 'm²', 'weight' => 500, 'weight_unit' => 'kg']])->assertSessionHasNoErrors();
    }

    public function test_partial_harvests_accumulate_and_exact_remaining_area_is_allowed(): void
    {
        $this->task->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        $this->actingAs($this->actor);
        $base = [...$this->payload(), 'cycle_id' => $this->cycle->id];

        $this->post('/petugas/aktivitas/mandiri/harvest', [...$base, 'details' => [
            'area' => '0.5', 'area_unit' => 'Ha', 'weight' => '1000', 'weight_unit' => 'kg',
        ]])->assertSessionHasNoErrors();
        $this->post('/petugas/aktivitas/mandiri/harvest', [...$base, 'details' => [
            'area' => '1.5', 'area_unit' => 'Ha', 'weight' => '2000', 'weight_unit' => 'kg',
        ]])->assertSessionHasNoErrors();

        $this->assertSame(2.0, (float) HarvestDetail::query()->sum('harvested_area_ha'));

        $this->post('/petugas/aktivitas/mandiri/harvest', [...$base, 'details' => [
            'area' => '1', 'area_unit' => 'm²', 'weight' => '1', 'weight_unit' => 'kg',
        ]])->assertSessionHasErrors('details.area');

        $this->assertDatabaseCount('harvest_details', 2);
    }

    public function test_sop_harvest_rejects_area_above_remaining_planted_area(): void
    {
        $this->task->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        $harvest = $this->newHarvestTask();
        $this->actingAs($this->actor);
        $url = '/petugas/tasks/'.$harvest->id.'/aktivitas';

        $this->post($url, [...$this->payload(), 'details' => [
            'area_ha' => '1.2500', 'weight_ton' => '1.500', 'method' => 'Manual',
        ]])->assertSessionHasNoErrors();

        $this->post($url, [...$this->payload(), 'details' => [
            'area_ha' => '0.7501', 'weight_ton' => '1.000', 'method' => 'Manual',
        ]])->assertSessionHasErrors('details.area_ha');
        $this->assertDatabaseCount('harvest_details', 1);

        $this->post($url, [...$this->payload(), 'details' => [
            'area_ha' => '0.7500', 'weight_ton' => '1.000', 'method' => 'Manual',
        ]])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('harvest_details', 2);
        $this->assertSame(2.0, (float) HarvestDetail::query()->sum('harvested_area_ha'));
    }

    public function test_voluntary_and_sop_harvest_share_the_cumulative_limit(): void
    {
        $this->task->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        $harvest = $this->newHarvestTask();
        $this->actingAs($this->actor);

        $this->post('/petugas/aktivitas/mandiri/harvest', [...$this->payload(), 'cycle_id' => $this->cycle->id, 'details' => [
            'area' => '1.75', 'area_unit' => 'Ha', 'weight' => '3000', 'weight_unit' => 'kg',
        ]])->assertSessionHasNoErrors();

        $taxonomy = [...$this->payload(), 'schema' => 'taxonomy', 'details' => [
            'area' => '0.2501', 'area_unit' => 'Ha', 'weight' => '100', 'weight_unit' => 'kg', 'method' => 'Manual',
        ]];
        $url = '/petugas/tasks/'.$harvest->id.'/aktivitas';

        $this->post($url, $taxonomy)->assertSessionHasErrors('details.area');
        $this->assertDatabaseCount('harvest_details', 1);

        $taxonomy['details']['area'] = '0.25';
        $this->post($url, $taxonomy)->assertSessionHasNoErrors();
        $this->assertSame(2.0, (float) HarvestDetail::query()->sum('harvested_area_ha'));
    }

    public function test_all_voluntary_categories_persist_details_and_history_without_completing_tasks(): void
    {
        // This category persistence test isolates typed detail writes; sequence is
        // exercised by test_activity_sequence_enforces_prerequisites_and_allows_exceptions.
        $this->mock(\App\Services\Activity\ActivitySequenceService::class, function ($mock): void {
            $mock->shouldReceive('ensureAllowed')->andReturnNull()->byDefault();
            $mock->shouldReceive('blockedReason')->andReturnNull()->byDefault();
            $mock->shouldReceive('blockReason')->andReturnNull()->byDefault();
        });
        $area = ['area' => 1000, 'area_unit' => 'm²'];
        $cases = [
            'land_cleaning' => $area + ['method' => 'Manual'],
            'loosening' => $area + ['method' => 'Cultivator', 'depth_cm' => 10],
            'beds' => ['bed_count' => 10, 'length_m' => 10, 'width_m' => 1],
            'liming' => $area + ['material' => 'Dolomit', 'quantity' => 2, 'quantity_unit' => 'sak', 'bag_size' => 25, 'method' => 'Tabur'],
            'base_fertilization' => $area + ['fertilizer' => 'Kompos', 'quantity' => 1, 'quantity_unit' => 'ton', 'method' => 'Benam'],
            'irrigation' => $area + ['water_source' => 'Sumur bor', 'method' => 'Pompa', 'pump' => 'Pompa diesel', 'duration_hours' => 2],
            'weeding' => $area + ['method' => 'Cangkul'],
            'follow_up_fertilization' => $area + ['fertilizer' => 'NPK', 'quantity' => 2, 'quantity_unit' => 'liter', 'method' => 'Kocor'],
            'growth_check' => ['phase' => 'Vegetatif', 'height_cm' => 35, 'pest' => 'Aman', 'disease' => 'Ringan'],
            'pest_control' => $area + ['control_type' => 'Insektisida', 'target' => 'Hama', 'product' => 'Pengendali hayati', 'dosage' => 2, 'water_liters' => 15, 'tank_count' => 2, 'method' => 'Spot'],
            'harvest' => $area + ['weight' => 500, 'weight_unit' => 'kg'],
            'incident' => $area + ['incident_category' => 'Cuaca', 'description' => 'Tanaman rebah akibat angin.', 'severity' => 'Sedang', 'immediate_action' => 'Menopang batang.'],
            'other' => ['description' => 'Membersihkan alat kerja.'],
        ];
        $this->actingAs($this->actor);
        foreach ($cases as $code => $details) {
            $this->get('/petugas/aktivitas/mandiri/'.$code)->assertOk();
            $location = \App\Services\Activity\ActivitySequenceService::isBlockPreparation($code)
                ? ['block_id' => $this->cycle->plantingRound->block_id]
                : ['cycle_id' => $code === 'other' ? null : $this->cycle->id];
            $this->post('/petugas/aktivitas/mandiri/'.$code, [...$this->payload(), ...$location, 'end_time' => null,
                'details' => $details])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertDatabaseCount('farm_activities', 13);
        $this->assertDatabaseCount('daily_activity_logs', 1);
        $this->assertDatabaseHas('daily_activity_logs', ['farm_id' => $this->farm->id, 'end_time' => null]);
        $this->assertDatabaseHas('harvest_details', ['gross_weight_ton' => '0.500']);
        $this->assertDatabaseHas('fertilization_details', ['quantity_kg' => null]);
        $this->assertSame('pending', $this->task->refresh()->status);
        $this->assertSame(0, FarmActivity::whereNotNull('planting_cycle_task_id')->count());
        $this->get('/petugas/laporan')->assertInertia(fn (Assert $page) => $page->component('petugas/Laporan')->where('reports.total', 13));
        $activity = FarmActivity::latest('activity_date')->firstOrFail();
        $this->get('/petugas/laporan/'.$activity->id)->assertInertia(fn (Assert $page) => $page->component('petugas/ReportShow')->has('log.activities', 1));
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_voluntary_conditional_fields_permissions_and_history_scope(): void
    {
        $this->actingAs($this->actor);
        $data = [...$this->payload(), 'cycle_id' => $this->cycle->id,
            'details' => ['area' => 500, 'area_unit' => 'm²', 'water_source' => 'Sumur bor', 'method' => 'Pompa', 'duration_hours' => 1]];
        $this->post('/petugas/aktivitas/mandiri/irrigation', $data)->assertSessionHasErrors('details.pump');
        $data['details']['pump'] = 'Pompa diesel';
        $this->post('/petugas/aktivitas/mandiri/irrigation', $data)->assertSessionHasNoErrors();
        $activity = FarmActivity::firstOrFail();
        $other = new Farm(['name' => 'Kebun lain', 'land_tenure' => 'owned', 'total_area_ha' => '4']);
        $other->forceFill(['team_id' => $this->team->id, 'created_by' => $this->actor->id])->save();
        $this->actor->forceFill(['assigned_farm_id' => $other->id])->save();
        $this->actor->unsetRelation('assignedFarm');
        $this->post('/petugas/aktivitas/mandiri/irrigation', $data)->assertForbidden();
        $this->get('/petugas/laporan/'.$activity->id)->assertForbidden();
        $this->get('/petugas/laporan')->assertInertia(fn (Assert $page) => $page->where('reports.total', 0));
        $this->get('/petugas/aktivitas/mandiri/block_mapping')->assertForbidden();
        $roles = config('access.roles');
        unset($roles['petugas']['activity_photos.create']);
        config(['access.roles' => $roles]);
        $this->get('/petugas/aktivitas/mandiri/other')->assertForbidden();
        $this->assertDatabaseCount('farm_activities', 1);
    }

    public function test_taxonomy_sop_form_persists_units_and_starts_task(): void
    {
        $data = [...$this->payload(), 'schema' => 'taxonomy', 'end_time' => null,
            'details' => ['area' => 1000, 'area_unit' => 'm²', 'water_source' => 'Irigasi', 'method' => 'Irigasi parit', 'duration_hours' => 2]];
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('irrigation_details', ['water_source' => 'Irigasi']);
        $this->assertSame('in_progress', $this->task->refresh()->status);
        $this->get('/petugas/laporan?source=sop')->assertInertia(fn (Assert $page) => $page->where('reports.total', 1));
        $this->get('/petugas/laporan?source=mandiri')->assertInertia(fn (Assert $page) => $page->where('reports.total', 0));
        $data['details']['area'] = 0.0001;
        $data['details']['area_unit'] = 'Ha';
        $this->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $data)->assertSessionHasNoErrors();
        $data['details']['area'] = 0.5;
        $data['details']['area_unit'] = 'm²';
        $this->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $data)->assertSessionHasErrors('details.area');
    }

    private function newTask(array $data = []): PlantingCycleTask
    {
        return PlantingCycleTask::create([
            'planting_cycle_id' => $this->cycle->id,
            'planting_cycle_stage_id' => $this->cycle->stages()->where('code', 'maintenance')->firstOrFail()->id,
            'activity_category' => 'maintenance', 'activity_subcategory' => 'irrigation', 'name' => 'Pengairan awal',
            'offset_days' => 7, 'tolerance_days' => 2, 'scheduled_date' => today(), 'due_date' => today()->addDays(2),
            'status' => 'pending', 'is_required' => true, ...$data,
        ]);
    }

    private function newHarvestTask(): PlantingCycleTask
    {
        return $this->newTask([
            'planting_cycle_stage_id' => $this->cycle->stages()->where('code', 'harvest')->firstOrFail()->id,
            'activity_category' => 'harvest',
            'activity_subcategory' => null,
            'name' => 'Panen',
        ]);
    }

    private function payload(): array
    {
        return [
            'work_date' => now('Asia/Jakarta')->toDateString(), 'start_time' => '07:00', 'end_time' => '10:00', 'worker_count' => 3,
            'notes' => 'Pengairan merata tanpa genangan.',
            'details' => ['area_ha' => '1.0000', 'water_source' => 'Sumur bor', 'method' => 'Pompa', 'pump' => 'Pompa diesel', 'duration_hours' => '3.00'],
            'photos' => [UploadedFile::fake()->image('pengairan.jpg')],
        ];
    }

    public function test_real_tasks_and_form_use_persisted_category_and_location(): void
    {
        $this->actingAs($this->actor)->get('/petugas/tasks')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('petugas/Aktivitas')->has('tasks', 1)->where('tasks.0.id', $this->task->id)
            ->where('tasks.0.activity_subcategory', 'irrigation')->where('tasks.0.subblok.farm.id', $this->farm->id)
            ->where('tasks.0.can.record', true)->where('summary.today', 1)->missing('isFixture'));
        $this->get('/petugas/tasks/'.$this->task->id.'/aktivitas/buat')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('petugas/ActivityCreate')->where('task.id', $this->task->id)->has('fields', 6));
        $this->get('/petugas/aktivitas/buat')->assertRedirect('/petugas/aktivitas');
    }

    public function test_report_persists_log_details_private_photo_and_link_without_completing_task(): void
    {
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())
            ->assertRedirect('/petugas/tasks/'.$this->task->id);
        $activity = FarmActivity::firstOrFail();
        $this->assertSame($this->task->id, $activity->getAttribute('planting_cycle_task_id'));
        $this->assertSame($this->actor->id, $activity->performed_by);
        $this->assertDatabaseHas('farm_activities', ['id' => $activity->id, 'worker_count' => 3]);
        $this->assertDatabaseHas('irrigation_details', ['activity_id' => $activity->id, 'water_source' => 'Sumur bor']);
        $this->assertSame('in_progress', $this->task->refresh()->status);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $photo = ActivityPhoto::firstOrFail();
        Storage::disk('local')->assertExists($photo->image_url);
        $this->get('/petugas/aktivitas/'.$activity->id.'/foto/'.$photo->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/petugas/tasks/'.$this->task->id)->assertInertia(fn (Assert $page) => $page->has('reports', 1)->where('task.linked_activities_count', 1));
    }

    public function test_task_completion_requires_a_report_and_marks_the_task_completed(): void
    {
        $url = '/petugas/tasks/'.$this->task->id.'/selesai';
        $this->actingAs($this->actor)->post($url, ['note' => 'Belum ada laporan.'])
            ->assertSessionHasErrors('status');
        $this->assertSame('pending', $this->task->refresh()->status);

        $this->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertSessionHasNoErrors();
        $this->post($url, ['note' => 'Pekerjaan selesai dan lahan sudah diperiksa.'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/petugas/tasks/'.$this->task->id);

        $task = $this->task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertSame($this->actor->id, $task->getAttribute('completed_by'));
        $this->assertSame('Pekerjaan selesai dan lahan sudah diperiksa.', $task->getAttribute('completion_note'));

        $this->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertForbidden();
    }

    public function test_completion_rejects_a_second_action_and_another_assigned_farm(): void
    {
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertSessionHasNoErrors();
        $url = '/petugas/tasks/'.$this->task->id.'/selesai';
        $this->post($url, ['note' => 'Selesai.'])->assertSessionHasNoErrors();
        $this->post($url, ['note' => 'Ulangi.'])->assertSessionHasErrors('status');

        $other = new Farm(['name' => 'Kebun lain', 'land_tenure' => 'owned', 'total_area_ha' => '3']);
        $other->forceFill(['team_id' => $this->team->id, 'created_by' => $this->actor->id])->save();
        $this->actor->forceFill(['assigned_farm_id' => $other->id])->save();
        $this->post($url, ['note' => 'Tidak boleh.'])->assertForbidden();
    }

    public function test_two_officers_share_one_header_without_overwriting_daily_information(): void
    {
        $data = $this->payload();
        $data['daily_notes'] = 'Tim gabungan bekerja di beberapa subblok.';
        $url = '/petugas/tasks/'.$this->task->id.'/aktivitas';
        $this->actingAs($this->actor)->post($url, $data)->assertSessionHasNoErrors();
        $first = \App\Models\DailyActivityLog::firstOrFail();
        $other = User::factory()->create(['role' => 'petugas']);
        $this->team->members()->attach($other, ['role' => TeamRole::Member->value]);
        $other->forceFill(['current_team_id' => $this->team->id, 'assigned_farm_id' => $this->farm->id])->save();
        $data = $this->payload();
        $data['worker_count'] = 99;
        $data['start_time'] = '08:00';
        $data['daily_notes'] = 'Tidak boleh menimpa header pertama.';
        $this->actingAs($other)->post($url, $data)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('daily_activity_logs', 1);
        $this->assertSame($this->actor->id, $first->refresh()->recorded_by);
        $this->assertSame('Tim gabungan bekerja di beberapa subblok.', $first->notes);
        $this->assertSame([1, 2], FarmActivity::orderBy('position')->pluck('position')->all());
        $this->assertSame([3, 99], FarmActivity::orderBy('position')->pluck('worker_count')->all());
        $this->assertSame([$this->actor->id, $other->id], FarmActivity::orderBy('position')->pluck('performed_by')->all());
        $this->get('/petugas/laporan/harian')->assertInertia(fn (Assert $page) => $page->component('petugas/DailyReport')->has('log.activities', 2)->where('log.activities.0.worker_count', 3)->where('log.activities.1.worker_count', 99)->where('canUpdate', true));
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_existing_header_needs_no_repeated_hours_and_new_date_requires_daily_information(): void
    {
        $url = '/petugas/tasks/'.$this->task->id.'/aktivitas';
        $this->actingAs($this->actor)->post($url, $this->payload())->assertSessionHasNoErrors();
        $data = $this->payload();
        unset($data['start_time'], $data['end_time']);
        $this->post($url, $data)->assertSessionHasNoErrors();
        $data['photos'] = [UploadedFile::fake()->image('pengairan-lain.jpg')];
        $data['work_date'] = now('Asia/Jakarta')->subDay()->toDateString();
        $this->post($url, $data)->assertSessionHasErrors(['start_time']);
        $this->assertDatabaseCount('daily_activity_logs', 1);
        $this->assertDatabaseCount('farm_activities', 2);
    }

    public function test_header_edit_is_separate_shared_and_keeps_original_creator_and_activities(): void
    {
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertSessionHasNoErrors();
        $log = \App\Models\DailyActivityLog::firstOrFail();
        $edit = '/kebun/'.$this->farm->id.'/daily-logs/'.$log->id;
        $data = ['start_time' => '06:00', 'end_time' => '17:00', 'daily_notes' => 'Ringkasan harian diperbarui.'];
        $this->patch($edit, $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('06:00', substr($log->refresh()->start_time, 0, 5));
        $this->assertSame('Ringkasan harian diperbarui.', $log->notes);
        $this->assertSame($this->actor->id, $log->recorded_by);
        $this->assertDatabaseCount('farm_activities', 1);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->team->members()->attach($admin, ['role' => TeamRole::Member->value]);
        $admin->forceFill(['current_team_id' => $this->team->id])->save();
        $this->actingAs($admin)->patch($edit, ['start_time' => '06:30', 'end_time' => '17:30', 'daily_notes' => 'Ringkasan admin.'])->assertSessionHasNoErrors();
        $this->assertSame('06:30', substr($log->refresh()->start_time, 0, 5));
        $owner = User::factory()->create(['role' => 'owner']);
        $this->team->members()->attach($owner, ['role' => TeamRole::Member->value]);
        $this->actingAs($owner)->patch($edit, $data)->assertForbidden();
        $this->get($edit)->assertOk()->assertInertia(fn (Assert $page) => $page->where('canUpdate', false)->has('log.activities', 1));
        $this->actingAs($this->actor)->patch($edit, [...$data, 'work_date' => '2026-01-01', 'recorded_by' => $admin->id])->assertSessionHasErrors(['work_date', 'recorded_by']);
    }

    public function test_daily_context_and_report_photos_are_farm_scoped(): void
    {
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertSessionHasNoErrors();
        $date = now('Asia/Jakarta')->toDateString();
        $context = '/kebun/'.$this->farm->id.'/daily-logs/context?work_date='.$date;
        $this->getJson($context)->assertOk()->assertJsonPath('data.start_time', '07:00');
        $photo = ActivityPhoto::firstOrFail();
        $url = '/kebun/'.$this->farm->id.'/daily-logs/activities/'.$photo->activity_id.'/photos/'.$photo->id;
        $this->get($url)->assertOk();
        $other = new Farm(['name' => 'Kebun tanpa akses', 'land_tenure' => 'owned', 'total_area_ha' => '3']);
        $other->forceFill(['team_id' => $this->team->id, 'created_by' => $this->actor->id])->save();
        $this->getJson('/kebun/'.$other->id.'/daily-logs/context?work_date='.$date)->assertForbidden();
        $this->actor->forceFill(['assigned_farm_id' => $other->id])->save();
        $this->actor->unsetRelation('assignedFarm');
        $this->getJson($context)->assertForbidden();
        $this->get($url)->assertForbidden();
        $this->get('/petugas/laporan/harian')->assertInertia(fn (Assert $page) => $page->where('log', null));
    }

    public function test_database_unique_constraint_prevents_a_second_header_for_the_same_farm_date(): void
    {
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertSessionHasNoErrors();
        $log = \App\Models\DailyActivityLog::firstOrFail();
        try {
            DB::transaction(fn () => \App\Models\DailyActivityLog::create($log->only(['farm_id', 'work_date', 'recorded_by', 'start_time', 'end_time', 'notes'])));
            $this->fail('Expected unique farm/date constraint.');
        } catch (\Illuminate\Database\QueryException $error) {
            $this->assertSame('23505', (string) $error->getCode());
        }
        $this->assertDatabaseCount('daily_activity_logs', 1);
    }

    public function test_foreign_tenant_and_read_only_grants_cannot_edit_or_expose_a_daily_header(): void
    {
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertSessionHasNoErrors();
        $log = \App\Models\DailyActivityLog::firstOrFail();
        $edit = '/kebun/'.$this->farm->id.'/daily-logs/'.$log->id;
        $data = ['start_time' => '06:00', 'end_time' => '17:00'];
        $roles = config('access.roles');
        unset($roles['petugas']['daily_activity_logs.update']);
        config(['access.roles' => $roles]);
        $this->patch($edit, $data)->assertForbidden();
        $foreign = Team::factory()->create(['is_personal' => false]);
        $this->team->members()->detach($this->actor);
        $foreign->members()->attach($this->actor, ['role' => TeamRole::Member->value]);
        $this->actor->forceFill(['current_team_id' => $foreign->id])->save();
        $this->actor->unsetRelation('teams')->unsetRelation('currentTeam')->unsetRelation('assignedFarm');
        $this->getJson('/kebun/'.$this->farm->id.'/daily-logs/context?work_date='.$log->work_date->toDateString())->assertForbidden();
        $this->patch($edit, $data)->assertForbidden();
        $this->get('/petugas/aktivitas')->assertForbidden();
        $this->assertSame(3, FarmActivity::firstOrFail()->worker_count);
    }

    public function test_forged_context_and_invalid_details_or_uploads_are_rejected_atomically(): void
    {
        $data = $this->payload();
        $data['farm_id'] = $this->farm->id;
        $data['activity_subcategory'] = 'harvest';
        $data['details']['weight_ton'] = '2';
        $data['photos'] = [UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf')];
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $data)
            ->assertSessionHasErrors(['farm_id', 'activity_subcategory', 'details', 'photos.0']);
        $this->assertDatabaseCount('farm_activities', 0);
        $this->assertDatabaseCount('daily_activity_logs', 0);
    }

    public function test_other_assigned_farm_and_missing_assignment_deny_list_form_write_and_photos(): void
    {
        $other = new Farm(['name' => 'Kebun lain', 'land_tenure' => 'owned', 'total_area_ha' => '3']);
        $other->forceFill(['team_id' => $this->team->id, 'created_by' => $this->actor->id])->save();
        $this->actor->forceFill(['assigned_farm_id' => $other->id])->save();
        $this->actingAs($this->actor)->get('/petugas/tasks')->assertInertia(fn (Assert $page) => $page->has('tasks', 0)->where('summary.today', 0));
        $this->get('/petugas/tasks/'.$this->task->id)->assertForbidden();
        $this->get('/petugas/tasks/'.$this->task->id.'/aktivitas/buat')->assertForbidden();
        $this->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertForbidden();
        $this->actor->forceFill(['assigned_farm_id' => null])->save();
        $this->actor->unsetRelation('assignedFarm');
        $this->get('/petugas/tasks')->assertInertia(fn (Assert $page) => $page->has('tasks', 0)->where('farm', null));
    }

    public function test_filters_have_unfiltered_scoped_counts_and_closed_tasks_cannot_receive_reports(): void
    {
        $this->task->update(['scheduled_date' => today()->subDays(3), 'due_date' => today()->subDay()]);
        $this->newTask(['status' => 'skipped', 'skip_reason' => 'Tidak relevan.']);
        $this->actingAs($this->actor)->get('/petugas/tasks?status=completed')->assertInertia(fn (Assert $page) => $page
            ->has('tasks', 1)->where('summary.overdue', 1)->where('summary.completed', 1));
        $this->task->update(['status' => 'completed', 'completed_at' => now()]);
        $this->get('/petugas/tasks/'.$this->task->id.'/aktivitas/buat')->assertForbidden();
        $this->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertForbidden();
    }

    public function test_reference_grants_allow_task_metadata_but_not_reference_mutation(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($this->actor)->allows('viewAny', [ActivityType::class, $this->team]));
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($this->actor)->allows('viewAny', [CropVariety::class, $this->team]));
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($this->actor)->allows('update', ActivityType::firstOrFail()));
    }

    public function test_all_generated_task_kinds_persist_correct_details_and_pass_database_integrity(): void
    {
        $this->mock(\App\Services\Activity\ActivitySequenceService::class, function ($mock): void {
            $mock->shouldReceive('ensureAllowed')->andReturnNull()->byDefault();
            $mock->shouldReceive('blockedReason')->andReturnNull()->byDefault();
        });
        $cases = [
            ['weeding', ['area_ha' => '1', 'method' => 'Cangkul', 'depth_cm' => '5'], 'weeding_details'],
            ['follow_up_fertilization', ['area_ha' => '1', 'fertilizer' => 'NPK', 'quantity_kg' => '25', 'method' => 'Tabur'], 'fertilization_details'],
            ['growth_check', ['phase' => 'Vegetatif', 'height_cm' => '60', 'sample_count' => '10', 'pest' => 'Aman', 'disease' => 'Aman'], 'plant_observations'],
            ['pest_control', ['area_ha' => '1', 'control_type' => 'Insektisida', 'target' => 'Ulat', 'product' => 'Produk pengendali', 'dosage' => '20 ml', 'water_liters' => '15', 'tank_count' => '2', 'method' => 'Semprot'], 'pesticide_details'],
            [null, ['area_ha' => '1', 'weight_ton' => '2', 'moisture' => '14', 'method' => 'Manual'], 'harvest_details'],
        ];
        foreach ($cases as [$subcategory, $details, $table]) {
            $task = $this->newTask(['activity_category' => $subcategory === null ? 'harvest' : 'maintenance', 'activity_subcategory' => $subcategory]);
            $data = $this->payload();
            $data['details'] = $details;
            $this->actingAs($this->actor)->post('/petugas/tasks/'.$task->id.'/aktivitas', $data)->assertSessionHasNoErrors()->assertRedirect('/petugas/tasks/'.$task->id);
            $activity = FarmActivity::where('planting_cycle_task_id', $task->id)->firstOrFail();
            $this->assertDatabaseHas($table, ['activity_id' => $activity->id]);
        }
        $weeding = FarmActivity::query()->whereHas('activityType', fn ($query) => $query->where('code', 'weeding'))->firstOrFail();
        $this->assertDatabaseHas('weeding_details', ['activity_id' => $weeding->id, 'method' => 'Cangkul', 'depth_cm' => 5]);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $this->assertDatabaseCount('farm_activities', 5);
    }

    public function test_database_rejects_weeding_without_a_typed_detail(): void
    {
        $weeding = ActivityType::query()->where('code', 'weeding')->firstOrFail();

        try {
            DB::transaction(function () use ($weeding): void {
                FarmActivity::create([
                    'farm_id' => $this->farm->id,
                    'planting_cycle_id' => $this->cycle->id,
                    'activity_type_id' => $weeding->id,
                    'performed_by' => $this->actor->id,
                    'activity_date' => now(),
                    'obstacle_status' => 'none',
                ]);
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail('Expected weeding without a typed detail to be rejected.');
        } catch (QueryException $error) {
            $this->assertSame('23514', (string) $error->getCode());
        }
    }

    public function test_missing_write_grants_and_cross_team_membership_deny_the_report_use_case(): void
    {
        $roles = app(\App\Support\RolePermissions::class)->roles();
        unset($roles['petugas']['activity_photos.create']);
        config(['access.roles' => $roles]);
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertForbidden();
        $this->team->members()->detach($this->actor);
        $this->actor->unsetRelation('teams')->unsetRelation('currentTeam');
        $this->get('/petugas/tasks')->assertForbidden();
        $this->get('/petugas/tasks/'.$this->task->id)->assertForbidden();
        $this->assertDatabaseCount('farm_activities', 0);
    }

    public function test_photos_are_denied_after_reassignment_and_failed_business_rules_leave_no_records(): void
    {
        $bad = $this->payload();
        $bad['details']['area_ha'] = '3';
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $bad)->assertSessionHasErrors('details.area_ha');
        $this->assertDatabaseCount('farm_activities', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertRedirect();
        $photo = ActivityPhoto::firstOrFail();
        $this->actor->forceFill(['assigned_farm_id' => null])->save();
        $this->get('/petugas/aktivitas/'.$photo->activity_id.'/foto/'.$photo->id)->assertForbidden();
    }

    public function test_force_delete_orders_task_reports_before_tasks_without_breaking_integrity(): void
    {
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertRedirect();
        $developer = User::factory()->create(['role' => 'superadmin']);
        \Illuminate\Support\Facades\Gate::forUser($developer)->authorize('forceDelete', $this->farm);
        $deletion = app(\App\Services\FarmDeletionService::class);
        $summary = $deletion->summary($developer, $this->farm);
        $this->assertSame([], $summary['blockers']);
        $deletion->forceDelete($developer, $this->farm, $this->farm->name, 'Pengujian penghapusan graph laporan tugas.', $summary['token']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $this->assertDatabaseCount('farm_activities', 0);
        $this->assertDatabaseCount('planting_cycle_tasks', 0);
        $this->assertDatabaseCount('daily_activity_logs', 0);
    }
}
