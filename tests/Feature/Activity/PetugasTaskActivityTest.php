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
        $block = app(FarmBlockService::class)->create($this->farm, $admin, $this->team, ['name' => 'Blok Utara', 'area_ha' => '5']);
        $this->cycle = app(FarmBlockService::class)->createBatch($block, $admin, $this->team, ['reserved_area_ha' => '2', 'planned_planting_at' => today()->subDays(21)->toDateString()]);
        $commodity = Commodity::create(['name' => 'Jagung']);
        $block->forceFill(['commodity_id' => $commodity->id])->save();
        $variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Bisi 18']);
        $this->cycle->forceFill(['planted_at' => today()->subDays(21), 'planted_area_ha' => '2', 'variety_id' => $variety->id, 'status' => 'active'])->save();
        $this->task = $this->newTask();
        $this->actor = User::factory()->create(['role' => 'petugas']);
        $this->team->members()->attach($this->actor, ['role' => TeamRole::Member->value]);
        $this->actor->forceFill(['current_team_id' => $this->team->id, 'assigned_farm_id' => $this->farm->id])->save();
        foreach (['land_cleaning', 'soil_preparation', 'base_fertilization', 'planting', 'maintenance', 'harvest', 'incident', 'other'] as $code) {
            ActivityType::firstOrCreate(['code' => $code], ['name' => $code]);
        }
    }

    public function test_inbox_block_navigation_reaches_task_index_with_a_validated_filter(): void
    {
        $block = $this->cycle->block_id;
        $this->actingAs($this->actor)->get('/petugas/aktivitas?status=all&block_id='.$block)
            ->assertInertia(fn (Assert $page) => $page->component('petugas/Aktivitas')
                ->where('filters.block_id', $block)->where('filters.status', 'all'));
        $this->getJson('/petugas/aktivitas?block_id=invalid')->assertUnprocessable();
        $this->get('/petugas/aktivitas?status=upcoming&category=harvest&block_id='.$block)
            ->assertInertia(fn (Assert $page) => $page->where('filters.block_id', $block)
                ->where('filters.status', 'upcoming')->where('filters.category', 'harvest'));
        $this->get('/petugas/aktivitas?status=active&category=harvest&block_id='.$block)
            ->assertInertia(fn (Assert $page) => $page->where('filters.status', 'active')
                ->where('filters.category', 'harvest')->where('tasks', fn ($tasks) => collect($tasks)->every(fn ($task) => in_array($task['urgency'], ['overdue', 'today'], true))));
        $this->getJson('/petugas/aktivitas?category=invalid')->assertUnprocessable();
    }

    public function test_activity_sequence_enforces_prerequisites_and_allows_exceptions(): void
    {
        $this->actingAs($this->actor);
        $this->cycle->forceFill(['planted_at' => null, 'planted_area_ha' => 0, 'status' => 'planned'])->save();
        $base = [...$this->payload(), 'cycle_id' => $this->cycle->id];
        $blockBase = [...$this->payload(), 'block_id' => $this->cycle->block_id];
        $yesterday = today()->subDay()->toDateString();
        $planting = ['work_date' => $yesterday, 'worker_count' => 3,
            'variety_id' => $this->cycle->variety_id, 'area_ha' => 1, 'seed_name' => 'Bisi 18', 'quantity_kg' => 5,
            'photos' => [UploadedFile::fake()->image('tanam.jpg')]];
        $plantingUrl = '/petugas/subbloks/'.$this->cycle->id.'/penanaman';
        $this->assertNull(app(\App\Services\Activity\ActivitySequenceService::class)->blockedReason($this->actor, $this->cycle, 'planting'));
        $this->post($plantingUrl, $planting)->assertSessionHasNoErrors();
        $soil = [...$blockBase, 'details' => ['area' => 100, 'area_unit' => 'm²']];
        $this->post('/petugas/aktivitas/mandiri/soil_preparation', $soil)->assertSessionHasNoErrors();
        $this->post('/petugas/aktivitas/mandiri/incident', [...$base, 'details' => ['area' => 100, 'area_unit' => 'm²', 'incident_category' => 'Cuaca', 'description' => 'Hujan lebat', 'severity' => 'Ringan']])->assertSessionHasNoErrors();
        $this->post('/petugas/aktivitas/mandiri/land_cleaning', [...$blockBase, 'details' => ['area' => 100, 'area_unit' => 'm²']])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('farm_activities', ['block_id' => $blockBase['block_id'], 'planting_cycle_id' => null]);
        $reason = app(\App\Services\Activity\ActivitySequenceService::class)->blockedReason($this->actor, $this->cycle->refresh(), 'planting');
        $this->assertNull($reason);
        $this->assertNull(app(\App\Services\Activity\ActivitySequenceService::class)->blockedReason($this->actor, $this->cycle->refresh(), 'planting'));
        $harvest = $this->newHarvestTask();
        $harvest->update(['offset_days' => 0, 'tolerance_days' => 2]);
        $harvestData = [...$this->payload(), 'details' => ['area_ha' => '0.01', 'weight_ton' => '0.5']];
        $harvestUrl = '/petugas/tasks/'.$harvest->id.'/aktivitas';
        $this->post($harvestUrl, $harvestData)->assertSessionHasErrors('task');
        $this->task->update(['status' => 'in_progress']);
        $this->post($harvestUrl, $harvestData)->assertSessionHasErrors('task');
        $this->task->update(['status' => 'skipped']);
        $this->post($harvestUrl, $harvestData)->assertSessionHasNoErrors();
    }

    public function test_empty_opposite_location_field_does_not_fail_validation(): void
    {
        $this->actingAs($this->actor);

        $this->post('/petugas/aktivitas/mandiri/land_cleaning', [
            ...$this->payload(),
            'cycle_id' => '',
            'block_id' => $this->cycle->block_id,
            'details' => ['area' => 100, 'area_unit' => 'm²'],
        ])->assertSessionHasNoErrors();

        $this->post('/petugas/aktivitas/mandiri/maintenance', [
            ...$this->payload(),
            'cycle_id' => $this->cycle->id,
            'block_id' => '',
            'details' => ['area' => 500, 'area_unit' => 'm²'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('farm_activities', [
            'block_id' => $this->cycle->block_id,
            'planting_cycle_id' => null,
        ]);
        $this->assertDatabaseHas('farm_activities', [
            'block_id' => $this->cycle->block_id,
            'planting_cycle_id' => $this->cycle->id,
        ]);
    }

    public function test_optional_location_uses_the_persisted_subblock_block_and_preserves_whole_location_options(): void
    {
        $this->actingAs($this->actor);
        $details = [
            'other' => ['description' => 'Memeriksa peralatan kebun.'],
            'incident' => ['incident_category' => 'Cuaca', 'description' => 'Hujan lebat', 'area' => 100, 'area_unit' => 'm²', 'severity' => 'Ringan'],
        ];

        foreach ($details as $code => $fields) {
            $url = '/petugas/aktivitas/mandiri/'.$code;
            $type = ActivityType::where('code', $code)->firstOrFail();
            foreach ([
                ['block_id' => '', 'cycle_id' => ''],
                ['block_id' => $this->cycle->block_id, 'cycle_id' => ''],
                ['block_id' => '', 'cycle_id' => $this->cycle->id],
            ] as $location) {
                $this->post($url, [...$this->payload(), ...$location, 'details' => $fields])
                    ->assertSessionHasNoErrors();
            }
            foreach ([
                ['block_id' => null, 'planting_cycle_id' => null],
                ['block_id' => $this->cycle->block_id, 'planting_cycle_id' => null],
                ['block_id' => $this->cycle->block_id, 'planting_cycle_id' => $this->cycle->id],
            ] as $expected) {
                $this->assertDatabaseHas('farm_activities', [
                    'activity_type_id' => $type->id,
                    ...$expected,
                ]);
            }
        }

        foreach ($details as $code => $fields) {
            $this->post('/petugas/aktivitas/mandiri/'.$code, [
                ...$this->payload(),
                'block_id' => $this->cycle->block_id,
                'cycle_id' => $this->cycle->id,
                'details' => $fields,
            ])->assertSessionHasErrors('block_id');
        }
        $this->assertDatabaseCount('farm_activities', 6);
    }

    public function test_sop_harvest_is_blocked_until_maintenance_tasks_finish(): void
    {
        $harvest = $this->newTask(['planting_cycle_stage_id' => $this->cycle->stages()->where('code', 'harvest')->firstOrFail()->id,
            'activity_category' => 'harvest', 'activity_subcategory' => null, 'name' => 'Panen']);
        $data = [...$this->payload(), 'details' => ['area_ha' => 1, 'weight_ton' => 1]];
        $url = '/petugas/tasks/'.$harvest->id.'/aktivitas';
        $this->actingAs($this->actor)->post($url, $data)->assertSessionHasErrors('task');
        $this->get('/petugas/tasks/'.$harvest->id)->assertInertia(fn (Assert $page) => $page->where('canRecord', false)->where('blockedReason', 'Selesaikan seluruh tugas SOP perawatan atau minta Admin melewati tugas yang tidak diperlukan sebelum panen.'));
        $this->task->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        $this->post($url, $data)->assertSessionHasNoErrors();
    }

    public function test_block_preparation_is_shared_with_subblocks_but_not_other_blocks(): void
    {
        $admin = User::findOrFail($this->farm->created_by);
        $block = $this->cycle->block;
        $other = app(FarmBlockService::class)->create($this->farm, $admin, $this->team, ['name' => 'Blok Selatan', 'area_ha' => '4']);
        $second = app(FarmBlockService::class)->createBatch($block, $admin, $this->team, ['reserved_area_ha' => '1', 'planned_planting_at' => today()->toDateString()]);
        $sequence = app(\App\Services\Activity\ActivitySequenceService::class);
        $this->actingAs($this->actor);
        $timeline = fn ($cycle) => $cycle->stages()->get()->keyBy('code');
        $otherCycle = app(FarmBlockService::class)->createBatch($other, $admin, $this->team, ['reserved_area_ha' => '1']);
        $this->assertSame('upcoming', $timeline($second)['land_clearing']->status);
        $this->post('/petugas/aktivitas/mandiri/land_cleaning', [...$this->payload(), 'block_id' => $block->id,
            'details' => ['area' => 100, 'area_unit' => 'm²']])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $timeline($second)['land_clearing']->status);
        $this->assertNotNull($timeline($second)['land_clearing']->completed_at);
        $this->assertSame('upcoming', $timeline($otherCycle)['land_clearing']->status);
        $this->post('/petugas/aktivitas/mandiri/soil_preparation', [...$this->payload(), 'block_id' => $block->id,
            'details' => ['area' => 100, 'area_unit' => 'm²']])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $timeline($second)['soil_preparation']->status);
        $third = app(FarmBlockService::class)->createBatch($block, $admin, $this->team, ['reserved_area_ha' => '1']);
        $this->assertSame('completed', $timeline($third)['land_clearing']->status);
        $this->assertSame('in_progress', $timeline($third)['soil_preparation']->status);
        $overview = app(FarmBlockService::class)->overview($this->farm, $admin, $this->team)->firstWhere('id', $block->id);
        $stages = collect(collect($overview->getAttribute('batches'))->firstWhere('id', $second->id)['stages'])->keyBy('code');
        $this->assertSame('completed', $stages['land_clearing']['status']);
        $this->assertSame('in_progress', $stages['soil_preparation']['status']);
        $this->cycle->forceFill(['planted_at' => null, 'planted_area_ha' => 0, 'status' => 'planned'])->save();
        $this->assertNull($sequence->blockedReason($this->actor, $this->cycle, 'planting'));
        $this->assertNull($sequence->blockedReason($this->actor, $second, 'planting'));
    }

    public function test_base_fertilization_is_a_separate_block_level_timeline_shared_by_subblocks(): void
    {
        $admin = User::findOrFail($this->farm->created_by);
        $block = $this->cycle->block;
        $second = app(FarmBlockService::class)->createBatch($block, $admin, $this->team, ['reserved_area_ha' => '1']);
        $other = app(FarmBlockService::class)->create($this->farm, $admin, $this->team, ['name' => 'Blok Selatan', 'area_ha' => '4']);
        $otherCycle = app(FarmBlockService::class)->createBatch($other, $admin, $this->team, ['reserved_area_ha' => '1']);
        $timeline = fn ($cycle) => $cycle->stages()->get()->keyBy('code');
        $this->assertSame('upcoming', $timeline($second)['basic_fertilizer']->status);

        $this->actingAs($this->actor);
        $details = ['area' => 100, 'area_unit' => 'm²'];
        $dates = [now('Asia/Jakarta')->subDays(2)->toDateString(), now('Asia/Jakarta')->subDay()->toDateString()];
        foreach (array_reverse($dates) as $date) {
            $this->post('/petugas/aktivitas/mandiri/base_fertilization', [...$this->payload(), 'work_date' => $date,
                'block_id' => $block->id, 'details' => $details])->assertSessionHasNoErrors();
        }
        foreach ([$this->cycle, $second] as $cycle) {
            $stages = $timeline($cycle);
            $this->assertSame('in_progress', $stages['basic_fertilizer']->status);
            $this->assertSame($dates[0], $stages['basic_fertilizer']->started_at->setTimezone('Asia/Jakarta')->toDateString());
            $this->assertSame($dates[1], $stages['basic_fertilizer']->completed_at->setTimezone('Asia/Jakarta')->toDateString());
            $this->assertSame('completed', $stages['soil_preparation']->status);
            $this->assertNull($stages['soil_preparation']->started_at);
            $this->assertNull($stages['soil_preparation']->completed_at);
        }
        $this->assertSame('upcoming', $timeline($otherCycle)['basic_fertilizer']->status);
        $this->assertDatabaseHas('fertilization_details', ['fertilization_stage' => 'kandang', 'area_ha' => '0.0100']);
        $this->assertSame(2, FarmActivity::query()->where('block_id', $block->id)->whereNull('planting_cycle_id')
            ->whereHas('activityType', fn ($query) => $query->where('code', 'base_fertilization'))->count());
        $stages = collect(collect(app(FarmBlockService::class)->overview($this->farm, $admin, $this->team)
            ->firstWhere('id', $block->id)->getAttribute('batches'))->firstWhere('id', $second->id)['stages'])->keyBy('code');
        $this->assertSame('in_progress', $stages['basic_fertilizer']['status']);
        $this->assertSame('completed', $stages['soil_preparation']['status']);
        $this->get('/petugas/kebun?block='.$block->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('garden.batches.data', fn ($batches) => collect($batches)->contains(fn ($batch) => $batch['id'] === $second->id
                && collect($batch['stages'])->firstWhere('code', 'basic_fertilizer')['status'] === 'in_progress'
                && collect($batch['stages'])->firstWhere('code', 'soil_preparation')['status'] === 'completed')));
        $this->actingAs($admin)->get('/admin/farm-management/'.$this->farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('blocks', fn ($blocks) => collect($blocks)->contains(fn ($row) => $row['id'] === $block->id
                && collect(collect($row['batches'])->firstWhere('id', $second->id)['stages'])->firstWhere('code', 'basic_fertilizer')['status'] === 'in_progress')));
        $owner = User::factory()->create(['role' => 'owner']);
        $this->team->members()->attach($owner, ['role' => TeamRole::Member->value]);
        $owner->forceFill(['current_team_id' => $this->team->id])->save();
        $owner->unsetRelation('currentTeam');
        $this->actingAs($owner)->get('/kebun/'.$this->farm->id.'/blok/'.$block->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('cycles', fn ($cycles) => collect($cycles)->contains(fn ($cycle) => $cycle['id'] === $second->id
                && collect($cycle['stages'])->firstWhere('code', 'basic_fertilizer')['status'] === 'in_progress'
                && collect($cycle['stages'])->firstWhere('code', 'soil_preparation')['status'] === 'completed')));
    }

    public function test_simplified_root_categories_share_area_input_and_use_appropriate_locations(): void
    {
        $this->assertSame(\App\Support\VoluntaryActivityFields::forCode('land_cleaning'), \App\Support\VoluntaryActivityFields::forCode('soil_preparation'));
        $this->assertSame(\App\Support\VoluntaryActivityFields::forCode('soil_preparation'), \App\Support\VoluntaryActivityFields::forCode('base_fertilization'));
        $this->assertSame(\App\Support\VoluntaryActivityFields::forCode('base_fertilization'), \App\Support\VoluntaryActivityFields::forCode('maintenance'));
        $this->actingAs($this->actor);
        foreach (['/petugas' => 'petugas/Home', '/petugas/laporan' => 'petugas/Laporan'] as $url => $component) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->where('categories.0.code', 'land_cleaning')->where('categories.0.group', null)
                ->where('categories.1.code', 'soil_preparation')->where('categories.1.group', null)
                ->where('categories.2.code', 'base_fertilization')->where('categories.2.name', 'Pemupukan Kandang')
                ->where('categories.3.code', 'planting')->where('categories.3.group', null)
                ->where('categories.4.code', 'maintenance')->where('categories.4.group', null)
                ->where('categories.5.code', 'incident')->where('categories.5.group', null)
                ->where('categories.6.code', 'other')->where('categories.6.group', null)
                ->where('categories', fn ($categories) => count($categories) === 7));
        }
        $details = ['area' => 100, 'area_unit' => 'm²'];
        $base = [...$this->payload(), 'block_id' => $this->cycle->block_id, 'details' => $details];
        $this->post('/petugas/aktivitas/mandiri/base_fertilization', [...$base, 'cycle_id' => $this->cycle->id])->assertSessionHasErrors('cycle_id');
        $this->post('/petugas/aktivitas/mandiri/base_fertilization', $base)->assertSessionHasNoErrors();
        $maintenance = [...$this->payload(), 'cycle_id' => $this->cycle->id, 'details' => $details, 'notes' => 'Pemupukan susulan NPK 2 liter kocor.'];
        $this->post('/petugas/aktivitas/mandiri/maintenance', [...$maintenance, 'block_id' => $this->cycle->block_id])->assertSessionHasErrors('block_id');
        $this->post('/petugas/aktivitas/mandiri/maintenance', $maintenance)->assertSessionHasNoErrors();
        $task = $this->newTask(['name' => 'Pemupukan susulan']);
        $this->post('/petugas/tasks/'.$task->id.'/aktivitas', [...$maintenance, 'schema' => 'taxonomy', 'cycle_id' => null])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fertilization_details', ['fertilization_stage' => 'kandang', 'area_ha' => '0.0100']);
        $this->get('/petugas/laporan?type=base_fertilization')->assertInertia(fn (Assert $page) => $page
            ->where('reports.total', 1)->where('stats.sop', 0)->where('stats.mandiri', 1)
            ->where('reports.data.0.code', 'base_fertilization')->where('reports.data.0.source', 'Mandiri'));
        $this->get('/petugas/laporan?type=maintenance')->assertInertia(fn (Assert $page) => $page
            ->where('reports.total', 2)->where('stats.sop', 1)->where('stats.mandiri', 1));
        $this->get('/petugas/laporan?source=sop')->assertInertia(fn (Assert $page) => $page
            ->where('reports.total', 1)->where('stats.sop', 1)->where('stats.mandiri', 0));
        $this->get('/petugas/laporan?source=mandiri')->assertInertia(fn (Assert $page) => $page
            ->where('reports.total', 2)->where('stats.sop', 0)->where('stats.mandiri', 2));
    }

    public function test_petugas_cannot_report_preparation_on_another_farm_block(): void
    {
        $admin = User::findOrFail($this->farm->created_by);
        $otherFarm = new Farm(['name' => 'Kebun Selatan', 'land_tenure' => 'owned', 'total_area_ha' => '5']);
        $otherFarm->forceFill(['team_id' => $this->team->id, 'created_by' => $admin->id])->save();
        $block = app(FarmBlockService::class)->create($otherFarm, $admin, $this->team, ['name' => 'Blok Selatan', 'area_ha' => '4']);
        $this->actingAs($this->actor)->post('/petugas/aktivitas/mandiri/land_cleaning', [...$this->payload(), 'block_id' => $block->id,
            'details' => ['area' => 100, 'area_unit' => 'm²']])->assertForbidden();
        $this->assertDatabaseCount('farm_activities', 0);
    }

    public function test_late_earlier_stage_report_fills_dates_without_reopening_a_completed_stage(): void
    {
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertSessionHasNoErrors();
        $before = $this->cycle->stages()->where('code', 'soil_preparation')->firstOrFail();
        $this->assertSame('completed', $before->status);
        $this->assertNull($before->started_at);
        $date = now('Asia/Jakarta')->subDays(4)->toDateString();
        $this->post('/petugas/aktivitas/mandiri/soil_preparation', [...$this->payload(), 'work_date' => $date,
            'block_id' => $this->cycle->block_id,
            'details' => ['area' => 100, 'area_unit' => 'm²']])->assertSessionHasNoErrors();
        $before->refresh();
        $this->assertSame('completed', $before->status);
        $this->assertSame($date, $before->started_at->setTimezone('Asia/Jakarta')->toDateString());
        $this->assertSame($date, $before->completed_at->setTimezone('Asia/Jakarta')->toDateString());
        $this->assertSame('in_progress', $this->cycle->stages()->where('code', 'maintenance')->value('status'));
    }

    public function test_database_rejects_activity_block_that_disagrees_with_cycle(): void
    {
        $admin = User::findOrFail($this->farm->created_by);
        $other = app(FarmBlockService::class)->create($this->farm, $admin, $this->team, ['name' => 'Blok Selatan', 'area_ha' => '4']);
        $this->actingAs($this->actor)->post('/petugas/aktivitas/mandiri/incident', [...$this->payload(), 'cycle_id' => $this->cycle->id,
            'details' => ['area' => 100, 'area_unit' => 'm²', 'incident_category' => 'Cuaca', 'description' => 'Hujan lebat', 'severity' => 'Ringan']])->assertSessionHasNoErrors();
        $activity = FarmActivity::firstOrFail();
        $this->assertSame($this->cycle->block_id, $activity->getAttribute('block_id'));
        $this->expectException(QueryException::class);
        DB::table('farm_activities')->where('id', $activity->id)->update(['block_id' => $other->id]);
    }

    public function test_no_maintenance_tasks_do_not_block_harvest(): void
    {
        $this->task->delete();
        $harvest = $this->newHarvestTask();
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$harvest->id.'/aktivitas', [...$this->payload(),
            'details' => ['area_ha' => '0.01', 'weight_ton' => '0.5']])->assertSessionHasNoErrors();
    }

    public function test_voluntary_harvest_is_unavailable_but_other_categories_and_historical_filters_remain(): void
    {
        $this->actingAs($this->actor);
        foreach (['/petugas', '/petugas/laporan'] as $url) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('categories', fn ($categories) => ! collect($categories)->pluck('code')->contains('harvest')
                    && collect($categories)->pluck('code')->contains('maintenance')));
        }
        $this->get('/petugas/laporan')->assertInertia(fn (Assert $page) => $page
            ->where('types', fn ($types) => collect($types)->pluck('code')->contains('harvest')));
        $this->get('/petugas/aktivitas/mandiri/harvest')->assertForbidden();
        $payload = [...$this->payload(), 'cycle_id' => $this->cycle->id,
            'details' => ['area' => 100, 'area_unit' => 'm²', 'weight' => 500, 'weight_unit' => 'kg']];
        $this->post('/petugas/aktivitas/mandiri/harvest', $payload)->assertForbidden();

        // The superadmin Gate::before must not bypass this workflow invariant.
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $superadmin->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->actingAs($superadmin)->get('/petugas/aktivitas/mandiri/harvest')->assertNotFound();
        $this->post('/petugas/aktivitas/mandiri/harvest', $payload)->assertNotFound();
        foreach (['/petugas', '/petugas/laporan'] as $url) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('categories', fn ($categories) => ! collect($categories)->pluck('code')->contains('harvest')));
        }
        $type = ActivityType::query()->where('code', 'harvest')->firstOrFail();
        try {
            app(\App\Services\Activity\VoluntaryActivityService::class)->record($superadmin, $this->farm, $type, $this->cycle, $payload);
            $this->fail('Direct service calls must not record voluntary harvest.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('type', $exception->errors());
        }
        $this->assertDatabaseCount('farm_activities', 0);
        $this->assertDatabaseCount('harvest_details', 0);
    }

    public function test_harvest_direct_completion_is_rejected_even_for_superadmin(): void
    {
        $harvest = $this->newHarvestTask();
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $superadmin->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->actingAs($superadmin)->post('/petugas/tasks/'.$harvest->id.'/selesai')
            ->assertSessionHasErrors('status');
        $this->assertSame('pending', $harvest->fresh()->status);
        $this->assertNull($harvest->fresh()->completed_at);
    }

    public function test_completion_service_scopes_the_locked_task_to_the_actor_farm(): void
    {
        $other = new Farm(['name' => 'Kebun lain', 'land_tenure' => 'owned', 'total_area_ha' => '3']);
        $other->forceFill(['team_id' => $this->team->id, 'created_by' => $this->farm->created_by])->save();
        $this->actor->forceFill(['assigned_farm_id' => $other->id])->save();
        $this->actor->unsetRelation('assignedFarm');
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($this->actor)->allows('complete', $this->task));

        try {
            app(\App\Services\PlantingCycleTaskManagementService::class)->complete($this->task, $this->actor, null);
            $this->fail('An out-of-scope task must not be resolved by the service.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->assertSame('pending', $this->task->fresh()->status);
        }
    }

    public function test_stage_report_times_use_work_date_not_database_write_times(): void
    {
        $this->travelTo(now()->startOfSecond());
        $url = '/petugas/tasks/'.$this->task->id.'/aktivitas';
        $firstDate = now('Asia/Jakarta')->subDays(3)->toDateString();
        $lastDate = now('Asia/Jakarta')->subDays(2)->toDateString();
        $this->actingAs($this->actor)->post($url, [...$this->payload(), 'work_date' => $firstDate])
            ->assertSessionHasNoErrors();
        $this->post($url, [...$this->payload(), 'work_date' => $firstDate])
            ->assertSessionHasNoErrors();

        $stage = $this->cycle->stages()->where('code', 'maintenance')->firstOrFail();
        $this->assertSame($firstDate.' 08:00', $stage->started_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'));
        $this->assertTrue($stage->started_at->equalTo($stage->completed_at));
        $this->assertFalse($stage->started_at->isSameDay($stage->created_at));
        $this->assertSame([$firstDate.' 08:00', $firstDate.' 08:00'], FarmActivity::query()->orderBy('position')->get()
            ->map(fn (FarmActivity $activity) => $activity->activity_date->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'))->all());

        $this->post($url, [...$this->payload(), 'work_date' => $lastDate])
            ->assertSessionHasNoErrors();
        $this->assertSame($firstDate.' 08:00', $stage->fresh()->started_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'));
        $this->assertSame($lastDate.' 08:00', $stage->fresh()->completed_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'));
    }

    public function test_partial_harvests_accumulate_and_exact_remaining_area_is_allowed(): void
    {
        $this->task->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        $harvest = $this->newHarvestTask();
        $this->actingAs($this->actor);
        $base = [...$this->payload(), 'schema' => 'taxonomy'];
        $url = '/petugas/tasks/'.$harvest->id.'/aktivitas';

        $this->post($url, [...$base, 'details' => [
            'area' => '0.5', 'area_unit' => 'Ha', 'weight' => '1000', 'weight_unit' => 'kg',
        ]])->assertSessionHasNoErrors();
        $this->post($url, [...$base, 'details' => [
            'area' => '1.5', 'area_unit' => 'Ha', 'weight' => '2000', 'weight_unit' => 'kg',
        ]])->assertSessionHasNoErrors();

        $this->assertSame(2.0, (float) HarvestDetail::query()->sum('harvested_area_ha'));

        $this->post($url, [...$base, 'details' => [
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
            'area_ha' => '1.2500', 'weight_ton' => '1.500',
        ]])->assertSessionHasNoErrors();

        $this->post($url, [...$this->payload(), 'details' => [
            'area_ha' => '0.7501', 'weight_ton' => '1.000',
        ]])->assertSessionHasErrors('details.area_ha');
        $this->assertDatabaseCount('harvest_details', 1);

        $this->post($url, [...$this->payload(), 'details' => [
            'area_ha' => '0.7500', 'weight_ton' => '1.000',
        ]])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('harvest_details', 2);
        $this->assertSame(2.0, (float) HarvestDetail::query()->sum('harvested_area_ha'));
    }

    public function test_legacy_unlinked_harvest_still_counts_toward_sop_harvest_limit(): void
    {
        $this->task->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        $harvest = $this->newHarvestTask();
        $this->actingAs($this->actor);

        $url = '/petugas/tasks/'.$harvest->id.'/aktivitas';
        $this->post($url, [...$this->payload(), 'details' => [
            'area_ha' => '1.75', 'weight_ton' => '3',
        ]])->assertSessionHasNoErrors();
        // Simulate an existing historical report created before task-only harvest.
        $legacy = FarmActivity::query()->firstOrFail();
        $legacy->forceFill(['planting_cycle_task_id' => null])->save();
        $this->get('/petugas/laporan?type=harvest&source=mandiri')->assertInertia(fn (Assert $page) => $page
            ->where('reports.total', 1)->where('reports.data.0.id', $legacy->id)->where('reports.data.0.source', 'Mandiri'));
        $this->get('/petugas/laporan/'.$legacy->id)->assertOk();

        $taxonomy = [...$this->payload(), 'schema' => 'taxonomy', 'details' => [
            'area' => '0.2501', 'area_unit' => 'Ha', 'weight' => '100', 'weight_unit' => 'kg',
        ]];
        $this->post($url, $taxonomy)->assertSessionHasErrors('details.area');
        $this->assertDatabaseCount('harvest_details', 1);

        $taxonomy['details']['area'] = '0.25';
        $this->post($url, $taxonomy)->assertSessionHasNoErrors();
        $this->assertSame(2.0, (float) HarvestDetail::query()->sum('harvested_area_ha'));
    }

    public function test_admin_closes_partial_harvest_only_after_reports_and_no_further_reports_are_accepted(): void
    {
        $this->travelTo(now()->startOfSecond());
        $admin = User::findOrFail($this->farm->created_by);
        $url = '/kebun/'.$this->farm->id.'/blocks/'.$this->cycle->block_id.'/batches/'.$this->cycle->id.'/close-harvest';
        $this->actingAs($admin)->post($url)->assertSessionHasErrors('harvest');
        $this->assertSame('active', $this->cycle->fresh()->status);
        $this->task->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        $harvest = $this->newHarvestTask();
        $otherHarvest = $this->newHarvestTask();
        $skippedHarvest = $this->newHarvestTask();
        $skippedHarvest->forceFill(['status' => 'skipped', 'skip_reason' => 'Tidak diperlukan.'])->save();
        $otherCycle = app(FarmBlockService::class)->createBatch($this->cycle->block, $admin, $this->team, ['reserved_area_ha' => '0.5']);
        $otherCycleTask = $this->newTask(['planting_cycle_id' => $otherCycle->id,
            'planting_cycle_stage_id' => $otherCycle->stages()->where('code', 'harvest')->firstOrFail()->id,
            'activity_category' => 'harvest', 'activity_subcategory' => null]);
        $this->actingAs($this->actor);
        $late = now('Asia/Jakarta')->subDay()->toDateString();
        $early = now('Asia/Jakarta')->subDays(2)->toDateString();
        $this->post('/petugas/tasks/'.$harvest->id.'/aktivitas', [...$this->payload(), 'work_date' => $late,
            'details' => ['area_ha' => '0.2500', 'weight_ton' => '0.500']])->assertSessionHasNoErrors();
        $this->get('/petugas/tasks/'.$harvest->id)->assertInertia(fn (Assert $page) => $page
            ->where('canRecord', true)->where('task.can.complete', false)
            ->where('completionGuidance', 'Laporan panen dapat ditambahkan dalam jendela pelaporan selama tugas dan subblok masih terbuka. Admin menyelesaikan tugas saat menutup panen.'));
        $this->post('/petugas/tasks/'.$harvest->id.'/selesai')->assertForbidden();
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $superadmin->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->actingAs($superadmin)->get('/petugas/tasks/'.$harvest->id)->assertInertia(fn (Assert $page) => $page
            ->where('canRecord', true)->where('task.can.complete', false));
        $this->post('/petugas/tasks/'.$harvest->id.'/selesai')->assertSessionHasErrors('status');
        $this->actingAs($this->actor);
        $this->post('/petugas/tasks/'.$harvest->id.'/aktivitas', [...$this->payload(), 'work_date' => $early,
            'details' => ['area_ha' => '0.5', 'weight_ton' => '0.5']])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $harvest->fresh()->status);
        $this->assertNull($harvest->fresh()->completed_at);
        $stage = $this->cycle->stages()->where('code', 'harvest')->firstOrFail();
        $this->assertSame('in_progress', $stage->status);
        $this->assertSame($early, $stage->started_at->setTimezone('Asia/Jakarta')->toDateString());
        $this->assertSame($late, $stage->completed_at->setTimezone('Asia/Jakarta')->toDateString());
        $this->assertSame('active', $this->cycle->fresh()->status);

        $this->actingAs($admin)->get('/admin/farm-management/'.$this->farm->id)->assertInertia(fn (Assert $page) => $page
            ->where('harvestPermissions.'.$this->cycle->id, true)
            ->where('blocks.0.batches.0.stages.5.status', 'in_progress'));
        // Open harvest task with zero reports cannot be closed silently.
        $this->post($url, ['return_to' => 'farm'])->assertSessionHasErrors('harvest');
        $this->assertSame('active', $this->cycle->fresh()->status);

        $this->actingAs($this->actor)->post('/petugas/tasks/'.$otherHarvest->id.'/aktivitas', [...$this->payload(), 'work_date' => $late,
            'details' => ['area_ha' => '0.2500', 'weight_ton' => '0.300']])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post($url, ['return_to' => 'farm'])->assertSessionHasNoErrors()->assertRedirect('/admin/farm-management/'.$this->farm->id);
        $this->assertSame('harvested', $this->cycle->fresh()->status);
        $this->assertSame('completed', $stage->fresh()->status);
        $this->assertSame($late, $stage->fresh()->completed_at->setTimezone('Asia/Jakarta')->toDateString());
        foreach ([$harvest, $otherHarvest] as $task) {
            $this->assertSame('completed', $task->fresh()->status);
            $this->assertTrue($task->fresh()->completed_at->equalTo(now()));
            $this->assertSame($admin->id, $task->fresh()->completed_by);
        }
        $this->assertSame('skipped', $skippedHarvest->fresh()->status);
        $this->assertSame('Tidak diperlukan.', $skippedHarvest->fresh()->skip_reason);
        $this->assertSame('pending', $otherCycleTask->fresh()->status);
        $this->post($url)->assertSessionHasErrors('harvest');
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$harvest->id.'/aktivitas', [...$this->payload(),
            'details' => ['area_ha' => '0.1', 'weight_ton' => '0.1']])->assertForbidden();
        $this->post('/petugas/aktivitas/mandiri/harvest', [...$this->payload(), 'cycle_id' => $this->cycle->id,
            'details' => ['area' => '0.1', 'area_unit' => 'Ha', 'weight' => '100', 'weight_unit' => 'kg']])->assertForbidden();
        $this->assertDatabaseCount('harvest_details', 3);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_harvest_closure_denies_other_roles_tenants_and_missing_grant(): void
    {
        $url = '/kebun/'.$this->farm->id.'/blocks/'.$this->cycle->block_id.'/batches/'.$this->cycle->id.'/close-harvest';
        $owner = User::factory()->create(['role' => 'owner']);
        $this->team->members()->attach($owner, ['role' => TeamRole::Member->value]);
        $owner->forceFill(['current_team_id' => $this->team->id])->save();
        $foreignTeam = Team::factory()->create(['is_personal' => false]);
        $foreignAdmin = User::factory()->create(['role' => 'admin']);
        $foreignTeam->members()->attach($foreignAdmin, ['role' => TeamRole::Member->value]);
        $foreignAdmin->forceFill(['current_team_id' => $foreignTeam->id])->save();
        foreach ([$this->actor, $owner, $foreignAdmin] as $denied) {
            $this->actingAs($denied)->post($url, ['farm_id' => $this->farm->id, 'team_id' => $this->team->id])->assertForbidden();
        }
        $roles = config('access.roles');
        unset($roles['admin']['planting_cycles.configure']);
        config(['access.roles' => $roles]);
        $this->actingAs(User::findOrFail($this->farm->created_by))->post($url)->assertForbidden();
        $this->assertSame('active', $this->cycle->fresh()->status);
    }

    public function test_harvest_closure_requires_task_view_and_update_within_the_farm(): void
    {
        $admin = User::findOrFail($this->farm->created_by);
        $url = '/kebun/'.$this->farm->id.'/blocks/'.$this->cycle->block_id.'/batches/'.$this->cycle->id.'/close-harvest';
        $roles = config('access.roles');
        foreach (['planting_cycle_tasks.view', 'planting_cycle_tasks.update'] as $grant) {
            $restricted = $roles;
            unset($restricted['admin'][$grant]);
            config(['access.roles' => $restricted]);
            $this->actingAs($admin)->post($url)->assertForbidden();
            $this->get('/admin/farm-management/'.$this->farm->id)->assertInertia(fn (Assert $page) => $page
                ->where('harvestPermissions.'.$this->cycle->id, false));

            $restricted = $roles;
            $restricted['admin'][$grant] = 'own';
            config(['access.roles' => $restricted]);
            $this->post($url)->assertForbidden();
        }
        config(['access.roles' => $roles]);
        $this->assertSame('active', $this->cycle->fresh()->status);
    }

    public function test_harvest_closure_rolls_back_all_task_and_cycle_changes_on_failure(): void
    {
        $admin = User::findOrFail($this->farm->created_by);
        $this->task->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        $first = $this->newHarvestTask();
        $second = $this->newHarvestTask();
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$first->id.'/aktivitas', [...$this->payload(),
            'details' => ['area_ha' => '0.25', 'weight_ton' => '0.5']])->assertSessionHasNoErrors();
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$second->id.'/aktivitas', [...$this->payload(),
            'details' => ['area_ha' => '0.25', 'weight_ton' => '0.5']])->assertSessionHasNoErrors();
        $ordered = collect([$first, $second])->sortBy('id')->values();
        $failingId = $ordered[1]->id;
        PlantingCycleTask::saving(function (PlantingCycleTask $task) use ($failingId): void {
            if ($task->id === $failingId && $task->status === 'completed') {
                throw new \RuntimeException('Simulated harvest task write failure.');
            }
        });

        \Illuminate\Support\Facades\Gate::forUser($admin)->authorize('configure', $this->cycle);
        try {
            app(\App\Services\Activity\HarvestClosureService::class)->close($admin, $this->team, $this->cycle);
            $this->fail('The simulated failure must abort the closure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated harvest task write failure.', $exception->getMessage());
        } finally {
            PlantingCycleTask::flushEventListeners();
        }
        $this->assertSame('active', $this->cycle->fresh()->status);
        $this->assertSame('in_progress', $this->cycle->stages()->where('code', 'harvest')->value('status'));
        $this->assertSame('in_progress', $first->fresh()->status);
        $this->assertSame('in_progress', $second->fresh()->status);
        $this->assertNull($first->fresh()->completed_at);
    }

    public function test_harvest_closure_requires_maintenance_resolved_and_harvest_tasks_reported_or_skipped(): void
    {
        $admin = User::findOrFail($this->farm->created_by);
        $this->task->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        $firstHarvest = $this->newHarvestTask();
        $secondHarvest = $this->newHarvestTask();
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$firstHarvest->id.'/aktivitas', [...$this->payload(),
            'details' => ['area_ha' => '0.50', 'weight_ton' => '1.0']])->assertSessionHasNoErrors();

        $extraMaintenance = $this->newTask(['name' => 'Pemupukan susulan wajib']);
        $url = '/kebun/'.$this->farm->id.'/blocks/'.$this->cycle->block_id.'/batches/'.$this->cycle->id.'/close-harvest';

        // Blocked when an open maintenance task exists.
        $this->actingAs($admin)->post($url)->assertSessionHasErrors('harvest');
        $this->assertSame('active', $this->cycle->fresh()->status);

        // Emergency skip by Admin resolves the maintenance blocker.
        $this->post('/admin/tasks/'.$extraMaintenance->id.'/skip', ['reason' => 'Kendala banjir di subblok.'])
            ->assertRedirect();

        // Still blocked while second harvest task has no report and is not skipped.
        $this->post($url)->assertSessionHasErrors('harvest');
        $this->assertSame('active', $this->cycle->fresh()->status);

        // Emergency skip on the second harvest task allows closure with the reported harvest task.
        $this->post('/admin/tasks/'.$secondHarvest->id.'/skip', ['reason' => 'Sisa tanaman gagal panen.'])
            ->assertRedirect();

        $this->post($url)->assertSessionHasNoErrors();
        $this->assertSame('harvested', $this->cycle->fresh()->status);
        $this->assertSame('completed', $firstHarvest->fresh()->status);
        $this->assertSame('skipped', $secondHarvest->fresh()->status);
    }

    public function test_stage_tracking_reports_require_visibility_but_unrelated_reports_remain_available(): void
    {
        $roles = config('access.roles');
        foreach (['planting_cycles.view', 'planting_cycle_stages.view'] as $grant) {
            $restricted = $roles;
            unset($restricted['petugas'][$grant]);
            config(['access.roles' => $restricted]);
            $this->actingAs($this->actor)->post('/petugas/aktivitas/mandiri/land_cleaning', [...$this->payload(),
                'block_id' => $this->cycle->block_id, 'details' => ['area' => 100, 'area_unit' => 'm²']])->assertForbidden();
            $this->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertForbidden();
            $this->post('/petugas/subbloks/'.$this->cycle->id.'/penanaman', [
                ...$this->payload(), 'variety_id' => $this->cycle->variety_id, 'area_ha' => 0.1,
                'seed_name' => 'Bisi 18', 'quantity_kg' => 1,
            ])->assertForbidden();
            $this->assertDatabaseCount('farm_activities', 0);
        }
        $this->post('/petugas/aktivitas/mandiri/other', [...$this->payload(), 'details' => ['description' => 'Membersihkan peralatan kebun.']])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('farm_activities', 1);
        config(['access.roles' => $roles]);
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
            'land_cleaning' => $area,
            'soil_preparation' => $area,
            'base_fertilization' => $area,
            'maintenance' => $area,
            'incident' => $area + ['incident_category' => 'Cuaca', 'description' => 'Tanaman rebah akibat angin.', 'severity' => 'Sedang', 'immediate_action' => 'Menopang batang.'],
            'other' => ['description' => 'Membersihkan alat kerja.'],
        ];
        $this->actingAs($this->actor);
        foreach ($cases as $code => $details) {
            $this->get('/petugas/aktivitas/mandiri/'.$code)->assertOk();
            $location = \App\Services\Activity\ActivitySequenceService::isBlockPreparation($code)
                ? ['block_id' => $this->cycle->block_id]
                : ['cycle_id' => $code === 'other' ? null : $this->cycle->id];
            $this->post('/petugas/aktivitas/mandiri/'.$code, [...$this->payload(), ...$location,
                'notes' => 'Catatan opsional untuk '.$code,
                'details' => $details])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertDatabaseCount('farm_activities', 6);
        $this->assertDatabaseCount('daily_activity_logs', 1);
        $this->assertDatabaseHas('daily_activity_logs', ['farm_id' => $this->farm->id]);
        $this->assertDatabaseCount('harvest_details', 0);
        $this->assertDatabaseHas('fertilization_details', ['fertilization_stage' => 'kandang', 'area_ha' => '0.1000']);
        $this->assertSame('pending', $this->task->refresh()->status);
        $this->assertSame(0, FarmActivity::whereNotNull('planting_cycle_task_id')->count());
        $this->get('/petugas/laporan')->assertInertia(fn (Assert $page) => $page
            ->component('petugas/Laporan')->where('reports.total', 6)
            ->where('categories', fn ($categories) => collect($categories)->pluck('code')->contains('maintenance')));
        $activity = FarmActivity::latest('activity_date')->firstOrFail();
        $this->get('/petugas/laporan/'.$activity->id)->assertInertia(fn (Assert $page) => $page->component('petugas/ReportShow')->has('log.activities', 1));
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_voluntary_validation_permissions_and_history_scope(): void
    {
        $this->actingAs($this->actor);
        $data = [...$this->payload(), 'cycle_id' => $this->cycle->id,
            'details' => ['area' => 500, 'area_unit' => 'm²']];
        $this->post('/petugas/aktivitas/mandiri/maintenance', [...$data, 'details' => ['area_unit' => 'm²']])
            ->assertSessionHasErrors('details.area');
        $this->post('/petugas/aktivitas/mandiri/maintenance', $data)->assertSessionHasNoErrors();
        $activity = FarmActivity::firstOrFail();
        $this->assertNotNull($activity->task_details);
        $other = new Farm(['name' => 'Kebun lain', 'land_tenure' => 'owned', 'total_area_ha' => '4']);
        $other->forceFill(['team_id' => $this->team->id, 'created_by' => $this->actor->id])->save();
        $this->actor->forceFill(['assigned_farm_id' => $other->id])->save();
        $this->actor->unsetRelation('assignedFarm');
        $this->post('/petugas/aktivitas/mandiri/maintenance', $data)->assertForbidden();
        $this->get('/petugas/laporan/'.$activity->id)->assertForbidden();
        $this->get('/petugas/laporan')->assertInertia(fn (Assert $page) => $page
            ->where('reports.total', 0)->where('stats.sop', 0)->where('stats.mandiri', 0));
        $this->get('/petugas/aktivitas/mandiri/block_mapping')->assertForbidden();
        $roles = config('access.roles');
        unset($roles['petugas']['activity_photos.create']);
        config(['access.roles' => $roles]);
        $this->get('/petugas/aktivitas/mandiri/other')->assertForbidden();
        $this->assertDatabaseCount('farm_activities', 1);
    }

    public function test_taxonomy_sop_form_persists_units_and_starts_task(): void
    {
        $data = [...$this->payload(), 'schema' => 'taxonomy',
            'details' => ['area' => 1000, 'area_unit' => 'm²']];
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $data)->assertSessionHasNoErrors();
        $activity = FarmActivity::where('planting_cycle_task_id', $this->task->id)->firstOrFail();
        $this->assertSame(0.1, json_decode((string) $activity->task_details, true)['area_ha']);
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
            'activity_category' => 'maintenance', 'activity_subcategory' => null, 'name' => 'Perawatan awal',
            'offset_days' => 18, 'tolerance_days' => 5, 'scheduled_date' => today()->subDays(3), 'due_date' => today()->addDays(2),
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
            'work_date' => now('Asia/Jakarta')->toDateString(), 'worker_count' => 3,
            'notes' => 'Perawatan berjalan lancar.',
            'details' => ['area_ha' => '1.0000'],
            'photos' => [UploadedFile::fake()->image('pengairan.jpg')],
        ];
    }

    public function test_real_tasks_and_form_use_persisted_category_and_location(): void
    {
        $this->actingAs($this->actor)->get('/petugas/tasks')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('petugas/Aktivitas')->has('tasks', 1)->where('tasks.0.id', $this->task->id)
            ->where('tasks.0.activity_category', 'maintenance')->where('tasks.0.subblok.farm.id', $this->farm->id)
            ->where('tasks.0.can.record', true)->where('summary.today', 1)->missing('isFixture'));
        $this->get('/petugas/tasks/'.$this->task->id.'/aktivitas/buat')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('petugas/ActivityCreate')->where('task.id', $this->task->id)->has('fields', 2));
        $this->get('/petugas/aktivitas/buat')->assertRedirect('/petugas/aktivitas');
    }

    public function test_task_window_is_enforced_for_direct_posts_and_rescheduling(): void
    {
        $this->actingAs($this->actor);
        $url = '/petugas/tasks/'.$this->task->id.'/aktivitas';
        $this->task->update(['rescheduled_date' => today()->addDay(), 'tolerance_days' => 2]);
        $this->get('/petugas/tasks/'.$this->task->id)->assertInertia(fn (Assert $page) => $page
            ->where('canRecord', false)->where('task.reporting_window.start', today()->addDay()->toDateString()));
        $this->post($url, $this->payload())->assertSessionHasErrors('task');
        $this->task->update(['rescheduled_date' => today()->subDays(3), 'tolerance_days' => 2]);
        $this->post($url, [...$this->payload(), 'work_date' => today()->subDays(2)->toDateString()])->assertSessionHasErrors('task');
        $this->task->update(['rescheduled_date' => today()->subDays(2), 'tolerance_days' => 2]);
        $this->post($url, [...$this->payload(), 'work_date' => today()->subDays(3)->toDateString()])->assertSessionHasErrors('work_date');
        $this->assertDatabaseCount('farm_activities', 0);
        $this->post($url, [...$this->payload(), 'work_date' => today()->subDays(2)->toDateString()])->assertSessionHasNoErrors();
        $this->post($url, $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('farm_activities', 2);
    }

    public function test_report_persists_log_details_private_photo_and_link_without_completing_task(): void
    {
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())
            ->assertRedirect('/petugas/tasks/'.$this->task->id);
        $activity = FarmActivity::firstOrFail();
        $this->assertSame($this->task->id, $activity->getAttribute('planting_cycle_task_id'));
        $this->assertSame($this->actor->id, $activity->performed_by);
        $this->assertDatabaseHas('farm_activities', ['id' => $activity->id, 'worker_count' => 3]);
        $this->assertSame('1.0000', json_decode((string) $activity->task_details, true)['area_ha']);
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

    public function test_two_officers_share_one_daily_log_without_overwriting_initial_recorder(): void
    {
        $data = $this->payload();
        $url = '/petugas/tasks/'.$this->task->id.'/aktivitas';
        $this->actingAs($this->actor)->post($url, $data)->assertSessionHasNoErrors();
        $first = \App\Models\DailyActivityLog::firstOrFail();
        $other = User::factory()->create(['role' => 'petugas']);
        $this->team->members()->attach($other, ['role' => TeamRole::Member->value]);
        $other->forceFill(['current_team_id' => $this->team->id, 'assigned_farm_id' => $this->farm->id])->save();
        $data = $this->payload();
        $data['worker_count'] = 99;
        $this->actingAs($other)->post($url, $data)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('daily_activity_logs', 1);
        $this->assertSame($this->actor->id, $first->refresh()->recorded_by);
        $this->assertSame([1, 2], FarmActivity::orderBy('position')->pluck('position')->all());
        $this->assertSame([3, 99], FarmActivity::orderBy('position')->pluck('worker_count')->all());
        $this->assertSame([$this->actor->id, $other->id], FarmActivity::orderBy('position')->pluck('performed_by')->all());
        $this->get('/petugas/laporan/harian')->assertInertia(fn (Assert $page) => $page->component('petugas/DailyReport')->has('log.activities', 2)->where('log.activities.0.worker_count', 3)->where('log.activities.1.worker_count', 99)->missing('canUpdate'));
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_same_date_reuses_daily_log_and_new_date_creates_a_separate_log(): void
    {
        $url = '/petugas/tasks/'.$this->task->id.'/aktivitas';
        $this->actingAs($this->actor)->post($url, $this->payload())->assertSessionHasNoErrors();
        $data = $this->payload();
        $this->post($url, $data)->assertSessionHasNoErrors();
        $data['photos'] = [UploadedFile::fake()->image('pengairan-lain.jpg')];
        $data['work_date'] = now('Asia/Jakarta')->subDay()->toDateString();
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('daily_activity_logs', 2);
        $this->assertDatabaseCount('farm_activities', 3);
    }

    public function test_daily_log_detail_is_readable_while_removed_header_edit_and_context_endpoints_are_unavailable(): void
    {
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertSessionHasNoErrors();
        $log = \App\Models\DailyActivityLog::firstOrFail();
        $detail = '/kebun/'.$this->farm->id.'/daily-logs/'.$log->id;
        $this->patch($detail, ['work_date' => '2026-01-01'])->assertStatus(405);
        $this->getJson('/kebun/'.$this->farm->id.'/daily-logs/context?work_date='.$log->work_date->toDateString())->assertNotFound();
        $this->assertDatabaseCount('farm_activities', 1);
        $owner = User::factory()->create(['role' => 'owner']);
        $this->team->members()->attach($owner, ['role' => TeamRole::Member->value]);
        $owner->forceFill(['current_team_id' => $this->team->id])->save();
        $this->actingAs($owner)->get($detail)->assertOk()->assertInertia(fn (Assert $page) => $page->missing('canUpdate')->has('log.activities', 1));
    }

    public function test_daily_report_and_photos_are_farm_scoped(): void
    {
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertSessionHasNoErrors();
        $photo = ActivityPhoto::firstOrFail();
        $url = '/kebun/'.$this->farm->id.'/daily-logs/activities/'.$photo->activity_id.'/photos/'.$photo->id;
        $this->get($url)->assertOk();
        $other = new Farm(['name' => 'Kebun tanpa akses', 'land_tenure' => 'owned', 'total_area_ha' => '3']);
        $other->forceFill(['team_id' => $this->team->id, 'created_by' => $this->actor->id])->save();
        $this->get('/kebun/'.$other->id.'/daily-logs')->assertForbidden();
        $this->actor->forceFill(['assigned_farm_id' => $other->id])->save();
        $this->actor->unsetRelation('assignedFarm');
        $this->get($url)->assertForbidden();
        $this->get('/petugas/laporan/harian')->assertInertia(fn (Assert $page) => $page->where('log', null));
    }

    public function test_report_photo_count_and_missing_files_remain_scoped_and_are_reported_honestly(): void
    {
        $data = $this->payload();
        $data['photos'] = [
            UploadedFile::fake()->image('pertama.jpg'),
            UploadedFile::fake()->image('kedua.jpg'),
        ];
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $data)->assertSessionHasNoErrors();
        $activity = FarmActivity::firstOrFail();
        $photos = ActivityPhoto::where('activity_id', $activity->id)->orderBy('uploaded_at')->orderBy('id')->get();
        $missing = $photos[0];
        $present = $photos[1];
        Storage::disk('local')->delete($missing->image_url);
        $photoUrl = fn ($photo) => route('daily-logs.photos', ['farm' => $this->farm, 'activity' => $activity, 'photo' => $photo]);

        $this->get('/petugas/laporan')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('reports.total', 1)->where('stats.sop', 1)->where('stats.mandiri', 0)
            ->where('reports.data.0.worker_count', 3)->where('reports.data.0.photo_count', 2)
            ->where('reports.data.0.photo', $photoUrl($present)));
        $this->get('/petugas/laporan/'.$activity->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('log.activities.0.photos', fn ($items) => collect($items)->firstWhere('id', $missing->id)['available'] === false
                && collect($items)->firstWhere('id', $present->id)['available'] === true));
        $taskPhotoUrl = fn ($photo) => route('petugas.aktivitas.photos', ['activity' => $activity, 'photo' => $photo]);
        $this->get('/petugas/tasks/'.$this->task->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('reports.0.photos', fn ($items) => collect($items)->firstWhere('id', $missing->id)['available'] === false
                && collect($items)->firstWhere('id', $present->id)['available'] === true));
        $this->get($taskPhotoUrl($missing))->assertNotFound();
        $this->get($taskPhotoUrl($present))->assertOk();
        $this->get($photoUrl($missing))->assertNotFound();
        $this->get($photoUrl($present))->assertOk();

        $roles = config('access.roles');
        $withoutPhotoView = $roles;
        unset($withoutPhotoView['petugas']['activity_photos.view']);
        try {
            config(['access.roles' => $withoutPhotoView]);
            $this->get('/petugas/laporan')->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('reports.data.0.photo_count', 0)->where('reports.data.0.photo', null));
            $this->get('/petugas/tasks/'.$this->task->id)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('reports.0.photos', 0));
            $this->get($taskPhotoUrl($present))->assertForbidden();
            $this->get($photoUrl($present))->assertForbidden();
        } finally {
            config(['access.roles' => $roles]);
        }
    }

    public function test_task_detail_retains_a_report_without_a_daily_log_or_worker_count(): void
    {
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())
            ->assertSessionHasNoErrors();
        $activity = FarmActivity::firstOrFail();
        $activity->forceFill(['daily_activity_log_id' => null, 'position' => null, 'worker_count' => null])->save();

        $this->get('/petugas/tasks/'.$this->task->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('reports.0.id', $activity->id)
            ->where('reports.0.work_date', null)
            ->where('reports.0.worker_count', null));
    }

    public function test_report_summary_counts_cover_all_filtered_pages_and_exclude_other_farms(): void
    {
        $otherOfficer = User::factory()->create(['role' => 'petugas']);
        $this->team->members()->attach($otherOfficer, ['role' => TeamRole::Member->value]);
        $otherOfficer->forceFill(['current_team_id' => $this->team->id, 'assigned_farm_id' => $this->farm->id])->save();
        $type = ActivityType::where('code', 'maintenance')->firstOrFail();
        for ($index = 0; $index < 18; $index++) {
            $activity = new FarmActivity([
                'farm_id' => $this->farm->id, 'planting_cycle_id' => $this->cycle->id,
                'activity_type_id' => $type->id, 'performed_by' => $index === 0 ? $otherOfficer->id : $this->actor->id,
                'activity_date' => now(), 'description' => 'Pemeliharaan kebun', 'worker_count' => 2,
            ]);
            $activity->forceFill([
                'block_id' => $this->cycle->block_id,
                'planting_cycle_task_id' => $index < 9 ? $this->task->id : null,
                'task_details' => $index < 9 ? json_encode(['area_ha' => 0.1]) : null,
            ])->save();
        }
        $otherFarm = new Farm(['name' => 'Kebun lain', 'land_tenure' => 'owned', 'total_area_ha' => '3']);
        $otherFarm->forceFill(['team_id' => $this->team->id, 'created_by' => $this->actor->id])->save();
        FarmActivity::create([
            'farm_id' => $otherFarm->id, 'activity_type_id' => $type->id,
            'performed_by' => $this->actor->id, 'activity_date' => now(), 'worker_count' => 1,
        ]);

        $this->actingAs($this->actor);
        foreach ([1, 2] as $pageNumber) {
            $this->get('/petugas/laporan?page='.$pageNumber)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('reports.total', 18)->where('reports.current_page', $pageNumber)
                ->where('stats.sop', 9)->where('stats.mandiri', 9));
        }
        $this->get('/petugas/laporan?source=mandiri')->assertInertia(fn (Assert $page) => $page
            ->where('reports.total', 9)->where('stats.sop', 0)->where('stats.mandiri', 9));
        $this->get('/petugas/laporan?mine=1')->assertInertia(fn (Assert $page) => $page
            ->where('reports.total', 17)->where('stats.sop', 8)->where('stats.mandiri', 9));
    }

    public function test_report_highlights_use_scoped_persisted_details_for_each_category(): void
    {
        $cases = [
            ['maintenance', ['area' => 1200, 'area_unit' => 'm²', 'area_ha' => '0.1200'], null, 'Luas dikerjakan: 1.200 m²'],
            ['soil_preparation', ['area' => '0.1250', 'area_unit' => 'Ha'], null, 'Luas dikerjakan: 0,125 ha'],
            ['land_cleaning', null, ['area_ha' => '0.1250'], 'Luas dikerjakan: 0,125 ha'],
            ['base_fertilization', ['area' => 1500, 'area_unit' => 'm²'], ['area_ha' => '0.1500'], 'Luas dikerjakan: 1.500 m²'],
            ['planting', ['quantity' => 2, 'quantity_unit' => 'sak', 'bag_size' => 5], ['seed_name' => 'Bisi 18', 'quantity_kg' => '10.000'], 'Benih: Bisi 18 · 2 sak'],
            ['harvest', null, ['gross_weight_ton' => '2.750'], 'Berat panen: 2,75 ton'],
            ['incident', null, ['incident_category' => 'Hama', 'severity' => 'medium'], 'Insiden: Hama · Sedang'],
            ['other', null, ['description' => 'Pemeriksaan peralatan'], null],
        ];
        $this->actingAs($this->actor);
        foreach ($cases as [$code, $input, $detail, $expected]) {
            $this->reportForHighlight($code, $input, $detail);
            $this->get('/petugas/laporan?type='.$code)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('reports.total', 1)->where('reports.data.0.code', $code)
                ->where('reports.data.0.highlight', $expected));
        }
    }

    public function test_report_highlight_preserves_original_units_and_hides_missing_data(): void
    {
        $this->actingAs($this->actor);
        $planting = $this->reportForHighlight('planting', ['quantity' => 200, 'quantity_unit' => 'butir'], [
            'seed_name' => 'Benih jagung', 'quantity_kg' => null,
        ]);
        $this->get('/petugas/laporan?type=planting')->assertInertia(fn (Assert $page) => $page
            ->where('reports.data.0.highlight', 'Benih: Benih jagung · 200 butir'));

        $harvest = $this->reportForHighlight('harvest', ['weight' => '750.50', 'weight_unit' => 'kg'], [
            'gross_weight_ton' => '0.751',
        ]);
        $this->get('/petugas/laporan?type=harvest')->assertInertia(fn (Assert $page) => $page
            ->where('reports.data.0.highlight', 'Berat panen: 750,5 kg'));

        $this->reportForHighlight('maintenance', ['area' => 'tidak valid', 'area_unit' => 'm²']);
        $this->get('/petugas/laporan?type=maintenance')->assertInertia(fn (Assert $page) => $page
            ->where('reports.data.0.highlight', null));
        $this->get('/petugas/laporan/'.$planting->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('legacyActivity.notes', 'Catatan kegiatan yang tersimpan.'));
        $this->get('/petugas/laporan/'.$harvest->id)->assertOk();
    }

    public function test_report_highlight_respects_detail_grants_and_assigned_farm(): void
    {
        $activity = $this->reportForHighlight('planting', ['quantity' => 2, 'quantity_unit' => 'sak'], [
            'seed_name' => 'Benih terlindungi', 'quantity_kg' => '10.000',
        ]);
        $this->actingAs($this->actor)->get('/petugas/laporan?type=planting')->assertInertia(fn (Assert $page) => $page
            ->where('reports.data.0.highlight', 'Benih: Benih terlindungi · 2 sak'));
        $roles = config('access.roles');
        $withoutDetailView = $roles;
        unset($withoutDetailView['petugas']['planting_details.view']);
        try {
            config(['access.roles' => $withoutDetailView]);
            $this->get('/petugas/laporan?type=planting')->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('reports.total', 1)->where('reports.data.0.highlight', null));
        } finally {
            config(['access.roles' => $roles]);
        }

        $other = new Farm(['name' => 'Kebun lain', 'land_tenure' => 'owned', 'total_area_ha' => '3']);
        $other->forceFill(['team_id' => $this->team->id, 'created_by' => $this->actor->id])->save();
        $this->actor->forceFill(['assigned_farm_id' => $other->id])->save();
        $this->actor->unsetRelation('assignedFarm');
        $this->get('/petugas/laporan?type=planting')->assertInertia(fn (Assert $page) => $page->where('reports.total', 0));
        $this->get('/petugas/laporan/'.$activity->id)->assertForbidden();
    }

    public function test_new_task_report_highlights_worked_area_and_retains_detail_notes(): void
    {
        $data = [...$this->payload(), 'schema' => 'taxonomy',
            'details' => ['area' => 100, 'area_unit' => 'm²']];
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $data)->assertSessionHasNoErrors();
        $activity = FarmActivity::firstOrFail();
        $this->get('/petugas/laporan?source=sop')->assertInertia(fn (Assert $page) => $page
            ->where('reports.data.0.highlight', 'Luas dikerjakan: 100 m²'));
        $this->get('/petugas/laporan/'.$activity->id)->assertInertia(fn (Assert $page) => $page
            ->where('log.activities.0.notes', $data['notes']));
    }

    /** @param array<string, mixed>|null $input
     * @param  array<string, mixed>|null  $detail
     */
    private function reportForHighlight(string $code, ?array $input = null, ?array $detail = null): FarmActivity
    {
        $type = ActivityType::query()->where('code', $code)->firstOrFail();
        $activity = FarmActivity::create([
            'farm_id' => $this->farm->id, 'planting_cycle_id' => $this->cycle->id,
            'activity_type_id' => $type->id, 'performed_by' => $this->actor->id,
            'activity_date' => now(), 'description' => 'Catatan kegiatan yang tersimpan.', 'worker_count' => 2,
        ]);
        if ($input !== null) {
            $activity->forceFill(['task_details' => json_encode($input, JSON_THROW_ON_ERROR)])->save();
        }
        if ($detail !== null) {
            $relation = match ($code) {
                'land_cleaning' => 'landCleaningDetail',
                'base_fertilization' => 'fertilizationDetail',
                'planting' => 'plantingDetail',
                'harvest' => 'harvestDetail',
                'incident' => 'incidentDetail',
                'other' => 'otherDetail',
            };
            $activity->{$relation}()->create($detail);
        }

        return $activity;
    }

    public function test_database_unique_constraint_prevents_a_second_log_for_the_same_farm_date(): void
    {
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertSessionHasNoErrors();
        $log = \App\Models\DailyActivityLog::firstOrFail();
        try {
            DB::transaction(fn () => \App\Models\DailyActivityLog::create($log->only(['farm_id', 'work_date', 'recorded_by'])));
            $this->fail('Expected unique farm/date constraint.');
        } catch (\Illuminate\Database\QueryException $error) {
            $this->assertSame('23505', (string) $error->getCode());
        }
        $this->assertDatabaseCount('daily_activity_logs', 1);
    }

    public function test_foreign_tenant_and_missing_grants_cannot_view_daily_logs(): void
    {
        $this->actingAs($this->actor)->post('/petugas/tasks/'.$this->task->id.'/aktivitas', $this->payload())->assertSessionHasNoErrors();
        $log = \App\Models\DailyActivityLog::firstOrFail();
        $photo = ActivityPhoto::firstOrFail();
        $foreign = Team::factory()->create(['is_personal' => false]);
        $this->team->members()->detach($this->actor);
        $foreign->members()->attach($this->actor, ['role' => TeamRole::Member->value]);
        $this->actor->forceFill(['current_team_id' => $foreign->id])->save();
        $this->actor->unsetRelation('teams')->unsetRelation('currentTeam')->unsetRelation('assignedFarm');
        $this->get('/kebun/'.$this->farm->id.'/daily-logs/'.$log->id)->assertForbidden();
        $this->get('/kebun/'.$this->farm->id.'/daily-logs/activities/'.$photo->activity_id.'/photos/'.$photo->id)->assertForbidden();
        $this->get('/petugas/aktivitas')->assertForbidden();
        $this->assertSame(3, FarmActivity::firstOrFail()->worker_count);
    }

    public function test_forged_context_and_invalid_details_or_uploads_are_rejected_atomically(): void
    {
        $data = $this->payload();
        $data['farm_id'] = $this->farm->id;
        $data['activity_subcategory'] = 'harvest';
        unset($data['details']['area_ha']);
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
        $this->task->update(['offset_days' => 18, 'tolerance_days' => 2, 'scheduled_date' => today()->subDays(3), 'due_date' => today()->subDay()]);
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
            ['maintenance', ['area_ha' => '1'], null],
            ['harvest', ['area_ha' => '1', 'weight_ton' => '2'], 'harvest_details'],
        ];
        foreach ($cases as [$category, $details, $table]) {
            $task = $this->newTask(['activity_category' => $category, 'activity_subcategory' => null]);
            $data = $this->payload();
            $data['details'] = $details;
            $this->actingAs($this->actor)->post('/petugas/tasks/'.$task->id.'/aktivitas', $data)->assertSessionHasNoErrors()->assertRedirect('/petugas/tasks/'.$task->id);
            $activity = FarmActivity::where('planting_cycle_task_id', $task->id)->firstOrFail();
            if ($table !== null) {
                $this->assertDatabaseHas($table, ['activity_id' => $activity->id]);
            } else {
                $this->assertSame('1', json_decode((string) $activity->task_details, true)['area_ha']);
            }
        }
        $harvest = FarmActivity::query()->whereHas('activityType', fn ($query) => $query->where('code', 'harvest'))->firstOrFail();
        $this->assertDatabaseHas('harvest_details', ['activity_id' => $harvest->id, 'harvested_area_ha' => '1.0000', 'gross_weight_ton' => '2.000']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $this->assertDatabaseCount('farm_activities', 2);
    }

    public function test_database_rejects_harvest_without_a_typed_detail(): void
    {
        $harvest = ActivityType::query()->where('code', 'harvest')->firstOrFail();

        try {
            DB::transaction(function () use ($harvest): void {
                FarmActivity::create([
                    'farm_id' => $this->farm->id,
                    'planting_cycle_id' => $this->cycle->id,
                    'activity_type_id' => $harvest->id,
                    'performed_by' => $this->actor->id,
                    'activity_date' => now(),
                    'obstacle_status' => 'none',
                ]);
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail('Expected harvest without a typed detail to be rejected.');
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
