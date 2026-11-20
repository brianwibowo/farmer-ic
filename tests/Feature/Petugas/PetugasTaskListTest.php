<?php

namespace Tests\Feature\Petugas;

use App\Enums\TeamRole;
use App\Models\Commodity;
use App\Models\CropVariety;
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

class PetugasTaskListTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    private Farm $farm;

    private FarmBlock $block;

    private PlantingCycle $subblok;

    private CropVariety $variety;

    protected function setUp(): void
    {
        parent::setUp();
        $this->team = Team::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->join($this->admin, $this->team);

        [$this->farm, $this->block, $this->subblok] = $this->makeFarm(
            'Kebun Petugas',
            'B01',
            $this->team,
            $this->admin,
        );

        $commodity = Commodity::query()->create(['name' => 'Jagung']);
        $this->variety = CropVariety::query()->create([
            'commodity_id' => $commodity->id,
            'name' => 'Bisi 18',
        ]);
        $this->subblok->forceFill(['variety_id' => $this->variety->id, 'planted_at' => CarbonImmutable::today()->subDays(12), 'planted_area_ha' => '2', 'status' => 'active'])->save();
    }

    private function join(User $user, Team $team): void
    {
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->unsetRelation('teams')->unsetRelation('currentTeam');
    }

    /** @return array{Farm, FarmBlock, PlantingCycle} */
    private function makeFarm(string $name, string $code, Team $team, User $actor): array
    {
        $farm = new Farm([
            'name' => $name,
            'land_tenure' => 'owned',
            'total_area_ha' => '10.0000',
        ]);
        $farm->forceFill([
            'team_id' => $team->id,
            'created_by' => $actor->id,
        ])->save();

        $block = app(FarmBlockService::class)->create($farm, $actor, $team, [
            'name' => 'Blok '.$code,
            'area_ha' => '5.0000',
        ]);

        $subblok = app(FarmBlockService::class)->createBatch($block, $actor, $team, [
            'reserved_area_ha' => '2.0000',
            'subblock_label' => 'A',
            'planned_planting_at' => CarbonImmutable::today()->subDays(20)->toDateString(),
        ]);

        return [$farm, $block, $subblok];
    }

    private function petugas(Farm $farm): User
    {
        $petugas = User::factory()->create([
            'role' => 'petugas',
            'assigned_farm_id' => $farm->id,
        ]);
        $this->join($petugas, $this->team);

        return $petugas;
    }

    /** @param array<string, mixed> $overrides */
    private function task(PlantingCycle $subblok, string $name, array $overrides = []): PlantingCycleTask
    {
        $stageCode = $overrides['stage'] ?? 'maintenance';
        unset($overrides['stage']);
        $stage = $subblok->stages()->where('code', $stageCode)->firstOrFail();
        $today = CarbonImmutable::today();

        return PlantingCycleTask::query()->create([
            'planting_cycle_id' => $subblok->id,
            'planting_cycle_stage_id' => $stage->id,
            'activity_category' => 'maintenance',
            'activity_subcategory' => null,
            'name' => $name,
            'instructions' => 'Ikuti instruksi SOP.',
            'offset_days' => 7,
            'tolerance_days' => 2,
            'scheduled_date' => $today->toDateString(),
            'due_date' => $today->addDays(2)->toDateString(),
            'is_required' => true,
            'status' => 'pending',
            ...$overrides,
        ]);
    }

    public function test_petugas_sees_real_tasks_only_from_the_assigned_farm(): void
    {
        $today = CarbonImmutable::today();
        $petugas = $this->petugas($this->farm);

        $overdue = $this->task($this->subblok, 'Pengairan', [
            'offset_days' => 7,
            'tolerance_days' => 1,
            'scheduled_date' => $today->subDays(5)->toDateString(),
            'due_date' => $today->subDays(4)->toDateString(),
        ]);
        $this->task($this->subblok, 'Pemupukan susulan pertama', [
            'offset_days' => 11,
            'tolerance_days' => 3,
            'scheduled_date' => $today->subDays(1)->toDateString(),
            'due_date' => $today->addDays(2)->toDateString(),
        ]);
        $this->task($this->subblok, 'Panen', [
            'stage' => 'harvest',
            'activity_category' => 'harvest',
            'activity_subcategory' => null,
            'offset_days' => 90,
            'scheduled_date' => $today->subDays(10)->toDateString(),
            'due_date' => $today->subDays(9)->toDateString(),
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        [, , $otherSubblok] = $this->makeFarm('Kebun Tetangga', 'B02', $this->team, $this->admin);
        $this->task($otherSubblok, 'Tugas kebun lain');

        $otherTeam = Team::factory()->create();
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $this->join($otherAdmin, $otherTeam);
        [, , $otherTenantSubblok] = $this->makeFarm('Kebun Tenant Lain', 'B03', $otherTeam, $otherAdmin);
        $this->task($otherTenantSubblok, 'Tugas tenant lain');

        $this->actingAs($petugas)
            ->get('/petugas/tasks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('petugas/Aktivitas')
                ->missing('isFixture')
                ->where('filters.status', 'all')
                ->has('tasks', 3)
                ->where('tasks.0.id', $overdue->id)
                ->where('tasks.0.name', 'Pengairan')
                ->where('tasks.0.urgency', 'overdue')
                ->where('tasks.0.activity_category', 'maintenance')
                ->where('tasks.0.scheduled_date', $today->subDays(5)->toDateString())
                ->where('tasks.0.due_date', $today->subDays(4)->toDateString())
                ->where('tasks.0.linked_activities_count', 0)
                ->where('tasks.0.can.complete', false)
                ->where('tasks.0.can.record', false)
                ->where('tasks.0.subblok.id', $this->subblok->id)
                ->where('tasks.0.subblok.number', 1)
                ->where('tasks.0.subblok.label', 'A')
                ->where('tasks.0.subblok.hst', 12)
                ->where('tasks.0.subblok.farm.id', $this->farm->id)
                ->where('tasks.0.subblok.farm.name', 'Kebun Petugas')
                ->where('tasks.0.subblok.block.name', 'Blok B01')
                ->where('tasks.0.subblok.variety.name', 'Bisi 18')
                ->where('tasks.1.name', 'Pemupukan susulan pertama')
                ->where('tasks.1.urgency', 'today')
                ->where('tasks.2.name', 'Panen')
                ->where('tasks.2.urgency', 'completed'));
    }

    public function test_a_rescheduled_task_uses_the_admin_override_for_the_petugas_date_and_urgency(): void
    {
        $today = CarbonImmutable::today();
        $petugas = $this->petugas($this->farm);

        $this->task($this->subblok, 'Pemupukan susulan', [
            'status' => 'in_progress',
            'scheduled_date' => $today->subDays(3)->toDateString(),
            'due_date' => $today->addDays(2)->toDateString(),
            'rescheduled_date' => $today->toDateString(),
            'reschedule_reason' => 'Pekerjaan tertunda karena hujan.',
            'rescheduled_by' => $petugas->id,
            'rescheduled_at' => now(),
        ]);

        $this->actingAs($petugas)
            ->get('/petugas/tasks?status=today')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('petugas/Aktivitas')
                ->where('filters.status', 'today')
                ->has('tasks', 1)
                ->where('tasks.0.scheduled_date', $today->toDateString())
                ->where('tasks.0.urgency', 'today')
                ->where('tasks.0.can.complete', false));
    }

    public function test_task_history_shows_closed_subblocks_only_within_the_assigned_farm(): void
    {
        $petugas = $this->petugas($this->farm);
        $running = $this->task($this->subblok, 'Tugas berjalan');
        $harvested = app(FarmBlockService::class)->createBatch($this->block, $this->admin, $this->team);
        $harvested->forceFill(['variety_id' => $this->variety->id, 'planted_at' => today()->subDays(12),
            'planted_area_ha' => '1', 'status' => 'harvested'])->save();
        $completed = $this->task($harvested, 'Riwayat panen', ['status' => 'completed', 'completed_at' => now()]);
        $cancelled = app(FarmBlockService::class)->createBatch($this->block, $this->admin, $this->team);
        $cancelled->forceFill(['status' => 'cancelled'])->save();
        $pending = $this->task($cancelled, 'Riwayat belum dikerjakan');
        [, , $other] = $this->makeFarm('Lahan lain', 'B03', $this->team, $this->admin);
        $other->forceFill(['status' => 'cancelled'])->save();
        $this->task($other, 'Riwayat lahan lain');

        $this->actingAs($petugas)->get('/petugas/aktivitas')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.view', 'running')->has('tasks', 1)->where('tasks.0.id', $running->id)
                ->where('summary.completed', 0));
        $this->get('/petugas/aktivitas?view=history&farm_id='.$other->farm_id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.view', 'history')->has('tasks', 2)
                ->where('tasks', fn ($tasks) => collect($tasks)->pluck('id')->sort()->values()->all()
                    === collect([$completed->id, $pending->id])->sort()->values()->all())
                ->where('tasks', fn ($tasks) => collect($tasks)->every(fn ($task) => ! $task['can']['record'] && ! $task['can']['complete'])));
        $this->get('/petugas/aktivitas?view=history&status=active')
            ->assertInertia(fn (Assert $page) => $page->where('filters.status', 'all')->has('tasks', 2));
        $this->get('/petugas/tasks/'.$pending->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('task.status', 'pending')->where('task.subblok.status', 'cancelled')
                ->where('task.can.complete', false)->where('canRecord', false));
        $this->get('/petugas/penanaman')->assertInertia(fn (Assert $page) => $page
            ->has('cycles', 1)->where('cycles.0.id', $this->subblok->id)->where('blocks.0.cycles_count', 1));
        $this->get('/petugas/aktivitas?view=invalid')->assertSessionHasErrors('view');
    }

    public function test_petugas_without_an_assigned_farm_gets_an_empty_real_list(): void
    {
        $petugas = User::factory()->create(['role' => 'petugas']);
        $this->join($petugas, $this->team);
        $this->task($this->subblok, 'Pengairan');

        $this->actingAs($petugas)
            ->get('/petugas/tasks?status=not-a-status')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('petugas/Aktivitas')
                ->missing('isFixture')
                ->where('filters.status', 'all')
                ->has('tasks', 0));
    }

    public function test_guests_and_non_petugas_cannot_open_the_task_list(): void
    {
        $this->get('/petugas/tasks')->assertRedirect('/login');

        foreach (['admin', 'owner'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get('/petugas/tasks')->assertForbidden();
        }
    }

    public function test_task_listing_remains_authenticated_and_portal_protected(): void
    {
        $this->get('/petugas/tasks')->assertRedirect('/login');
        foreach (['owner', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/petugas/tasks')->assertForbidden();
        }
    }

    public function test_petugas_task_detail_exposes_hok_when_present(): void
    {
        $petugas = $this->petugas($this->farm);
        $withHok = $this->task($this->subblok, 'Pemupukan HOK', ['hok' => 7]);
        $withoutHok = $this->task($this->subblok, 'Pemupukan Opsional HOK', ['hok' => null]);

        $this->actingAs($petugas)
            ->get('/petugas/tasks/'.$withHok->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('petugas/TaskShow')
                ->where('task.id', $withHok->id)
                ->where('task.hok', 7));

        $this->actingAs($petugas)
            ->get('/petugas/tasks/'.$withoutHok->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('petugas/TaskShow')
                ->where('task.id', $withoutHok->id)
                ->where('task.hok', null));
    }
}
