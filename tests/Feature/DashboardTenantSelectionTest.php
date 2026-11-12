<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmRisk;
use App\Models\FinancialCategory;
use App\Models\FinancialProjection;
use App\Models\FinancialTransaction;
use App\Models\OperationalTarget;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use App\Services\FarmService;
use Database\Seeders\BlockMappingActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Integration coverage on the guarded, dedicated PostgreSQL test schema. */
class DashboardTenantSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
        $this->seed(BlockMappingActivityTypeSeeder::class);
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->team = $this->joinTeam('Tenant Dashboard A');
        $this->superadmin->switchTeam($this->team);
        $this->actingAs($this->superadmin);
    }

    public function test_all_dashboard_sections_follow_the_selected_tenant_and_farm_filter(): void
    {
        $other = $this->joinTeam('Tenant Dashboard B');
        $first = $this->farm($this->team, 'Kebun A', '10');
        $foreign = $this->farm($other, 'Kebun B', '20');
        $officer = $this->officer($this->team, $first, 'Petugas A');
        $foreignOfficer = $this->officer($other, $foreign, 'Petugas B');
        $this->records($first, $officer, 100, 1000);
        $this->records($foreign, $foreignOfficer, 900, 9000);
        FinancialTransaction::create(['category_id' => FinancialCategory::firstOrFail()->id, 'recorded_by' => $this->superadmin->id,
            'transaction_date' => today(), 'transaction_type' => 'expense', 'amount' => 20000]);

        $this->actingAs($this->superadmin)->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('currentTeam.id', $this->team->id)->has('dashboard.farmOptions', 1)->where('dashboard.farmOptions.0.id', $first->id)
            ->where('dashboard.metrics.0.value', 1)->where('dashboard.metrics.1.value', '10')
            ->where('dashboard.metrics.1.breakdown.blocks', 1)->where('dashboard.metrics.1.breakdown.subBlocks', 1)
            ->where('dashboard.metrics.2.value', 1)->where('dashboard.metrics.3.value', 1)
            ->has('dashboard.activities', 1)->where('dashboard.activities.0.farm', 'Kebun A')
            ->has('dashboard.officers', 1)->where('dashboard.officers.0.id', $officer->id)->where('dashboard.officers.0.activityCount', 1)
            ->has('dashboard.tasks', 1)->where('dashboard.tasks.0.farm', 'Kebun A')
            ->has('dashboard.landMap.farms', 1)->where('dashboard.landMap.farms.0.id', $first->id)
            ->where('dashboard.trend.total', 1)->where('dashboard.activity.total', 1)->has('dashboard.activity.farms', 1)
            ->where('dashboard.cycles.running', 1)->where('dashboard.cycles.stages.0.count', 0)->where('dashboard.cycles.withoutTimeline', 1)
            ->where('dashboard.attention.0.count', 1)->where('dashboard.attention.4.count', 1)
            ->where('dashboard.financial.opexThisMonth', 'Rp100')->where('dashboard.financial.projectionCount', 1)
            ->where('dashboard.financial.projectedIncome', 'Rp1.000')->where('dashboard.financial.projectedExpense', 'Rp10'));
        $this->get('/admin/dashboard?farm_id='.$foreign->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.filters.farm_id', null)->has('dashboard.activities', 1)->where('dashboard.activities.0.farm', 'Kebun A')
            ->where('dashboard.financial.opexThisMonth', 'Rp100'));
        $this->post(route('teams.switch', $other))->assertRedirect();
        $this->get('/admin/dashboard?farm_id='.$foreign->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('currentTeam.id', $other->id)->where('dashboard.filters.farm_id', $foreign->id)
            ->has('dashboard.farmOptions', 1)->where('dashboard.farmOptions.0.id', $foreign->id)
            ->has('dashboard.activities', 1)->where('dashboard.activities.0.farm', 'Kebun B')
            ->has('dashboard.officers', 1)->where('dashboard.officers.0.id', $foreignOfficer->id)
            ->where('dashboard.financial.opexThisMonth', 'Rp900')->where('dashboard.financial.projectedIncome', 'Rp9.000'));
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_empty_tenant_stays_empty_when_another_tenant_records_new_logbook_activities(): void
    {
        $farm = $this->farm($this->team, 'Kebun Logbook', '5');
        $worker = $this->officer($this->team, $farm, 'Petugas Logbook');
        $this->records($farm, $worker, 100, 1000);
        $empty = $this->joinTeam('Tenant Baru Tanpa Kebun');
        $this->actingAs($this->superadmin)->post(route('teams.switch', $empty))->assertRedirect();
        $this->assertSame($empty->id, $this->superadmin->fresh()->current_team_id);
        $this->assertEmptyDashboard($empty, '/admin/dashboard?farm_id='.$farm->id);
        $this->createLog($farm, $worker, today()->subDay()->toDateString());
        $this->assertSame(2, FarmActivity::where('farm_id', $farm->id)->count());
        $this->actingAs($this->superadmin);
        $this->assertEmptyDashboard($empty, '/admin/dashboard');
        $this->post(route('teams.switch', $this->team))->assertRedirect();
        $this->get('/admin/dashboard')->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.activities', 2)->where('dashboard.officers.0.activityCount', 2)->where('dashboard.trend.total', 2));
    }

    public function test_current_team_is_respected_even_with_one_tenant_and_global_access_is_preserved(): void
    {
        $farm = $this->farm($this->team, 'Kebun Global', '5');
        $personal = $this->joinTeam('Tim Personal Terpilih', true);
        $this->post(route('teams.switch', $personal))->assertRedirect();
        $this->assertEmptyDashboard($personal, '/admin/dashboard');
        $this->assertTrue(Gate::forUser($this->superadmin)->allows('access.global'));
        $this->assertTrue(Gate::forUser($this->superadmin)->allows('view', $farm));
        $this->assertTrue(Gate::forUser($this->superadmin)->allows('update', $farm));
        $this->assertTrue(Farm::visibleTo($this->superadmin)->whereKey($farm->id)->exists());
        $this->get('/admin/farm-management/'.$farm->id)->assertOk();
        $this->superadmin->forceFill(['current_team_id' => null])->save();
        $this->superadmin->unsetRelation('currentTeam');
        $this->get('/admin/dashboard')->assertForbidden();
    }

    private function assertEmptyDashboard(Team $team, string $url): void
    {
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('currentTeam.id', $team->id)->where('dashboard.filters.farm_id', null)
            ->has('dashboard.farmOptions', 0)->has('dashboard.activities', 0)->has('dashboard.officers', 0)->has('dashboard.tasks', 0)
            ->has('dashboard.landMap.farms', 0)->where('dashboard.landMap.hasCoordinates', false)
            ->where('dashboard.metrics.0.value', 0)->where('dashboard.metrics.1.value', '0')
            ->where('dashboard.metrics.2.value', 0)->where('dashboard.metrics.3.value', 0)
            ->where('dashboard.cycles.running', 0)->where('dashboard.cycles.stages.0.count', 0)->where('dashboard.cycles.withoutTimeline', 0)
            ->where('dashboard.trend.total', 0)->where('dashboard.activity.total', 0)->has('dashboard.activity.farms', 0)->has('dashboard.commodities', 0)
            ->where('dashboard.attention.0.count', 0)->where('dashboard.attention.2.count', 0)->where('dashboard.attention.4.count', 0)
            ->where('dashboard.financial.opexThisMonth', 'Rp0')->where('dashboard.financial.projectionCount', 0)
            ->where('dashboard.financial.projectedIncome', 'Rp0')->where('dashboard.financial.projectedExpense', 'Rp0')->where('dashboard.financial.projectedNet', 'Rp0'));
    }

    private function joinTeam(string $name, bool $personal = false): Team
    {
        $team = Team::factory()->create(['name' => $name, 'is_personal' => $personal]);
        $team->members()->attach($this->superadmin, ['role' => TeamRole::Member->value]);

        return $team;
    }

    private function farm(Team $team, string $name, string $area): Farm
    {
        return app(FarmService::class)->create(['name' => $name, 'total_area_ha' => $area, 'latitude' => '-6.59', 'longitude' => '106.95'], $this->superadmin, $team);
    }

    private function officer(Team $team, Farm $farm, string $name): User
    {
        $user = User::factory()->create(['role' => 'petugas', 'name' => $name]);
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $user->switchTeam($team);
        $user->forceFill(['assigned_farm_id' => $farm->id])->save();

        return $user;
    }

    private function createLog(Farm $farm, User $worker, string $date): void
    {
        $this->actingAs($worker)->postJson('/kebun/'.$farm->id.'/daily-logs', [
            'work_date' => $date, 'start_time' => '08:00', 'end_time' => '09:00', 'worker_count' => 1,
            'activities' => [['type' => 'block_mapping', 'obstacle_status' => 'none', 'notes' => 'Pemetaan '.$farm->name,
                'block_mapping' => ['block_name' => 'Area pemetaan', 'length_m' => 10, 'width_m' => 10]]],
        ])->assertCreated();
    }

    private function records(Farm $farm, User $worker, int $expense, int $income): void
    {
        $this->createLog($farm, $worker, today()->toDateString());
        $block = app(FarmBlockService::class)->create($farm, $this->superadmin, $farm->team, ['code' => 'B01', 'name' => 'Blok Utara', 'area_ha' => '4']);
        $cycle = app(FarmBlockService::class)->createBatch($block, $this->superadmin, $farm->team, ['reserved_area_ha' => '1']);
        FarmRisk::create(['planting_cycle_id' => $cycle->id, 'risk_description' => 'Drainase perlu diperiksa', 'status' => 'open', 'detected_at' => now()]);
        $category = FinancialCategory::firstOrCreate(['name' => 'Biaya lapangan', 'transaction_type' => 'expense']);
        FinancialTransaction::create(['farm_id' => $farm->id, 'category_id' => $category->id, 'recorded_by' => $worker->id,
            'transaction_date' => today(), 'transaction_type' => 'expense', 'amount' => $expense]);
        FinancialProjection::create(['farm_id' => $farm->id, 'period_start' => today()->startOfMonth(), 'period_end' => today()->endOfMonth(),
            'projected_income' => $income, 'projected_expense' => 10, 'calculated_at' => now()]);
        OperationalTarget::create(['farm_id' => $farm->id, 'task_name' => 'Periksa '.$farm->name, 'target_unit' => 'ha', 'target_value' => '1',
            'created_by' => $worker->id, 'target_date' => today()]);
    }
}
