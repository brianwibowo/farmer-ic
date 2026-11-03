<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\FarmRisk;
use App\Models\FinancialProjection;
use App\Models\IrrigationDetail;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Integration tests on the guarded, dedicated PostgreSQL test schema. */
class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(12, 0));
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->team = Team::factory()->create(['is_personal' => false]);
        $this->team->members()->attach($this->admin, ['role' => TeamRole::Member->value]);
        $this->admin->switchTeam($this->team);
        $this->actingAs($this->admin);
    }

    private function farm(string $name, ?Team $team = null): Farm
    {
        $farm = new Farm(['name' => $name, 'total_area_ha' => 10, 'latitude' => -6.59, 'longitude' => 106.95]);
        $farm->forceFill(['team_id' => ($team ?? $this->team)->id, 'created_by' => $this->admin->id])->save();

        return $farm;
    }

    private function cycle(Farm $farm): PlantingCycle
    {
        $commodity = Commodity::firstOrCreate(['name' => 'Jagung']);
        $variety = CropVariety::firstOrCreate(['commodity_id' => $commodity->id, 'name' => 'Bisi 18']);

        return PlantingCycle::create(['farm_id' => $farm->id, 'variety_id' => $variety->id, 'planted_at' => '2026-09-01', 'estimated_harvest_at' => '2026-11-10', 'planted_area_ha' => 1, 'status' => 'active']);
    }

    private function irrigation(Farm $farm, string $date): void
    {
        $type = ActivityType::firstOrCreate(['code' => 'irrigation'], ['name' => 'Penyiraman']);
        $activity = FarmActivity::create(['farm_id' => $farm->id, 'activity_type_id' => $type->id, 'performed_by' => $this->admin->id, 'activity_date' => $date, 'description' => 'Penyiraman bedeng pagi', 'status' => 'completed']);
        IrrigationDetail::create(['activity_id' => $activity->id, 'water_source' => 'Sumur kebun', 'duration_hours' => 1]);
    }

    public function test_global_and_selected_farm_summaries_do_not_leak_other_farms_or_tenants(): void
    {
        $first = $this->farm('Kebun Utara');
        $second = $this->farm('Kebun Selatan');
        $hidden = $this->farm('Kebun Privat', Team::factory()->create(['is_personal' => false]));
        foreach ([$first, $second, $hidden] as $index => $farm) {
            $cycle = $this->cycle($farm);
            FarmRisk::create(['planting_cycle_id' => $cycle->id, 'risk_description' => 'Drainase perlu diperiksa', 'status' => 'open', 'detected_at' => now()]);
            $this->irrigation($farm, '2026-11-02 08:00:00+07');
            FinancialProjection::create(['farm_id' => $farm->id, 'period_start' => '2026-11-01', 'period_end' => '2026-11-30', 'projected_income' => ($index + 1) * 100, 'projected_expense' => 10, 'calculated_at' => now()]);
        }
        $this->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.farmOptions', 2)->where('dashboard.metrics.0.value', 2)
            ->where('dashboard.metrics.3.value', 2)->where('dashboard.trend.total', 2)
            ->has('dashboard.activities', 2)->has('dashboard.landMap.farms', 2)
            ->where('dashboard.cycles.withoutTimeline', 2)->where('dashboard.financial.projectedIncome', 'Rp300'));
        $this->get('/admin/dashboard?farm_id='.$first->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.farmOptions', 2)->where('dashboard.filters.farm_id', $first->id)
            ->where('dashboard.metrics.0.value', 1)->where('dashboard.metrics.3.value', 1)
            ->where('dashboard.trend.total', 1)->has('dashboard.activities', 1)
            ->has('dashboard.landMap.farms', 1)->where('dashboard.landMap.farms.0.id', $first->id)
            ->where('dashboard.cycles.running', 1)->where('dashboard.commodities.0.cycleCount', 1)
            ->where('dashboard.financial.projectedIncome', 'Rp100'));
        $this->get('/admin/dashboard?farm_id='.$hidden->id)->assertForbidden();
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_period_changes_only_activity_trend_and_excludes_future_or_old_reports(): void
    {
        $farm = $this->farm('Kebun Periode');
        foreach (['2026-11-02', '2026-10-15', '2026-09-01', '2026-11-03'] as $date) {
            $this->irrigation($farm, $date.' 08:00:00+07');
        }
        $this->get('/admin/dashboard?period=7')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.trend.total', 1)->has('dashboard.trend.days', 7));
        $this->get('/admin/dashboard?period=30')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.trend.total', 2)->has('dashboard.trend.days', 6)
            ->where('dashboard.metrics.0.value', 1)->where('dashboard.filters.period', '30'));
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_preparation_batch_uses_persisted_stage_and_clears_block_land_clearing(): void
    {
        $farm = $this->farm('Kebun Timeline');
        Gate::forUser($this->admin)->authorize('create', [FarmBlock::class, $farm]);
        $block = app(FarmBlockService::class)->create($farm, $this->admin, $this->team, ['code' => 'B01', 'name' => 'Blok Utara', 'area_ha' => 5]);
        $this->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->where('dashboard.attention.2.count', 1));
        Gate::forUser($this->admin)->authorize('create', [PlantingCycle::class, $block]);
        app(FarmBlockService::class)->createBatch($block, $this->admin, $this->team, ['reserved_area_ha' => 1]);
        $this->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.cycles.running', 1)->where('dashboard.cycles.planned', 1)
            ->where('dashboard.cycles.stages.0.count', 1)->where('dashboard.cycles.withoutTimeline', 0)
            ->where('dashboard.attention.2.count', 0)->where('dashboard.metrics.1.breakdown.blocks', 1));
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_invalid_filters_are_validated_and_empty_summary_is_valid(): void
    {
        $this->get('/admin/dashboard?farm_id=invalid&period=99')->assertSessionHasErrors(['farm_id', 'period']);
        $this->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.cycles.running', 0)->where('dashboard.trend.total', 0)
            ->has('dashboard.landMap.farms', 0)->has('dashboard.activities', 0));
    }
}
