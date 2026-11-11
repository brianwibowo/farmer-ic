<?php

namespace Tests\Feature\Admin;

use App\Enums\TeamRole;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    private Farm $farm;

    private FarmBlock $block;

    private PlantingCycle $subblok;

    protected function setUp(): void
    {
        parent::setUp();
        $this->team = Team::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->join($this->admin, $this->team);
        $this->farm = new Farm([
            'name' => 'Kebun Tugas Admin',
            'land_tenure' => 'owned',
            'total_area_ha' => '10.0000',
        ]);
        $this->farm->forceFill([
            'team_id' => $this->team->id,
            'created_by' => $this->admin->id,
        ])->save();
        $this->block = app(FarmBlockService::class)->create($this->farm, $this->admin, $this->team, [
            'code' => 'B01',
            'name' => 'Blok Utara',
            'area_ha' => '5.0000',
        ]);
        $this->subblok = app(FarmBlockService::class)->createBatch($this->block, $this->admin, $this->team, [
            'reserved_area_ha' => '2.0000',
            'planned_planting_at' => '2026-11-05',
        ]);
        $this->actingAs($this->admin);
    }

    private function join(User $user, Team $team): void
    {
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->unsetRelation('teams')->unsetRelation('currentTeam');
    }

    private function task(array $overrides = [], ?PlantingCycle $subblok = null): PlantingCycleTask
    {
        $subblok ??= $this->subblok;
        $stage = $subblok->stages()->where('code', 'maintenance')->firstOrFail();

        return PlantingCycleTask::query()->create([
            'planting_cycle_id' => $subblok->id,
            'planting_cycle_stage_id' => $stage->id,
            'activity_category' => 'maintenance',
            'activity_subcategory' => 'irrigation',
            'name' => 'Pengairan awal',
            'instructions' => 'Pastikan kelembapan tanah cukup.',
            'offset_days' => 7,
            'tolerance_days' => 2,
            'scheduled_date' => '2026-11-12',
            'due_date' => '2026-11-14',
            'is_required' => true,
            'status' => 'pending',
            ...$overrides,
        ]);
    }

    public function test_admin_can_list_and_filter_tasks_by_block_and_subblok(): void
    {
        $pending = $this->task();
        $completed = $this->task([
            'name' => 'Pemupukan susulan',
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $secondBlock = app(FarmBlockService::class)->create($this->farm, $this->admin, $this->team, [
            'code' => 'B02',
            'name' => 'Blok Selatan',
            'area_ha' => '4.0000',
        ]);
        $secondSubblok = app(FarmBlockService::class)->createBatch($secondBlock, $this->admin, $this->team, [
            'reserved_area_ha' => '1.0000',
            'subblock_label' => 'B',
            'planned_planting_at' => '2026-11-06',
        ]);
        $secondTask = $this->task(['name' => 'Tugas blok selatan'], $secondSubblok);

        $this->get('/admin/tasks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Index')
                ->where('filters.view', 'actionable')
                ->where('tasks.total', 2)
                ->has('blocks', 2)
                ->where('blocks.0.code', 'B01')
                ->where('blocks.1.code', 'B02')
                ->has('subbloks', 2)
                ->where('subbloks.0.id', $this->subblok->id)
                ->where('subbloks.0.label', 'A')
                ->where('subbloks.1.id', $secondSubblok->id)
                ->where('subbloks.1.label', 'B')
                ->where('summary.total', 3)
                ->where('summary.open', 2)
                ->where('summary.completed', 1));

        $this->get('/admin/tasks?block_id='.$this->block->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.block_id', $this->block->id)
                ->where('tasks.total', 1));

        $this->get('/admin/tasks?block_id='.$secondBlock->id.'&subblok_id='.$secondSubblok->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.subblok_id', $secondSubblok->id)
                ->where('tasks.total', 1)
                ->where('tasks.data.0.id', $secondTask->id));

        $this->get('/admin/tasks?status=completed')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Index')
                ->where('tasks.total', 1)
                ->where('tasks.data.0.id', $completed->id));

        $this->get('/admin/tasks?search=Pengairan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.total', 1)
                ->where('tasks.data.0.id', $pending->id));
    }

    public function test_actionable_default_views_and_stable_summary(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00:00', 'Asia/Jakarta'));
        $overdue = $this->task([
            'name' => 'Tugas terlambat',
            'scheduled_date' => '2026-11-01',
            'due_date' => '2026-11-03',
        ]);
        $today = $this->task([
            'name' => 'Tugas hari ini',
            'scheduled_date' => '2026-11-09',
            'due_date' => '2026-11-11',
        ]);
        $upcoming = $this->task([
            'name' => 'Tugas mendatang',
            'scheduled_date' => '2026-11-12',
            'due_date' => '2026-11-14',
        ]);
        $completed = $this->task([
            'name' => 'Tugas selesai',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->get('/admin/tasks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.view', 'actionable')
                ->where('tasks.total', 3)
                ->where('tasks.data.0.id', $overdue->id)
                ->where('tasks.data.0.overdue_days', 7)
                ->where('tasks.data.1.id', $today->id)
                ->where('tasks.data.2.id', $upcoming->id)
                ->where('summary.total', 4)
                ->where('summary.open', 3)
                ->where('summary.overdue', 1)
                ->where('summary.today', 1)
                ->where('summary.completed', 1));

        $this->get('/admin/tasks?view=overdue')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.view', 'overdue')
                ->where('tasks.total', 1)
                ->where('tasks.data.0.id', $overdue->id));

        $this->get('/admin/tasks?view=today')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.total', 1)
                ->where('tasks.data.0.id', $today->id));

        $this->get('/admin/tasks?view=upcoming')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.total', 1)
                ->where('tasks.data.0.id', $upcoming->id));

        $this->get('/admin/tasks?view=completed')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.total', 1)
                ->where('tasks.data.0.id', $completed->id));

        $this->get('/admin/tasks?view=all')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.total', 4)
                ->where('summary.total', 4));

        $this->get('/admin/tasks?search=Mendatang')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.total', 1)
                ->where('tasks.data.0.id', $upcoming->id)
                ->where('summary.total', 4)
                ->where('summary.open', 3));
    }

    public function test_invalid_task_filters_are_rejected(): void
    {
        $this->get('/admin/tasks?block_id=not-a-block&subblok_id=not-a-subblok')
            ->assertSessionHasErrors(['block_id', 'subblok_id']);

        $this->get('/admin/tasks?category=planting')
            ->assertSessionHasErrors('category');

        $this->get('/admin/tasks?status=cancelled')
            ->assertSessionHasErrors('status');

        $this->get('/admin/tasks?view=unknown')
            ->assertSessionHasErrors('view');
    }

    public function test_admin_can_open_task_detail(): void
    {
        $task = $this->task();

        $this->get('/admin/tasks/'.$task->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Show')
                ->where('task.id', $task->id)
                ->where('task.farm.name', 'Kebun Tugas Admin')
                ->where('task.block.code', 'B01')
                ->where('task.can.update', true));
    }

    public function test_admin_can_mark_an_unfinished_task_as_not_done(): void
    {
        $skip = $this->task();

        $this->post('/admin/tasks/'.$skip->id.'/skip', ['reason' => 'Lahan tergenang setelah hujan.'])
            ->assertRedirect();

        $this->assertSame('skipped', $skip->refresh()->status);
        $this->assertSame('Lahan tergenang setelah hujan.', $skip->skip_reason);
        $this->assertNull($skip->completed_at);
        $this->assertNull($skip->completed_by);
    }

    public function test_admin_can_reschedule_an_unfinished_task_without_changing_the_sop_date(): void
    {
        $task = $this->task();

        $this->post('/admin/tasks/'.$task->id.'/reschedule', [
            'rescheduled_date' => '2026-11-20',
            'reschedule_reason' => 'Pekerjaan tertunda karena banjir.',
        ])->assertRedirect();

        $task->refresh();
        $this->assertSame('2026-11-12', $task->scheduled_date->toDateString());
        $this->assertSame('2026-11-20', $task->rescheduled_date->toDateString());
        $this->assertSame('2026-11-22', $task->due_date->toDateString());
        $this->assertSame($this->admin->id, $task->rescheduled_by);
        $this->assertNotNull($task->rescheduled_at);
    }

    public function test_finished_tasks_and_invalid_reasons_are_rejected(): void
    {
        $task = $this->task([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->post('/admin/tasks/'.$task->id.'/skip', ['reason' => 'Alasan cukup.'])
            ->assertSessionHasErrors('status');
        $this->assertSame('completed', $task->refresh()->status);

        $pending = $this->task(['name' => 'Tugas lain']);
        $this->post('/admin/tasks/'.$pending->id.'/skip', ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->post('/admin/tasks/'.$pending->id.'/reschedule', [
            'rescheduled_date' => '20-11-2026',
            'reschedule_reason' => 'Alasan cukup.',
        ])->assertSessionHasErrors('rescheduled_date');
    }

    public function test_cross_tenant_tasks_are_not_listed_or_visible(): void
    {
        $this->task();

        $otherTeam = Team::factory()->create();
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $this->join($otherAdmin, $otherTeam);
        $otherFarm = new Farm([
            'name' => 'Kebun Lain',
            'land_tenure' => 'owned',
            'total_area_ha' => '10.0000',
        ]);
        $otherFarm->forceFill([
            'team_id' => $otherTeam->id,
            'created_by' => $otherAdmin->id,
        ])->save();
        $otherBlock = app(FarmBlockService::class)->create($otherFarm, $otherAdmin, $otherTeam, [
            'code' => 'B01',
            'name' => 'Blok Lain',
            'area_ha' => '5.0000',
        ]);
        $otherSubblok = app(FarmBlockService::class)->createBatch($otherBlock, $otherAdmin, $otherTeam, [
            'reserved_area_ha' => '2.0000',
            'planned_planting_at' => '2026-11-05',
        ]);
        $otherStage = $otherSubblok->stages()->where('code', 'maintenance')->firstOrFail();
        $otherTask = PlantingCycleTask::query()->create([
            'planting_cycle_id' => $otherSubblok->id,
            'planting_cycle_stage_id' => $otherStage->id,
            'activity_category' => 'maintenance',
            'activity_subcategory' => 'irrigation',
            'name' => 'Tugas tenant lain',
            'offset_days' => 7,
            'tolerance_days' => 2,
            'scheduled_date' => '2026-11-12',
            'due_date' => '2026-11-14',
            'is_required' => true,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/tasks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.total', 1)
                ->has('blocks', 1)
                ->has('subbloks', 1));

        $this->get('/admin/tasks/'.$otherTask->id)->assertForbidden();
    }

    public function test_non_admin_roles_cannot_open_admin_task_management(): void
    {
        $petugas = User::factory()->create(['role' => 'petugas']);
        $this->join($petugas, $this->team);
        $owner = User::factory()->create(['role' => 'owner']);
        $this->join($owner, $this->team);

        $this->actingAs($petugas)->get('/admin/tasks')->assertForbidden();
        $this->actingAs($owner)->get('/admin/tasks')->assertForbidden();
    }
}
