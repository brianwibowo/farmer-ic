<?php

namespace Tests\Feature\Activity;

use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\SopTemplate;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use App\Services\PlantingCycleTaskService;
use App\Services\SopTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Integrated PostgreSQL coverage; only the guarded test database is reset. */
class PetugasPlantingActivityTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private User $admin;

    private Team $team;

    private Farm $farm;

    private FarmBlock $block;

    private PlantingCycle $cycle;

    private CropVariety $variety;

    protected function setUp(): void
    {
        parent::setUp();
        // These legacy tests isolate planting, transactions and HST; prerequisite
        // ordering is exercised separately through the real HTTP sequence test.
        $this->mock(\App\Services\Activity\ActivitySequenceService::class, function ($mock): void {
            $mock->shouldReceive('ensureAllowed')->andReturnNull()->byDefault();
            $mock->shouldReceive('blockedReason')->andReturnNull()->byDefault();
        });
        Storage::fake('local');
        $this->team = Team::factory()->create(['is_personal' => false]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actor = User::factory()->create(['role' => 'petugas']);
        foreach ([$this->admin, $this->actor] as $actor) {
            $this->team->members()->attach($actor, ['role' => 'member']);
            $actor->forceFill(['current_team_id' => $this->team->id])->save();
        }
        $this->farm = new Farm(['name' => 'Kebun Realisasi Jagung', 'land_tenure' => 'owned', 'total_area_ha' => '10']);
        $this->farm->forceFill(['team_id' => $this->team->id, 'created_by' => $this->admin->id])->save();
        $this->actor->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->block = app(FarmBlockService::class)->create($this->farm, $this->admin, $this->team, ['code' => 'B01', 'name' => 'Blok Utara', 'area_ha' => '5']);
        $this->cycle = app(FarmBlockService::class)->createBatch($this->block, $this->admin, $this->team, ['reserved_area_ha' => '2', 'planned_planting_at' => now('Asia/Jakarta')->subDays(5)->toDateString()]);
        $commodity = Commodity::create(['name' => 'Jagung']);
        $this->variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Bisi 18']);
        ActivityType::firstOrCreate(['code' => 'planting'], ['name' => 'Penanaman']);
    }

    public function test_staged_planting_uses_second_distinct_work_day_and_reconciles_late_reports(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-04 17:00', 'Asia/Jakarta'));
        $this->sop();
        $this->actingAs($this->actor);
        foreach (['2026-10-26', '2026-10-28'] as $date) {
            $this->post($this->url(), [...$this->payload(), 'area_ha' => '0.4', 'work_date' => $date, 'end_time' => null])->assertSessionHasNoErrors();
        }
        $task = $this->cycle->tasks()->where('activity_subcategory', 'irrigation')->firstOrFail();
        $this->assertSame('2026-10-28', $this->cycle->refresh()->planted_at->toDateString());
        $this->assertSame('2026-11-04', $task->scheduled_date->toDateString());
        $this->artisan('planting:reconcile-windows')->assertSuccessful();
        $this->assertNotNull($this->cycle->refresh()->getAttribute('planting_window_checked_at'));
        $this->post($this->url(), [...$this->payload(), 'area_ha' => '0.4', 'work_date' => '2026-10-27'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-27', $this->cycle->refresh()->planted_at->toDateString());
        $this->assertSame('2026-10-26', $this->cycle->getAttribute('planting_started_on'));
        $this->assertSame('1.2000', $this->cycle->planted_area_ha);
        $this->assertSame('2026-11-03', $task->refresh()->scheduled_date->toDateString());
        $this->assertDatabaseCount('planting_cycle_tasks', 2);
        $this->post($this->url(), [...$this->payload(), 'area_ha' => '0.1', 'work_date' => '2026-10-29'])->assertSessionHasErrors('work_date');
        $this->assertDatabaseCount('farm_activities', 3);
        $this->artisan('planting:reconcile-windows')->assertSuccessful();
        $this->artisan('planting:reconcile-windows')->assertSuccessful();
        $this->assertDatabaseCount('planting_cycle_tasks', 2);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_repeated_same_day_planting_does_not_advance_hst_and_later_day_preserves_overrides(): void
    {
        $this->sop();
        $this->actingAs($this->actor);
        $date = now('Asia/Jakarta')->subDays(2)->toDateString();
        for ($i = 0; $i < 2; $i++) {
            $this->post($this->url(), [...$this->payload(), 'area_ha' => '0.3'])->assertSessionHasNoErrors();
        }
        $this->assertSame($date, $this->cycle->refresh()->planted_at->toDateString());
        $task = $this->cycle->tasks()->where('activity_subcategory', 'irrigation')->firstOrFail();
        $override = now('Asia/Jakarta')->addDays(10)->toDateString();
        $task->update(['rescheduled_date' => $override, 'reschedule_reason' => 'Ketersediaan air', 'rescheduled_by' => $this->admin->id, 'rescheduled_at' => now()]);
        $closed = $this->cycle->tasks()->where('activity_category', 'harvest')->firstOrFail();
        $old = $closed->scheduled_date->toDateString();
        $closed->update(['status' => 'completed', 'completed_at' => now()]);
        $this->post($this->url(), [...$this->payload(), 'area_ha' => '0.3', 'work_date' => now('Asia/Jakarta')->subDay()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame($override, $task->refresh()->effectiveScheduledDate()->toDateString());
        $this->assertSame($old, $closed->refresh()->scheduled_date->toDateString());
        $this->assertSame(now('Asia/Jakarta')->subDay()->toDateString(), $this->cycle->refresh()->planted_at->toDateString());
    }

    public function test_taxonomy_planting_converts_units_and_accepts_an_earlier_late_report(): void
    {
        $this->sop();
        $this->actingAs($this->actor);
        $data = $this->payload();
        unset($data['area_ha'], $data['quantity_kg']);
        $data = [...$data, 'schema' => 'taxonomy', 'area' => 1000, 'area_unit' => 'm²',
            'quantity' => 2, 'quantity_unit' => 'sak', 'bag_size' => 5, 'depth_cm' => 3, 'end_time' => null];
        $this->post($this->url(), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('planting_details', ['area_ha' => '0.1000', 'quantity_kg' => '10.000']);
        $date = \Carbon\CarbonImmutable::parse($data['work_date'])->subDay()->toDateString();
        $this->post($this->url(), [...$data, 'work_date' => $date, 'quantity_unit' => 'butir', 'quantity' => 200])->assertSessionHasNoErrors();
        $this->assertSame($data['work_date'], $this->cycle->refresh()->planted_at->toDateString());
        $this->assertSame($date, $this->cycle->getAttribute('planting_started_on'));
        $this->assertSame('0.2000', $this->cycle->planted_area_ha);
        $this->assertDatabaseHas('planting_details', ['quantity_kg' => null]);
        $this->post($this->url(), [...$data, 'quantity_unit' => 'butir', 'quantity' => 1.5])->assertSessionHasErrors('quantity');
        $this->post($this->url(), [...$data, 'quantity_unit' => 'sak', 'bag_size' => null])->assertSessionHasErrors('bag_size');
        $this->assertDatabaseCount('farm_activities', 2);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    private function sop(): SopTemplate
    {
        $service = app(SopTemplateService::class);
        $sop = $service->create($this->admin, $this->team, ['commodity_id' => $this->variety->commodity_id, 'name' => 'SOP Jagung Pengujian', 'is_active' => true]);
        $version = $sop->versions()->firstOrFail();
        foreach ([['maintenance', 'irrigation', 7], ['harvest', null, 90]] as [$category, $subcategory, $days]) {
            $service->createTask($version, ['activity_category' => $category, 'activity_subcategory' => $subcategory, 'name' => 'Pekerjaan '.$category, 'offset_days' => $days, 'tolerance_days' => 2, 'is_required' => true]);
        }
        $service->publish($version, $this->admin);
        $this->farm->forceFill(['default_sop_template_id' => $sop->id])->save();

        return $sop;
    }

    private function payload(): array
    {
        return ['work_date' => now('Asia/Jakarta')->subDays(2)->toDateString(), 'start_time' => '07:00', 'end_time' => '11:00', 'worker_count' => 4,
            'variety_id' => $this->variety->id, 'area_ha' => '1.5000', 'seed_name' => 'Benih Jagung Bisi 18', 'quantity_kg' => '12.500',
            'planting_pattern' => '20 x 40 cm', 'notes' => 'Penanaman selesai dengan tanah cukup lembap.', 'photos' => [UploadedFile::fake()->image('tanam.jpg')]];
    }

    private function url(): string
    {
        return '/petugas/subbloks/'.$this->cycle->id.'/penanaman';
    }

    public function test_planting_persists_all_records_and_generates_tasks_from_actual_date_atomically(): void
    {
        $this->sop();
        $data = $this->payload();
        $this->actingAs($this->actor)->get('/petugas/penanaman')->assertInertia(fn (Assert $page) => $page->has('cycles', 1)->where('cycles.0.canRecord', true));
        $this->get($this->url())->assertInertia(fn (Assert $page) => $page->component('petugas/PlantingCreate')->where('canRecord', true)->has('varieties', 1));
        $this->post($this->url(), $data)->assertSessionHasNoErrors()->assertRedirect($this->url());
        $cycle = $this->cycle->refresh();
        $this->assertSame($data['work_date'], $cycle->planted_at->toDateString());
        $this->assertSame('active', $cycle->status);
        $this->assertSame('1.5000', $cycle->planted_area_ha);
        $this->assertSame($this->variety->id, $cycle->variety_id);
        $this->assertDatabaseHas('daily_activity_logs', ['farm_id' => $this->farm->id, 'recorded_by' => $this->actor->id]);
        $activity = FarmActivity::firstOrFail();
        $this->assertSame(4, $activity->worker_count);
        $this->assertNull($activity->getAttribute('planting_cycle_task_id'));
        $this->assertDatabaseHas('planting_details', ['activity_id' => $activity->id, 'seed_name' => $data['seed_name']]);
        $this->assertDatabaseHas('planting_cycle_stages', ['planting_cycle_id' => $cycle->id, 'code' => 'planting', 'status' => 'completed']);
        $this->assertSame(2, $cycle->tasks()->count());
        $this->assertSame(now('Asia/Jakarta')->subDays(2)->addDays(7)->toDateString(), $cycle->tasks()->where('activity_subcategory', 'irrigation')->firstOrFail()->scheduled_date->toDateString());
        $photo = ActivityPhoto::firstOrFail();
        Storage::disk('local')->assertExists($photo->image_url);
        $this->get('/petugas/aktivitas/'.$activity->id.'/foto/'.$photo->id)->assertOk();
        $this->get($this->url())->assertInertia(fn (Assert $page) => $page->where('canRecord', true)->has('reports', 1));
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_actual_planting_is_saved_even_without_a_ready_sop_and_can_be_synchronized_later(): void
    {
        $this->actingAs($this->actor)->post($this->url(), $this->payload())->assertRedirect($this->url());
        $this->assertNotNull($this->cycle->refresh()->planted_at);
        $this->assertDatabaseCount('planting_cycle_tasks', 0);
        $this->sop();
        $sync = '/petugas/subbloks/'.$this->cycle->id.'/tasks/sync';
        $this->post($sync)->assertRedirect('/petugas/penanaman');
        $this->post($sync)->assertRedirect('/petugas/penanaman');
        $this->assertDatabaseCount('planting_cycle_tasks', 2);
        $this->assertDatabaseCount('farm_activities', 1);
    }

    public function test_planting_and_sop_follow_up_on_the_same_day_share_one_daily_header(): void
    {
        $this->sop();
        $data = $this->payload();
        $this->actingAs($this->actor)->post($this->url(), $data)->assertSessionHasNoErrors();
        ActivityType::firstOrCreate(['code' => 'irrigation'], ['name' => 'Pengairan']);
        $task = $this->cycle->tasks()->where('activity_subcategory', 'irrigation')->firstOrFail();
        $this->post('/petugas/tasks/'.$task->id.'/aktivitas', [
            'work_date' => $data['work_date'], 'worker_count' => 4, 'notes' => 'Pengairan setelah tanam.',
            'details' => ['area_ha' => '1', 'water_source' => 'Sumur', 'method' => 'Pompa', 'duration_hours' => '1'],
            'photos' => [UploadedFile::fake()->image('pengairan.jpg')],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('daily_activity_logs', 1);
        $this->assertSame([1, 2], FarmActivity::orderBy('position')->pluck('position')->all());
        $this->assertSame(1, FarmActivity::distinct()->count('daily_activity_log_id'));
        $this->assertDatabaseCount('planting_details', 1);
        $this->assertDatabaseCount('irrigation_details', 1);
        $this->get('/petugas/laporan/harian?work_date='.$data['work_date'])->assertInertia(fn (Assert $page) => $page->has('log.activities', 2)->where('log.activities.0.worker_count', 4)->where('log.activities.1.worker_count', 4));
    }

    public function test_actual_date_reschedules_only_pending_tasks_without_changing_the_snapshot(): void
    {
        $this->sop();
        $this->actingAs($this->actor)->post('/petugas/subbloks/'.$this->cycle->id.'/tasks/sync')->assertRedirect();
        $versionId = $this->cycle->refresh()->sop_template_version_id;
        $completed = $this->cycle->tasks()->where('activity_category', 'harvest')->firstOrFail();
        $originalDate = $completed->scheduled_date->toDateString();
        $completed->update(['status' => 'completed', 'completed_at' => now()]);
        $this->post($this->url(), $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($originalDate, $completed->refresh()->scheduled_date->toDateString());
        $this->assertSame($versionId, $this->cycle->refresh()->sop_template_version_id);
        $this->assertDatabaseCount('planting_cycle_tasks', 2);
        $this->assertSame(now('Asia/Jakarta')->subDays(2)->addDays(7)->toDateString(), $this->cycle->tasks()->where('activity_subcategory', 'irrigation')->firstOrFail()->scheduled_date->toDateString());
    }

    public function test_no_anchor_or_inactive_sop_defers_tasks_without_creating_undated_rows(): void
    {
        $sop = $this->sop();
        $this->cycle->update(['planned_planting_at' => null]);
        $sync = '/petugas/subbloks/'.$this->cycle->id.'/tasks/sync';
        $this->actingAs($this->actor)->post($sync)->assertRedirect();
        $this->assertDatabaseCount('planting_cycle_tasks', 0);
        $this->cycle->update(['planned_planting_at' => today()]);
        $sop->update(['is_active' => false]);
        $this->post($sync)->assertRedirect();
        $this->assertDatabaseCount('planting_cycle_tasks', 0);
        $this->post($this->url(), $this->payload())->assertRedirect();
        $this->assertNotNull($this->cycle->refresh()->planted_at);
        $this->assertDatabaseCount('planting_cycle_tasks', 0);
    }

    public function test_actual_planting_keeps_an_admin_schedule_override_and_its_due_date(): void
    {
        $this->sop();
        $this->actingAs($this->actor)->post('/petugas/subbloks/'.$this->cycle->id.'/tasks/sync')->assertRedirect();
        $task = $this->cycle->tasks()->where('activity_subcategory', 'irrigation')->firstOrFail();
        $override = now('Asia/Jakarta')->addDays(10)->toDateString();
        $due = now('Asia/Jakarta')->addDays(12)->toDateString();
        $task->update([
            'rescheduled_date' => $override, 'due_date' => $due,
            'reschedule_reason' => 'Penyesuaian ketersediaan air oleh Admin.',
            'rescheduled_by' => $this->admin->id, 'rescheduled_at' => now(),
        ]);
        $this->post($this->url(), $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($override, $task->refresh()->effectiveScheduledDate()->toDateString());
        $this->assertSame($due, $task->due_date->toDateString());
        $this->assertSame(now('Asia/Jakarta')->subDays(2)->addDays(7)->toDateString(), $task->scheduled_date->toDateString());
        $this->get('/petugas/tasks/'.$task->id)->assertInertia(fn (Assert $page) => $page->where('task.scheduled_date', $override)->where('task.due_date', $due));
    }

    public function test_duplicate_planting_and_invalid_area_do_not_overwrite_or_create_partial_reports(): void
    {
        $data = $this->payload();
        $data['area_ha'] = '3';
        $this->actingAs($this->actor)->post($this->url(), $data)->assertSessionHasErrors('area_ha');
        $this->assertDatabaseCount('farm_activities', 0);
        $this->assertNull($this->cycle->refresh()->planted_at);
        $this->post($this->url(), $this->payload())->assertRedirect();
        $date = $this->cycle->refresh()->planted_at->toDateString();
        $data = $this->payload();
        $data['work_date'] = now('Asia/Jakarta')->toDateString();
        $this->post($this->url(), $data)->assertSessionHasErrors('area_ha');
        $this->assertDatabaseCount('farm_activities', 1);
        $this->assertSame($date, $this->cycle->refresh()->planted_at->toDateString());
    }

    public function test_php_upload_failure_returns_actionable_feedback_without_persisting_planting(): void
    {
        $data = $this->payload();
        $file = $data['photos'][0];
        $data['photos'] = [new UploadedFile($file->getPathname(), 'tanam.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true)];

        $this->actingAs($this->actor)->post($this->url(), $data)
            ->assertSessionHasErrors(['photos.0' => \App\Support\PhotoUploadFeedback::messages($data['photos'])['photos.0.uploaded']]);

        $this->assertNull($this->cycle->refresh()->planted_at);
        $this->assertDatabaseCount('farm_activities', 0);
        $this->assertDatabaseCount('daily_activity_logs', 0);
        $this->assertDatabaseCount('activity_photos', 0);
    }

    public function test_missing_php_temporary_directory_is_not_misreported_as_a_size_limit(): void
    {
        $data = $this->payload();
        $file = $data['photos'][0];
        $data['photos'] = [new UploadedFile($file->getPathname(), 'tanam.jpg', 'image/jpeg', UPLOAD_ERR_NO_TMP_DIR, true)];

        $this->actingAs($this->actor)->post($this->url(), $data)
            ->assertSessionHasErrors(['photos.0' => 'Foto gagal diunggah: folder sementara PHP tidak tersedia. Pengelola perlu mengatur upload_tmp_dir dan memulai ulang server.']);

        $this->assertNull($this->cycle->refresh()->planted_at);
        $this->assertDatabaseCount('farm_activities', 0);
        $this->assertDatabaseCount('activity_photos', 0);
    }

    public function test_authorization_and_forged_context_are_rejected(): void
    {
        $data = $this->payload();
        $data['farm_id'] = $this->farm->id;
        $data['status'] = 'active';
        $this->actingAs($this->actor)->post($this->url(), $data)->assertSessionHasErrors(['farm_id', 'status']);
        $foreign = Team::factory()->create(['is_personal' => false]);
        $this->team->members()->detach($this->actor);
        $foreign->members()->attach($this->actor, ['role' => 'member']);
        $this->actor->forceFill(['current_team_id' => $foreign->id])->save();
        $this->actor->unsetRelation('teams')->unsetRelation('currentTeam');
        $this->get($this->url())->assertForbidden();
        $this->post($this->url(), $this->payload())->assertForbidden();
        $this->post('/petugas/subbloks/'.$this->cycle->id.'/tasks/sync')->assertForbidden();
        $this->assertDatabaseCount('farm_activities', 0);
    }

    public function test_creating_a_subblok_through_the_application_triggers_generation_if_ready(): void
    {
        $this->sop();
        $this->actingAs($this->actor)->post('/kebun/'.$this->farm->id.'/blocks/'.$this->block->id.'/batches', ['subblock_label' => 'B', 'reserved_area_ha' => '1', 'planned_planting_at' => today()->toDateString()])->assertSessionHasNoErrors()->assertRedirect();
        $cycle = PlantingCycle::where('subblock_label', 'B')->firstOrFail();
        $this->assertSame(2, $cycle->tasks()->count());
        $this->assertNull($cycle->planted_at);
    }

    public function test_a_generation_failure_rolls_back_planting_details_and_uploaded_files(): void
    {
        $mock = \Mockery::mock(PlantingCycleTaskService::class);
        $mock->shouldReceive('generateForSubblok')->once()->andThrow(new \RuntimeException('Simulated generation failure'));
        $this->app->instance(PlantingCycleTaskService::class, $mock);
        $this->actingAs($this->actor)->post($this->url(), $this->payload())->assertStatus(500);
        $this->assertNull($this->cycle->refresh()->planted_at);
        $this->assertDatabaseCount('farm_activities', 0);
        $this->assertDatabaseCount('daily_activity_logs', 0);
        $this->assertDatabaseCount('planting_details', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
