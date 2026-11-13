<?php

namespace Tests\Feature\Activity;

use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use App\Services\Notifications\NotificationService;
use App\Services\PlantingCycleTaskManagementService;
use App\Services\SopTemplateService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** HTTP, policy and transaction coverage on the guarded PostgreSQL test schema. */
class HarvestEstimateTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    private User $petugas;

    private Farm $farm;

    private PlantingCycle $cycle;

    private CropVariety $variety;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-13 12:00', 'Asia/Jakarta'));
        Storage::fake('local');
        $this->team = Team::factory()->create(['is_personal' => false]);
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->petugas = User::factory()->create(['role' => 'petugas', 'is_active' => true]);
        foreach ([$this->admin, $this->petugas] as $actor) {
            $this->team->members()->attach($actor, ['role' => 'member']);
            $actor->forceFill(['current_team_id' => $this->team->id])->save();
        }
        $this->farm = new Farm(['name' => 'Kebun Estimasi Panen', 'land_tenure' => 'owned', 'total_area_ha' => '10']);
        $this->farm->forceFill(['team_id' => $this->team->id, 'created_by' => $this->admin->id])->save();
        $this->petugas->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $blocks = app(FarmBlockService::class);
        $block = $blocks->create($this->farm, $this->admin, $this->team, ['code' => 'B01', 'name' => 'Blok Estimasi', 'area_ha' => '5']);
        $this->cycle = $blocks->createBatch($block, $this->admin, $this->team, ['reserved_area_ha' => '2']);
        $commodity = Commodity::create(['name' => 'Jagung Estimasi']);
        $this->farm->forceFill(['commodity_id' => $commodity->id])->save();
        $this->variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Bisi Estimasi']);
        ActivityType::firstOrCreate(['code' => 'planting'], ['name' => 'Penanaman']);
        ActivityType::firstOrCreate(['code' => 'harvest'], ['name' => 'Panen']);
    }

    /** @param list<int> $offsets */
    private function sop(array $offsets): void
    {
        $service = app(SopTemplateService::class);
        $template = $service->create($this->admin, $this->team, [
            'commodity_id' => $this->variety->commodity_id, 'name' => 'SOP Estimasi Jagung', 'is_active' => true,
        ]);
        $version = $template->versions()->firstOrFail();
        foreach ($offsets as $offset) {
            $service->createTask($version, [
                'activity_category' => 'harvest', 'activity_subcategory' => null, 'name' => 'Panen HST '.$offset,
                'offset_days' => $offset, 'tolerance_days' => 2, 'is_required' => true,
            ]);
        }
        $service->publish($version, $this->admin);
        $this->farm->forceFill(['default_sop_template_id' => $template->id])->save();
        $this->farm->unsetRelation('defaultSopTemplate');
    }

    private function plant(string $date = '2026-11-12'): void
    {
        $this->actingAs($this->petugas)->post('/petugas/subbloks/'.$this->cycle->id.'/penanaman', [
            'work_date' => $date, 'start_time' => '07:00', 'worker_count' => 2, 'variety_id' => $this->variety->id,
            'area_ha' => '0.5', 'seed_name' => 'Benih Jagung Bisi', 'quantity_kg' => '5',
            'photos' => [UploadedFile::fake()->image('tanam.jpg')],
        ])->assertSessionHasNoErrors();
    }

    private function harvest(int $offset = 90): PlantingCycleTask
    {
        return $this->cycle->tasks()->where('activity_category', 'harvest')->where('offset_days', $offset)->firstOrFail();
    }

    public function test_planting_generates_the_estimate_and_reanchors_it_with_pending_tasks(): void
    {
        $this->sop([90, 110]);
        $this->plant();
        $this->assertSame('2027-02-10', $this->cycle->fresh()->estimated_harvest_at->toDateString());
        $this->assertSame('2027-02-10', $this->harvest()->scheduled_date->toDateString());
        $this->get('/petugas/kebun')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('garden.batches.data.0.estimated_harvest_at', '2027-02-10'));

        $this->plant('2026-11-13');
        $this->assertSame('2026-11-13', $this->cycle->fresh()->planted_at->toDateString());
        $this->assertSame('2027-02-11', $this->cycle->fresh()->estimated_harvest_at->toDateString());
        $this->assertSame('2027-02-11', $this->harvest()->fresh()->scheduled_date->toDateString());
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_reschedule_and_skip_use_the_earliest_open_effective_harvest_date(): void
    {
        $this->sop([90, 110]);
        $this->plant();
        $first = $this->harvest(90);
        $second = $this->harvest(110);
        $first->forceFill(['status' => 'in_progress'])->save();
        $this->actingAs($this->admin)->post('/admin/tasks/'.$first->id.'/reschedule', [
            'rescheduled_date' => '2027-03-05', 'reschedule_reason' => 'Menyesuaikan kesiapan tanaman.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2027-02-10', $first->fresh()->scheduled_date->toDateString());
        $this->assertSame('2027-03-02', $this->cycle->fresh()->estimated_harvest_at->toDateString());
        $this->post('/admin/tasks/'.$second->id.'/skip', ['reason' => 'Panen kedua tidak diperlukan.'])->assertSessionHasNoErrors();
        $this->assertSame('2027-03-05', $this->cycle->fresh()->estimated_harvest_at->toDateString());
        $this->post('/admin/tasks/'.$first->id.'/skip', ['reason' => 'Panen ditunda sampai ada jadwal baru.'])->assertSessionHasNoErrors();
        $this->assertNull($this->cycle->fresh()->estimated_harvest_at);
        $this->actingAs($this->petugas)->get('/petugas/kebun')->assertInertia(fn (Assert $page) => $page
            ->where('garden.batches.data.0.estimated_harvest_at', null));
    }

    public function test_missing_sop_does_not_invent_an_estimate(): void
    {
        $this->plant();
        $this->assertSame(0, $this->cycle->tasks()->count());
        $this->assertNull($this->cycle->fresh()->estimated_harvest_at);
        $this->get('/petugas/kebun')->assertInertia(fn (Assert $page) => $page
            ->where('garden.batches.data.0.estimated_harvest_at', null));
    }

    public function test_existing_estimate_is_preserved_until_the_harvest_schedule_changes(): void
    {
        $this->sop([90]);
        $this->cycle->forceFill(['estimated_harvest_at' => '2027-03-20'])->save();
        $this->plant();
        $this->assertSame('2027-03-20', $this->cycle->fresh()->estimated_harvest_at->toDateString());
        $this->post('/petugas/subbloks/'.$this->cycle->id.'/tasks/sync')->assertSessionHasNoErrors();
        $this->assertSame('2027-03-20', $this->cycle->fresh()->estimated_harvest_at->toDateString());

        $task = $this->harvest();
        $this->actingAs($this->admin)->post('/admin/tasks/'.$task->id.'/reschedule', [
            'rescheduled_date' => '2027-02-20', 'reschedule_reason' => 'Menyesuaikan kondisi kebun.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2027-02-20', $this->cycle->fresh()->estimated_harvest_at->toDateString());
    }

    public function test_explicit_task_sync_can_fill_an_empty_existing_estimate_without_changing_tasks(): void
    {
        $this->sop([90]);
        $this->plant();
        $task = $this->harvest();
        $this->cycle->forceFill(['estimated_harvest_at' => null])->save();
        $this->post('/petugas/subbloks/'.$this->cycle->id.'/tasks/sync')->assertSessionHasNoErrors();
        $this->assertSame('2027-02-10', $this->cycle->fresh()->estimated_harvest_at->toDateString());
        $this->assertSame($task->id, $this->harvest()->id);
    }

    public function test_no_effective_schedule_change_does_not_overwrite_a_legacy_estimate(): void
    {
        $this->sop([90]);
        $this->plant();
        $task = $this->harvest();
        $this->actingAs($this->admin)->post('/admin/tasks/'.$task->id.'/reschedule', [
            'rescheduled_date' => '2027-02-20', 'reschedule_reason' => 'Menyesuaikan kondisi kebun.',
        ])->assertSessionHasNoErrors();
        $task->forceFill(['scheduled_date' => '2027-02-09'])->save();
        $this->cycle->forceFill(['estimated_harvest_at' => '2027-03-20'])->save();

        $this->actingAs($this->petugas)->post('/petugas/subbloks/'.$this->cycle->id.'/tasks/sync')->assertSessionHasNoErrors();
        $this->assertSame('2027-02-10', $task->fresh()->scheduled_date->toDateString());
        $this->assertSame('2027-02-20', $task->fresh()->effectiveScheduledDate()->toDateString());
        $this->assertSame('2027-03-20', $this->cycle->fresh()->estimated_harvest_at->toDateString());

        $this->actingAs($this->admin)->post('/admin/tasks/'.$task->id.'/reschedule', [
            'rescheduled_date' => '2027-02-20', 'reschedule_reason' => 'Alasan jadwal diperbarui.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2027-03-20', $this->cycle->fresh()->estimated_harvest_at->toDateString());
        $this->post('/admin/tasks/'.$task->id.'/reschedule', [
            'rescheduled_date' => '2027-02-21', 'reschedule_reason' => 'Tanggal panen disesuaikan.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2027-02-21', $this->cycle->fresh()->estimated_harvest_at->toDateString());
    }

    public function test_planting_anchor_change_keeps_an_admin_override_and_synchronizes_the_estimate(): void
    {
        $this->sop([90]);
        $this->plant();
        $task = $this->harvest();
        $this->actingAs($this->admin)->post('/admin/tasks/'.$task->id.'/reschedule', [
            'rescheduled_date' => '2027-02-20', 'reschedule_reason' => 'Menyesuaikan kondisi kebun.',
        ])->assertSessionHasNoErrors();
        $this->cycle->forceFill(['estimated_harvest_at' => '2027-03-20'])->save();

        $this->plant('2026-11-13');
        $this->assertSame('2027-02-11', $task->fresh()->scheduled_date->toDateString());
        $this->assertSame('2027-02-20', $task->fresh()->effectiveScheduledDate()->toDateString());
        $this->assertSame('2027-02-20', $this->cycle->fresh()->estimated_harvest_at->toDateString());
    }

    public function test_planting_anchor_change_synchronizes_an_in_progress_harvest_without_moving_its_schedule(): void
    {
        $this->sop([90]);
        $this->plant();
        $task = $this->harvest();
        $task->forceFill(['status' => 'in_progress'])->save();
        $this->cycle->forceFill(['estimated_harvest_at' => '2027-03-20'])->save();

        $this->plant('2026-11-13');
        $this->assertSame('2026-11-13', $this->cycle->fresh()->planted_at->toDateString());
        $this->assertSame('2027-02-10', $task->fresh()->effectiveScheduledDate()->toDateString());
        $this->assertSame('2027-02-10', $this->cycle->fresh()->estimated_harvest_at->toDateString());
    }

    public function test_undated_task_rescheduling_returns_validation_feedback(): void
    {
        $this->sop([90]);
        $this->plant();
        $task = $this->harvest();
        $task->forceFill(['scheduled_date' => null, 'due_date' => null])->save();
        $this->actingAs($this->admin)->post('/admin/tasks/'.$task->id.'/reschedule', [
            'rescheduled_date' => '2027-02-20', 'reschedule_reason' => 'Menyesuaikan kondisi kebun.',
        ])->assertSessionHasErrors('rescheduled_date');
        $this->assertNull($task->fresh()->rescheduled_date);
        $this->assertSame('2027-02-10', $this->cycle->fresh()->estimated_harvest_at->toDateString());
    }

    public function test_harvest_reschedule_before_planting_is_rejected_without_changing_data(): void
    {
        $this->sop([90]);
        $this->plant();
        $task = $this->harvest();
        $this->actingAs($this->admin)->post('/admin/tasks/'.$task->id.'/reschedule', [
            'rescheduled_date' => '2026-11-11', 'reschedule_reason' => 'Menyesuaikan kondisi kebun.',
        ])->assertSessionHasErrors('rescheduled_date');
        $this->assertNull($task->fresh()->rescheduled_date);
        $this->assertSame('2027-02-10', $this->cycle->fresh()->estimated_harvest_at->toDateString());
        $this->get('/admin/tasks/'.$task->id)->assertInertia(fn (Assert $page) => $page
            ->where('task.subblok.planted_at', '2026-11-12'));
    }

    public function test_zero_offset_reanchors_atomically(): void
    {
        $this->sop([0]);
        $this->plant();
        $this->assertSame('2026-11-12', $this->cycle->fresh()->estimated_harvest_at->toDateString());
        $this->plant('2026-11-13');
        $this->assertSame('2026-11-13', $this->cycle->fresh()->estimated_harvest_at->toDateString());
        $this->assertSame('2026-11-13', $this->harvest(0)->fresh()->scheduled_date->toDateString());
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_planting_anchor_cannot_move_past_a_harvest_override(): void
    {
        $this->sop([90]);
        $this->plant();
        $task = $this->harvest();
        $this->actingAs($this->admin)->post('/admin/tasks/'.$task->id.'/reschedule', [
            'rescheduled_date' => '2026-11-12', 'reschedule_reason' => 'Jadwal disesuaikan Admin.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2027-02-10', $task->fresh()->scheduled_date->toDateString());
        $this->assertSame('2026-11-12', $task->fresh()->rescheduled_date->toDateString());
        $this->assertSame('2026-11-14', $task->fresh()->due_date->toDateString());
        $this->actingAs($this->petugas)->post('/petugas/subbloks/'.$this->cycle->id.'/penanaman', [
            'work_date' => '2026-11-13', 'start_time' => '07:00', 'worker_count' => 2, 'variety_id' => $this->variety->id,
            'area_ha' => '0.5', 'seed_name' => 'Benih Jagung Bisi', 'quantity_kg' => '5',
            'photos' => [UploadedFile::fake()->image('tanam-lanjutan.jpg')],
        ])->assertSessionHasErrors('work_date');
        $this->assertSame('2026-11-12', $this->cycle->fresh()->planted_at->toDateString());
        $this->assertSame('2026-11-12', $this->cycle->fresh()->estimated_harvest_at->toDateString());
        $this->assertSame(1, $this->cycle->activities()->count());
    }

    public function test_anchor_change_does_not_discard_an_unrelated_legacy_estimate(): void
    {
        $this->cycle->forceFill(['estimated_harvest_at' => '2026-11-12'])->save();
        $this->plant();
        $this->actingAs($this->petugas)->post('/petugas/subbloks/'.$this->cycle->id.'/penanaman', [
            'work_date' => '2026-11-13', 'start_time' => '07:00', 'worker_count' => 2, 'variety_id' => $this->variety->id,
            'area_ha' => '0.5', 'seed_name' => 'Benih Jagung Bisi', 'quantity_kg' => '5',
            'photos' => [UploadedFile::fake()->image('tanam-lanjutan.jpg')],
        ])->assertSessionHasErrors('work_date');
        $this->assertSame('2026-11-12', $this->cycle->fresh()->planted_at->toDateString());
        $this->assertSame('2026-11-12', $this->cycle->fresh()->estimated_harvest_at->toDateString());
        $this->assertSame(1, $this->cycle->activities()->count());
    }

    public function test_constraint_rollback_refuses_to_discard_earlier_reschedules(): void
    {
        $this->sop([90]);
        $this->plant();
        $task = $this->harvest();
        $this->actingAs($this->admin)->post('/admin/tasks/'.$task->id.'/reschedule', [
            'rescheduled_date' => '2026-11-12', 'reschedule_reason' => 'Jadwal disesuaikan Admin.',
        ])->assertSessionHasNoErrors();

        $migration = require database_path('migrations/2026_11_13_000001_allow_early_task_reschedule.php');
        try {
            $migration->down();
            $this->fail('The old constraint must not be restored over an earlier reschedule.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('preserve and review', $exception->getMessage());
        }
        $this->assertSame('2026-11-12', $task->fresh()->rescheduled_date->toDateString());
        $this->assertSame('2026-11-14', $task->fresh()->due_date->toDateString());
    }

    public function test_harvest_schedule_requires_authorized_parent_cycle_and_farm(): void
    {
        $this->sop([90]);
        $this->plant();
        $task = $this->harvest();
        $original = config('access.roles');
        foreach (['planting_cycles.view', 'planting_cycles.update', 'farms.view'] as $grant) {
            $restricted = $original;
            unset($restricted['admin'][$grant]);
            config(['access.roles' => $restricted]);
            $this->actingAs($this->admin)->post('/admin/tasks/'.$task->id.'/reschedule', [
                'rescheduled_date' => '2027-02-20', 'reschedule_reason' => 'Menyesuaikan kondisi kebun.',
            ])->assertForbidden();
        }
        config(['access.roles' => $original]);
        $owner = User::factory()->create(['role' => 'owner']);
        $this->team->members()->attach($owner, ['role' => 'member']);
        $owner->forceFill(['current_team_id' => $this->team->id])->save();
        $this->actingAs($owner)->post('/admin/tasks/'.$task->id.'/skip', ['reason' => 'Tidak dikerjakan.'])->assertForbidden();
        $this->actingAs($this->petugas)->post('/admin/tasks/'.$task->id.'/skip', ['reason' => 'Tidak dikerjakan.'])->assertForbidden();
        $this->assertSame('pending', $task->fresh()->status);
        $this->assertSame('2027-02-10', $this->cycle->fresh()->estimated_harvest_at->toDateString());

        $foreign = User::factory()->create(['role' => 'admin']);
        $otherTeam = Team::factory()->create(['is_personal' => false]);
        $otherTeam->members()->attach($foreign, ['role' => 'member']);
        $foreign->forceFill(['current_team_id' => $otherTeam->id])->save();
        $this->actingAs($foreign)->post('/admin/tasks/'.$task->id.'/skip', ['reason' => 'Tidak dikerjakan.'])->assertForbidden();
        try {
            app(PlantingCycleTaskManagementService::class)->skip($task, $foreign, 'Tidak dikerjakan.');
            $this->fail('The service must not lock a task outside the actor scope.');
        } catch (ModelNotFoundException) {
            $this->assertSame('pending', $task->fresh()->status);
        }
    }

    public function test_synchronization_requires_visibility_of_all_tasks_in_the_parent_farm(): void
    {
        $this->sop([90]);
        $this->plant();
        $cycle = $this->cycle->fresh();
        $this->assertTrue(Gate::forUser($this->admin)->allows('synchronize', [PlantingCycleTask::class, $cycle]));
        $this->assertTrue(Gate::forUser($this->petugas)->allows('synchronize', [PlantingCycleTask::class, $cycle]));

        $roles = config('access.roles');
        $roles['admin']['planting_cycle_tasks.view'] = 'own';
        config(['access.roles' => $roles]);
        $this->assertFalse(Gate::forUser($this->admin)->allows('synchronize', [PlantingCycleTask::class, $cycle]));
        $this->assertSame('2027-02-10', $cycle->fresh()->estimated_harvest_at->toDateString());
    }

    public function test_reconciliation_continues_after_a_cycle_with_an_invalid_harvest_override(): void
    {
        $this->sop([90]);
        $this->plant();
        $this->plant('2026-11-13');
        $blocked = $this->cycle->fresh();
        $blocked->forceFill(['planted_at' => '2026-11-12', 'estimated_harvest_at' => '2026-11-12'])->save();
        $task = $this->harvest();
        $task->forceFill(['rescheduled_date' => '2026-11-12', 'due_date' => '2026-11-14'])->save();

        $this->cycle = app(FarmBlockService::class)->createBatch($blocked->block, $this->admin, $this->team, ['reserved_area_ha' => '2']);
        $this->plant();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00', 'Asia/Jakarta'));
        $this->artisan('planting:reconcile-windows')->assertFailed();

        $this->assertNull($blocked->fresh()->getAttribute('planting_window_checked_at'));
        $this->assertSame('2026-11-12', $blocked->fresh()->planted_at->toDateString());
        $this->assertSame('2026-11-12', $blocked->fresh()->estimated_harvest_at->toDateString());
        $this->assertNotNull($this->cycle->fresh()->getAttribute('planting_window_checked_at'));
    }

    public function test_reconciliation_syncs_an_in_progress_harvest_when_its_anchor_changes(): void
    {
        $this->sop([90]);
        $this->plant();
        $this->plant('2026-11-13');
        $this->cycle->forceFill(['planted_at' => '2026-11-12', 'estimated_harvest_at' => '2027-03-20'])->save();
        $task = $this->harvest();
        $task->forceFill(['status' => 'in_progress', 'scheduled_date' => '2027-02-10', 'due_date' => '2027-02-12'])->save();

        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00', 'Asia/Jakarta'));
        $this->artisan('planting:reconcile-windows')->assertSuccessful();
        $this->assertSame('2026-11-13', $this->cycle->fresh()->planted_at->toDateString());
        $this->assertSame('2027-02-10', $task->fresh()->scheduled_date->toDateString());
        $this->assertSame('2027-02-10', $this->cycle->fresh()->estimated_harvest_at->toDateString());
    }

    public function test_owner_sees_the_same_estimate_and_effective_harvest_schedule(): void
    {
        $this->sop([90]);
        $this->plant();
        $task = $this->harvest();
        $this->actingAs($this->admin)->post('/admin/tasks/'.$task->id.'/reschedule', [
            'rescheduled_date' => '2027-02-20', 'reschedule_reason' => 'Menyesuaikan kondisi kebun.',
        ])->assertSessionHasNoErrors();
        $owner = User::factory()->create(['role' => 'owner']);
        $this->team->members()->attach($owner, ['role' => 'member']);
        $owner->forceFill(['current_team_id' => $this->team->id])->save();

        $this->actingAs($owner->fresh())->get('/kebun/'.$this->farm->id.'/blok/'.$this->cycle->block_id)
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('cycles.0.estimated_harvest_at', '2027-02-20')
            ->where('cycles.0.tasks.0.scheduled_date', '2027-02-10')
            ->where('cycles.0.tasks.0.effective_scheduled_date', '2027-02-20'));
    }

    public function test_failure_to_save_estimate_rolls_back_the_task_reschedule(): void
    {
        $this->sop([90]);
        $this->plant();
        $task = $this->harvest();
        Gate::forUser($this->admin)->authorize('update', $task);
        PlantingCycle::saving(function (PlantingCycle $cycle): void {
            if ($cycle->isDirty('estimated_harvest_at')) {
                throw new \RuntimeException('Simulated estimate write failure.');
            }
        });
        try {
            app(PlantingCycleTaskManagementService::class)->reschedule($task, $this->admin, [
                'rescheduled_date' => '2027-02-20', 'reschedule_reason' => 'Menyesuaikan kondisi kebun.',
            ]);
            $this->fail('The simulated failure must roll back the schedule.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated estimate write failure.', $exception->getMessage());
        } finally {
            PlantingCycle::flushEventListeners();
        }
        $this->assertNull($task->fresh()->rescheduled_date);
        $this->assertSame('2027-02-10', $this->cycle->fresh()->estimated_harvest_at->toDateString());
    }

    public function test_notification_uses_the_updated_estimate_and_rejects_the_old_schedule(): void
    {
        $this->sop([90]);
        $this->plant();
        $task = $this->harvest();
        config(['operational_notifications.enabled' => true]);
        Bus::fake();
        $this->travelTo(CarbonImmutable::parse('2027-02-03 10:00', 'Asia/Jakarta'));
        $notifications = app(NotificationService::class);
        $data = ['kind' => 'harvest_reminder', 'user_id' => $this->petugas->id, 'team_id' => $this->team->id,
            'farm_id' => $this->farm->id, 'cycle_id' => $this->cycle->id, 'harvest_date' => '2027-02-10', 'event_date' => '2027-02-03'];
        $this->assertNotNull($notifications->validRecipient($data));
        $this->actingAs($this->admin)->post('/admin/tasks/'.$task->id.'/reschedule', [
            'rescheduled_date' => '2027-02-15', 'reschedule_reason' => 'Menyesuaikan kondisi kebun.',
        ])->assertSessionHasNoErrors();
        $this->assertNull($notifications->validRecipient($data));
        $this->assertSame(0, $notifications->harvestReminders());
        $this->travelTo(CarbonImmutable::parse('2027-02-08 10:00', 'Asia/Jakarta'));
        $this->assertSame(1, $notifications->harvestReminders());
    }

    public function test_closing_harvest_preserves_the_last_estimate_as_history(): void
    {
        $this->sop([90]);
        $this->plant();
        $task = $this->harvest();
        $this->actingAs($this->petugas)->post('/petugas/tasks/'.$task->id.'/aktivitas', [
            'work_date' => '2026-11-13', 'start_time' => '07:00', 'worker_count' => 2,
            'details' => ['area_ha' => '0.1', 'weight_ton' => '0.1', 'method' => 'Manual'],
            'photos' => [UploadedFile::fake()->image('panen.jpg')],
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/kebun/'.$this->farm->id.'/blocks/'.$this->cycle->block_id.'/batches/'.$this->cycle->id.'/close-harvest')
            ->assertSessionHasNoErrors();
        $this->assertSame('harvested', $this->cycle->fresh()->status);
        $this->assertSame('completed', $task->fresh()->status);
        $this->assertSame('2027-02-10', $this->cycle->fresh()->estimated_harvest_at->toDateString());
        $this->actingAs($this->petugas)->get('/petugas/kebun')->assertInertia(fn (Assert $page) => $page
            ->where('garden.batches.data.0.status', 'harvested')
            ->where('garden.batches.data.0.estimated_harvest_at', '2027-02-10'));
    }
}
