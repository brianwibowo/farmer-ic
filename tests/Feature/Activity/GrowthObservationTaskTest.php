<?php

namespace Tests\Feature\Activity;

use App\Enums\TeamRole;
use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantObservation;
use App\Models\SopTemplate;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use App\Services\PlantingCycleTaskService;
use App\Services\SopTemplateService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Integration coverage for SOP growth_observation tasks linked to Admin Detail Lahan growth modal. */
class GrowthObservationTaskTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    private User $owner;

    private User $petugas;

    private Farm $farm;

    private FarmBlock $block;

    private PlantingCycle $cycle;

    private Commodity $commodity;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('activity');
        $this->travelTo(CarbonImmutable::parse('2026-11-19 09:00:00', 'Asia/Jakarta'));

        $this->team = Team::factory()->create();
        $this->admin = $this->member('admin');
        $this->owner = $this->member('owner');
        $this->petugas = $this->member('petugas');

        $this->farm = new Farm(['name' => 'Kebun Pengamatan', 'land_tenure' => 'owned', 'total_area_ha' => '10']);
        $this->farm->forceFill(['team_id' => $this->team->id, 'created_by' => $this->admin->id])->save();
        $this->petugas->forceFill(['assigned_farm_id' => $this->farm->id])->save();

        $this->commodity = Commodity::create(['name' => 'Jagung']);
        $variety = CropVariety::create(['commodity_id' => $this->commodity->id, 'name' => 'Hibrida BISI-18']);

        $template = SopTemplate::create([
            'team_id' => $this->team->id,
            'commodity_id' => $this->commodity->id,
            'name' => 'SOP Pengamatan Jagung',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
        $version = $template->versions()->create([
            'version' => 1,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);
        $version->tasks()->createMany([
            [
                'activity_category' => 'growth_observation',
                'activity_subcategory' => null,
                'name' => 'Pengamatan Vegetatif',
                'instructions' => 'Catat fase pertumbuhan dan unggah foto petak ukur.',
                'offset_days' => 20,
                'tolerance_days' => 5,
                'is_required' => true,
            ],
            [
                'activity_category' => 'harvest',
                'activity_subcategory' => null,
                'name' => 'Panen Jagung',
                'offset_days' => 100,
                'tolerance_days' => 5,
                'is_required' => true,
            ],
        ]);
        app(SopTemplateService::class)->publish($version, $this->admin);

        $this->block = app(FarmBlockService::class)->create($this->farm, $this->admin, $this->team, [
            'name' => 'Blok Pengamatan',
            'area_ha' => '5',
            'commodity_id' => $this->commodity->id,
            'default_sop_template_id' => $template->id,
        ]);
        $this->cycle = app(FarmBlockService::class)->createBatch($this->block, $this->admin, $this->team, [
            'reserved_area_ha' => '2',
            'planned_planting_at' => '2026-10-30',
        ]);
        $this->cycle->forceFill([
            'variety_id' => $variety->id,
            'planted_at' => '2026-10-30',
            'planted_area_ha' => '2',
            'status' => 'active',
        ])->save();
        app(PlantingCycleTaskService::class)->generateForSubblok($this->cycle->fresh(), $this->admin);

        foreach (['land_cleaning', 'soil_preparation', 'base_fertilization', 'planting', 'maintenance', 'harvest', 'incident', 'other'] as $code) {
            ActivityType::firstOrCreate(['code' => $code], ['name' => $code]);
        }
    }

    public function test_admin_can_add_growth_observation_step_to_draft_sop(): void
    {
        $sop = SopTemplate::create([
            'team_id' => $this->team->id,
            'commodity_id' => $this->commodity->id,
            'name' => 'SOP Uji Tambah Pengamatan',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
        $draft = $sop->versions()->create([
            'version' => 1,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/sops/'.$sop->id.'/versions/'.$draft->id.'/tasks', [
                'activity_category' => 'growth_observation',
                'name' => 'Pengamatan pertumbuhan awal',
                'instructions' => 'Amati pertumbuhan tunas awal.',
                'offset_days' => 14,
                'tolerance_days' => 2,
                'hok' => 1,
            ])->assertRedirect();

        $this->assertDatabaseHas('sop_template_tasks', [
            'sop_template_version_id' => $draft->id,
            'activity_category' => 'growth_observation',
            'activity_subcategory' => null,
            'name' => 'Pengamatan pertumbuhan awal',
        ]);
    }

    public function test_petugas_submits_growth_observation_without_area_or_worker_count_and_links_admin_modal(): void
    {
        $observationTask = $this->cycle->tasks()->where('activity_category', 'growth_observation')->firstOrFail();
        $harvestTask = $this->cycle->tasks()->where('activity_category', 'harvest')->firstOrFail();

        $this->assertSame('maintenance', $observationTask->stage->code);

        $this->actingAs($this->admin)
            ->get('/admin/tasks?category=growth_observation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.category', 'growth_observation')
                ->has('tasks.data', 1)
                ->where('tasks.data.0.id', $observationTask->id));

        $this->actingAs($this->petugas)
            ->get('/petugas/aktivitas?status=all&category=growth_observation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.category', 'growth_observation')
                ->has('tasks', 2)
                ->where('tasks.0.id', $observationTask->id));

        $this->actingAs($this->petugas)
            ->get('/petugas/tasks/'.$observationTask->id.'/aktivitas/buat')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('petugas/ActivityCreate')
                ->where('requiresWorkerCount', false)
                ->where('task.subblok.commodity.name', 'Jagung')
                ->has('fields', 1)
                ->where('fields.0.key', 'growth_phase'));

        $this->actingAs($this->petugas)
            ->post('/petugas/tasks/'.$observationTask->id.'/aktivitas', [
                'schema' => 'taxonomy',
                'work_date' => '2026-11-19',
                'notes' => 'Daun sehat seragam',
                'details' => ['growth_phase' => '   '],
                'photos' => [UploadedFile::fake()->image('blank.jpg')],
            ])->assertSessionHasErrors('details.growth_phase');

        $this->actingAs($this->petugas)
            ->post('/petugas/tasks/'.$observationTask->id.'/aktivitas', [
                'schema' => 'taxonomy',
                'work_date' => '2026-11-19',
                'notes' => 'Pertumbuhan vegetatif seragam pada baris tengah.',
                'details' => ['growth_phase' => 'Vegetatif'],
                'photos' => [UploadedFile::fake()->image('vegetatif.jpg', 4000, 2000)],
            ])->assertRedirect('/petugas/tasks/'.$observationTask->id);

        $activity = FarmActivity::query()->where('planting_cycle_task_id', $observationTask->id)->firstOrFail();
        $this->assertNull($activity->worker_count);
        $this->assertSame('growth_observation', $activity->activityType->code);
        $this->assertSame('Pengamatan Pertumbuhan', $activity->activityType->name);
        $photo = $activity->photos()->firstOrFail();
        $this->assertStringStartsWith('farms/'.$this->farm->id.'/'.$activity->id.'/', $photo->image_url);
        $this->assertSame([2048, 1024], array_slice(getimagesizefromstring(Storage::disk('activity')->get($photo->image_url)), 0, 2));

        $observation = PlantObservation::query()->where('activity_id', $activity->id)->firstOrFail();
        $this->assertSame($this->cycle->id, $observation->planting_cycle_id);
        $this->assertSame($this->petugas->id, $observation->observed_by);
        $this->assertSame('Vegetatif', $observation->growth_phase);
        $this->assertSame('Pertumbuhan vegetatif seragam pada baris tengah.', $observation->notes);
        $this->assertNull($observation->growth_percentage);

        $this->assertSame('in_progress', $observationTask->fresh()->status);
        $this->assertSame('completed', $this->cycle->stages()->where('code', 'planting')->value('status'));
        $this->assertSame('in_progress', $this->cycle->stages()->where('code', 'maintenance')->value('status'));

        // Unfinished observation task blocks harvest recording until completed or skipped.
        $this->actingAs($this->petugas)
            ->get('/petugas/tasks/'.$harvestTask->id)
            ->assertInertia(fn (Assert $page) => $page->where('canRecord', false));

        // Petugas task detail, report listing, and report detail surface Pengamatan Pertumbuhan while staying SOP-only.
        $this->actingAs($this->petugas)
            ->get('/petugas/tasks/'.$observationTask->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reports.0.worker_count', null)
                ->where('reports.0.details.growth_phase', 'Vegetatif'));

        $this->actingAs($this->petugas)
            ->get('/petugas/laporan?type=growth_observation&q=Pengamatan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reports.total', 1)
                ->where('reports.data.0.code', 'growth_observation')
                ->where('reports.data.0.name', 'Pengamatan Pertumbuhan')
                ->where('reports.data.0.source', 'SOP')
                ->where('reports.data.0.highlight', 'Fase pertumbuhan: Vegetatif')
                ->where('types', fn ($types) => collect($types)->contains(fn ($type) => $type['code'] === 'growth_observation' && $type['name'] === 'Pengamatan Pertumbuhan'))
                ->where('categories', fn ($categories) => ! collect($categories)->pluck('code')->contains('growth_observation')));

        $this->actingAs($this->petugas)
            ->get('/petugas/laporan/'.$activity->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('log.activities.0.code', 'growth_observation')
                ->where('log.activities.0.name', 'Pengamatan Pertumbuhan')
                ->where('log.activities.0.details', fn ($details) => collect($details)->contains(
                    fn ($detail) => $detail['label'] === 'Fase pertumbuhan' && $detail['value'] === 'Vegetatif',
                )));

        $this->actingAs($this->petugas)
            ->get('/petugas/aktivitas/mandiri/growth_observation')
            ->assertForbidden();

        // Admin logbook indexes and filters Pengamatan Pertumbuhan separately from Perawatan.
        $this->actingAs($this->admin)
            ->get('/admin/logbook?activity_type=growth_observation&search=Pengamatan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activities.total', 1)
                ->where('activities.data.0.code', 'growth_observation')
                ->where('activities.data.0.name', 'Pengamatan Pertumbuhan'));

        // Admin Detail Lahan receives HST %, observed phase, observation history, photos, and SOP task links.
        $this->actingAs($this->admin)
            ->get('/admin/farm-management/'.$this->farm->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('blocks.0.batches.0.growth.available', true)
                ->where('blocks.0.batches.0.growth.hst', 20)
                ->where('blocks.0.batches.0.growth.hst_percentage', 20)
                ->where('blocks.0.batches.0.growth.observation.id', $observation->id)
                ->where('blocks.0.batches.0.growth.observation.phase', 'Vegetatif')
                ->where('blocks.0.batches.0.growth.observations.0.id', $observation->id)
                ->where('blocks.0.batches.0.growth.observations.0.phase', 'Vegetatif')
                ->where('blocks.0.batches.0.growth.observations.0.observer_name', $this->petugas->name)
                ->where('blocks.0.batches.0.growth.observations.0.notes', 'Pertumbuhan vegetatif seragam pada baris tengah.')
                ->where('blocks.0.batches.0.growth.observations.0.task_id', $observationTask->id)
                ->where('blocks.0.batches.0.growth.observations.0.task_name', 'Pengamatan Vegetatif')
                ->where('blocks.0.batches.0.growth.observations.0.task_url', '/admin/tasks/'.$observationTask->id)
                ->where('blocks.0.batches.0.growth.observations.0.activity_url', '/admin/logbook/aktivitas/'.$activity->id)
                ->has('blocks.0.batches.0.growth.observations.0.photos', 1)
                ->where('blocks.0.batches.0.growth.observations.0.photos.0.available', true)
                ->has('blocks.0.batches.0.growth.tasks', 1)
                ->where('blocks.0.batches.0.growth.tasks.0.id', $observationTask->id)
                ->where('blocks.0.batches.0.growth.tasks.0.status', 'in_progress')
                ->where('blocks.0.batches.0.growth.tasks.0.url', '/admin/tasks/'.$observationTask->id));

        $photoId = $activity->photos()->firstOrFail()->id;
        $this->actingAs($this->admin)
            ->get('/kebun/'.$this->farm->id.'/daily-logs/activities/'.$activity->id.'/photos/'.$photoId)
            ->assertOk();

        // Owner portal can inspect observations and photos, but does not receive admin-only links.
        $this->actingAs($this->owner)
            ->get('/kebun/'.$this->farm->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('blocks.0.batches.0.growth.observations', 1)
                ->where('blocks.0.batches.0.growth.observations.0.task_url', null)
                ->where('blocks.0.batches.0.growth.observations.0.activity_url', null)
                ->where('blocks.0.batches.0.growth.tasks.0.url', null));

        // Revoking plant_observations.view hides observation details honestly.
        $grants = config('access.roles.admin');
        unset($grants['plant_observations.view']);
        config(['access.roles.admin' => $grants]);
        $this->actingAs($this->admin)
            ->get('/admin/farm-management/'.$this->farm->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('blocks.0.batches.0.growth.observation', null)
                ->has('blocks.0.batches.0.growth.observations', 0));

        $petugasGrants = config('access.roles.petugas');
        unset($petugasGrants['plant_observations.view']);
        config(['access.roles.petugas' => $petugasGrants]);
        $this->actingAs($this->petugas)
            ->get('/petugas/laporan?type=growth_observation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('reports.data.0.highlight', null));
        $this->actingAs($this->petugas)
            ->get('/petugas/laporan/'.$activity->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('log.activities.0.details', 0));
        $this->actingAs($this->petugas)
            ->get('/petugas/tasks/'.$observationTask->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('reports.0.details', null));
    }

    public function test_completing_growth_observation_task_unblocks_harvest_recording_and_closure(): void
    {
        $observationTask = $this->cycle->tasks()->where('activity_category', 'growth_observation')->firstOrFail();
        $harvestTask = $this->cycle->tasks()->where('activity_category', 'harvest')->firstOrFail();

        $this->actingAs($this->petugas)
            ->post('/petugas/tasks/'.$observationTask->id.'/aktivitas', [
                'schema' => 'taxonomy',
                'work_date' => '2026-11-19',
                'details' => ['growth_phase' => 'Vegetatif'],
                'photos' => [UploadedFile::fake()->image('veg.jpg')],
            ])->assertRedirect();

        $this->actingAs($this->petugas)
            ->post('/petugas/tasks/'.$observationTask->id.'/selesai', ['note' => 'Pengamatan tuntas.'])
            ->assertRedirect();

        $this->assertSame('completed', $observationTask->fresh()->status);

        $this->travelTo(CarbonImmutable::parse('2027-02-08 10:00:00', 'Asia/Jakarta'));

        $this->actingAs($this->petugas)
            ->post('/petugas/tasks/'.$harvestTask->id.'/aktivitas', [
                'schema' => 'taxonomy',
                'work_date' => '2027-02-08',
                'worker_count' => 4,
                'details' => ['area' => 1, 'area_unit' => 'Ha', 'weight' => 5, 'weight_unit' => 'ton'],
                'photos' => [UploadedFile::fake()->image('harvest.jpg')],
            ])->assertRedirect();

        $this->actingAs($this->admin)
            ->post('/kebun/'.$this->farm->id.'/blocks/'.$this->block->id.'/batches/'.$this->cycle->id.'/close-harvest')
            ->assertRedirect();

        $this->assertSame('harvested', $this->cycle->fresh()->status);
    }

    private function member(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $this->team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $user->forceFill(['current_team_id' => $this->team->id])->save();
        $user->unsetRelation('teams')->unsetRelation('currentTeam');

        return $user;
    }
}
