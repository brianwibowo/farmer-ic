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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    public function test_shared_navigation_reuses_membership_queries_for_activity_and_reports(): void
    {
        $petugas = $this->petugas($this->farm);
        $measurements = [];
        Log::shouldReceive('info')->andReturnUsing(function ($event, $metrics) use (&$measurements): void {
            $measurements[$event] = $metrics;
        });

        foreach (['/petugas/aktivitas', '/petugas/laporan'] as $url) {
            $this->actingAs($petugas)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('navigation.petugas', true)->where('navigation.admin', false)
                ->where('navigation.finance.transactions', false));
        }

        foreach (['petugas_activity_timing', 'petugas_report_timing'] as $event) {
            $this->assertArrayHasKey($event, $measurements);
            $this->assertLessThanOrEqual(5, $measurements[$event]['shared_props_queries']);
        }
    }

    public function test_search_matches_across_task_block_and_subblock_without_exposing_other_farms(): void
    {
        $petugas = $this->petugas($this->farm);
        $this->task($this->subblok, 'Pemupukan');
        [, , $other] = $this->makeFarm('Kebun lain', 'B02', $this->team, $this->admin);
        $this->task($other, 'Pemupukan');

        $this->actingAs($petugas)->get('/petugas/aktivitas?'.http_build_query([
            'q' => 'pemupukan blok b01 a', 'farm_id' => $other->farm_id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tasks.total', 1)->where('tasks.data.0.subblok.farm.id', $this->farm->id));
        $this->get('/petugas/aktivitas?'.http_build_query(['q' => 'Pemupukan Blok B02']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('tasks.total', 0));
        $this->get('/petugas/aktivitas?'.http_build_query(['q' => '%']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('tasks.total', 0));
    }

    public function test_task_added_after_options_query_still_has_scoped_page_data(): void
    {
        $petugas = $this->petugas($this->farm);
        $newCycle = app(FarmBlockService::class)->createBatch($this->block, $this->admin, $this->team, ['reserved_area_ha' => '1']);
        $newCycle->forceFill(['variety_id' => $this->variety->id, 'status' => 'active', 'planted_at' => today(), 'planted_area_ha' => '1'])->save();
        $inserted = false;
        DB::listen(function ($query) use (&$inserted, $newCycle): void {
            if (! $inserted && str_contains($query->sql, 'select * from "planting_cycles"')
                && str_contains($query->sql, 'exists (select * from "planting_cycle_tasks"')) {
                $inserted = true;
                $this->task($newCycle, 'Tugas baru');
            }
        });

        $response = $this->actingAs($petugas)->get('/petugas/aktivitas?status=all');
        $this->assertTrue($inserted);
        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tasks.total', 1)
                ->where('tasks.data.0.name', 'Tugas baru')
                ->where('tasks.data.0.subblok.id', $newCycle->id)
                ->where('tasks.data.0.subblok.farm.id', $this->farm->id));
    }

    public function test_history_query_count_does_not_grow_per_task(): void
    {
        $petugas = $this->petugas($this->farm);
        $this->subblok->forceFill(['status' => 'harvested'])->save();
        $this->task($this->subblok, 'Tugas pertama');
        $queryCount = 0;
        $active = false;
        \Illuminate\Support\Facades\DB::listen(function () use (&$queryCount, &$active): void {
            if ($active) {
                $queryCount++;
            }
        });
        $active = true;
        $this->actingAs($petugas)->get('/petugas/aktivitas?view=history')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('tasks.data', 1));
        $active = false;
        $baseline = $queryCount;
        for ($i = 0; $i < 10; $i++) {
            $this->task($this->subblok, 'Tugas tambahan '.$i);
        }
        $queryCount = 0;
        $active = true;
        $this->get('/petugas/aktivitas?view=history')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('tasks.data', 10)->where('tasks.total', 11));
        $active = false;
        $this->assertLessThanOrEqual($baseline + 5, $queryCount);
    }

    public function test_history_filters_and_paginates_only_assigned_farm_tasks(): void
    {
        $petugas = $this->petugas($this->farm);
        $this->subblok->forceFill(['status' => 'harvested'])->save();
        $this->block->forceFill(['name' => 'Blok Utara'])->save();
        for ($i = 0; $i < 12; $i++) {
            $this->task($this->subblok, sprintf('Pemupukan %02d', $i));
        }
        [, , $other] = $this->makeFarm('Lahan lain', 'B04', $this->team, $this->admin);
        $other->forceFill(['status' => 'cancelled'])->save();
        $this->task($other, 'Pemupukan rahasia');
        $url = '/petugas/aktivitas?view=history&q=Pemupukan&block_id='.$this->block->id.'&subblok_id='.$this->subblok->id.'&category=maintenance';
        $this->actingAs($petugas)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tasks.total', 12)->where('tasks.current_page', 1)->has('tasks.data', 10)
            ->where('tasks.data.0.name', 'Pemupukan 00'));
        $this->get($url.'&page=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tasks.total', 12)->where('tasks.current_page', 2)->has('tasks.data', 2)
            ->where('tasks.data.0.name', 'Pemupukan 10'));
        $this->get($url.'&per_page=20')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tasks.total', 12)->has('tasks.data', 12));
        $this->get('/petugas/aktivitas?view=history&q=0')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tasks.total', 11));
        $this->get('/petugas/aktivitas?per_page=999')->assertSessionHasErrors('per_page');
    }

    public function test_activity_timing_records_scoped_metrics_without_query_details(): void
    {
        $petugas = $this->petugas($this->farm);
        $this->task($this->subblok, 'Pemupukan');
        Log::spy();

        $response = $this->withoutExceptionHandling()->actingAs($petugas)->get('/petugas/aktivitas?view=running');
        $response->assertOk();
        $traceId = $response->headers->get('X-Activity-Trace-Id');
        $this->assertNotEmpty($traceId);
        Log::shouldHaveReceived('info')->once()->with('petugas_activity_timing', \Mockery::on(
            fn ($metrics) => $metrics['trace_id'] === $traceId
                && $metrics['status'] === 200
                && $metrics['task_count'] === 1
                && $metrics['page'] === 1
                && $metrics['per_page'] === 10
                && $metrics['query_count'] > 0
                && $metrics['query_ms'] >= 0
                && $metrics['listing_ms'] >= 0
                && $metrics['web_setup_ms'] >= 0
                && $metrics['web_setup_queries'] >= 0
                && $metrics['shared_props_ms'] >= 0
                && $metrics['shared_props_queries'] > 0
                && $metrics['web_pipeline_ms'] >= 0
                && $metrics['inertia_ms'] >= 0
                && $metrics['route_ms'] >= 0
                && $metrics['controller_ms'] >= 0
                && $metrics['inertia_visit'] === false
                && $metrics['route_queries'] >= 0
                && $metrics['permissions_ms'] >= 0
                && $metrics['laravel_ms'] >= $metrics['listing_ms']
                && ! array_key_exists('sql', $metrics)
                && ! array_key_exists('bindings', $metrics),
        ));

        $this->get('/petugas/tasks')->assertOk()->assertHeaderMissing('X-Activity-Trace-Id');
        config(['activity_timing.enabled' => false]);
        $this->get('/petugas/aktivitas')->assertOk()->assertHeaderMissing('X-Activity-Trace-Id');
        Log::shouldHaveReceived('info')->once()->with('petugas_activity_timing', \Mockery::any());
    }

    public function test_task_summary_uses_one_scoped_aggregate(): void
    {
        $petugas = $this->petugas($this->farm);
        $this->task($this->subblok, 'Terlambat', ['offset_days' => 0, 'tolerance_days' => 1]);
        $this->task($this->subblok, 'Mendatang', ['offset_days' => 30]);
        $this->task($this->subblok, 'Selesai', ['status' => 'completed', 'completed_at' => now()]);
        $summaryQueries = 0;
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$summaryQueries): void {
            if (str_contains($query->sql, 'task_statuses')) {
                $summaryQueries++;
            }
        });
        $this->actingAs($petugas)->get('/petugas/aktivitas?status=upcoming')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.overdue', 1)->where('summary.upcoming', 1)
            ->where('summary.completed', 1)->where('summary.today', 0)->where('tasks.total', 1));
        $this->assertSame(1, $summaryQueries);
    }

    public function test_activity_timing_records_requested_pagination(): void
    {
        $petugas = $this->petugas($this->farm);
        Log::spy();

        $response = $this->actingAs($petugas)->get('/petugas/aktivitas?view=history&page=2&per_page=20');
        $response->assertOk();
        $traceId = $response->headers->get('X-Activity-Trace-Id');
        Log::shouldHaveReceived('info')->once()->with('petugas_activity_timing', \Mockery::on(
            fn ($metrics) => $metrics['trace_id'] === $traceId
                && $metrics['page'] === 2
                && $metrics['per_page'] === 20
                && $metrics['task_count'] === 0,
        ));
    }

    public function test_activity_timing_leaves_pagination_empty_when_validation_fails(): void
    {
        $petugas = $this->petugas($this->farm);
        Log::spy();

        $response = $this->actingAs($petugas)->get('/petugas/aktivitas?page=2&per_page=999');
        $response->assertSessionHasErrors('per_page');
        $traceId = $response->headers->get('X-Activity-Trace-Id');
        Log::shouldHaveReceived('info')->once()->with('petugas_activity_timing', \Mockery::on(
            fn ($metrics) => $metrics['trace_id'] === $traceId
                && $metrics['page'] === null
                && $metrics['per_page'] === null,
        ));
    }

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
                ->has('tasks.data', 3)
                ->where('tasks.data.0.id', $overdue->id)
                ->where('tasks.data.0.name', 'Pengairan')
                ->where('tasks.data.0.urgency', 'overdue')
                ->where('tasks.data.0.activity_category', 'maintenance')
                ->where('tasks.data.0.scheduled_date', $today->subDays(5)->toDateString())
                ->where('tasks.data.0.due_date', $today->subDays(4)->toDateString())
                ->where('tasks.data.0.linked_activities_count', 0)
                ->where('tasks.data.0.can.complete', false)
                ->where('tasks.data.0.can.record', false)
                ->where('tasks.data.0.subblok.id', $this->subblok->id)
                ->where('tasks.data.0.subblok.number', 1)
                ->where('tasks.data.0.subblok.label', 'A')
                ->where('tasks.data.0.subblok.hst', 12)
                ->where('tasks.data.0.subblok.farm.id', $this->farm->id)
                ->where('tasks.data.0.subblok.farm.name', 'Kebun Petugas')
                ->where('tasks.data.0.subblok.block.name', 'Blok B01')
                ->where('tasks.data.0.subblok.variety.name', 'Bisi 18')
                ->where('tasks.data.1.name', 'Pemupukan susulan pertama')
                ->where('tasks.data.1.urgency', 'today')
                ->where('tasks.data.2.name', 'Panen')
                ->where('tasks.data.2.urgency', 'completed'));
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
                ->has('tasks.data', 1)
                ->where('tasks.data.0.scheduled_date', $today->toDateString())
                ->where('tasks.data.0.urgency', 'today')
                ->where('tasks.data.0.can.complete', false));
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

        $this->actingAs($petugas)->get('/petugas/aktivitas?status=all')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.view', 'running')->has('tasks.data', 1)->where('tasks.data.0.id', $running->id)
                ->where('summary.completed', 0));
        $this->get('/petugas/aktivitas?view=history&farm_id='.$other->farm_id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.view', 'history')->has('tasks.data', 2)
                ->where('tasks.data', fn ($tasks) => collect($tasks)->pluck('id')->sort()->values()->all()
                    === collect([$completed->id, $pending->id])->sort()->values()->all())
                ->where('tasks.data', fn ($tasks) => collect($tasks)->every(fn ($task) => ! $task['can']['record'] && ! $task['can']['complete'])));
        $this->get('/petugas/aktivitas?view=history&status=active')
            ->assertInertia(fn (Assert $page) => $page->where('filters.status', 'all')->has('tasks.data', 2));
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
            ->get('/petugas/tasks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('petugas/Aktivitas')
                ->missing('isFixture')
                ->where('filters.status', 'all')
                ->where('tasks', null));
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
