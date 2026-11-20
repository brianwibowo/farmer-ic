<?php

namespace Tests\Feature\Owner;

use App\Enums\TeamRole;
use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\HarvestDetail;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Services\Owner\ExecutiveSummaryService;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** HTTP/service integration against the guarded dedicated PostgreSQL schema. */
class ExecutiveSummaryDataTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Team $team;

    public function test_projection_uses_accumulated_planted_area_and_retains_harvested_batches(): void
    {
        $farm = $this->farm('Lahan Proyeksi Aktual', 10);
        $batch = $this->cycle($farm, 'Jagung', 1, null);
        $this->get('/ringkasan-eksekutif?year=2026')->assertInertia(fn (Assert $page) => $page
            ->where('summary.rows.0.projectedTon', 40));
        $batch->update(['planted_area_ha' => 3]);
        $this->get('/ringkasan-eksekutif?year=2026')->assertInertia(fn (Assert $page) => $page
            ->where('summary.rows.0.projectedTon', 120));
        $batch->update(['status' => 'harvested']);
        $this->get('/ringkasan-eksekutif?year=2026')->assertInertia(fn (Assert $page) => $page
            ->where('summary.rows.0.projectedTon', 120));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(12, 0));
        $this->owner = User::factory()->create(['role' => 'owner']);
        $this->team = Team::factory()->create(['is_personal' => false]);
        $this->team->members()->attach($this->owner, ['role' => TeamRole::Member->value]);
        $this->owner->switchTeam($this->team);
        $this->actingAs($this->owner);
    }

    public function test_persisted_multi_commodity_production_and_current_land_positions(): void
    {
        $farm = $this->farm('Lahan Bogor', 10);
        $corn = $this->cycle($farm, 'Jagung', 2, 12);
        $grass = $this->cycle($farm, 'Rumput', 3, 20);
        $this->harvest($corn, 5);
        $this->harvest($grass, 7);
        $this->farm('Lahan Belum Ditanam', 4);
        $this->get('/ringkasan-eksekutif')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('summary.farms', 2)->has('summary.rows', 3)
            ->where('summary.rows.1.farm.areaHa', 10)
            ->where('summary.rows.1.plantedHa', 2)
            ->where('summary.rows.1.harvestedTon', 5)
            ->where('summary.rows.1.projectedTon', 80)
            ->where('summary.rows.1.stockTon', null)
            ->where('summary.rows.2.plantedHa', 3)
            ->where('summary.rows.2.harvestedTon', 7)
            ->where('summary.rows.2.projectedTon', 300)
            ->where('summary.rows.1.farm.detail_url', '/kebun/'.$farm->id));
    }

    public function test_refresh_reads_new_database_records_and_filters_use_persisted_ids(): void
    {
        $farm = $this->farm('Lahan Terpilih', 8, 'Mitra Bogor');
        $cycle = $this->cycle($farm, 'Jagung', 2, 12);
        $this->cycle($this->farm('Lahan Lain', 5), 'Rumput', 1, 9);
        $this->get('/ringkasan-eksekutif?farm='.$farm->id)->assertInertia(fn (Assert $page) => $page
            ->has('summary.rows', 1)->where('summary.rows.0.harvestedTon', 0));
        $this->harvest($cycle, 6);
        $this->get('/ringkasan-eksekutif?year=2026&partner=Mitra%20Bogor&region=Jawa%20Barat&commodity='.$cycle->variety->commodity_id)
            ->assertInertia(fn (Assert $page) => $page
                ->has('summary.rows', 1)->where('summary.filters.year', 2026)->where('summary.rows.0.harvestedTon', 6));
        $this->get('/ringkasan-eksekutif?partner=Tidak%20ada')->assertInertia(fn (Assert $page) => $page->has('summary.rows', 0));
    }

    public function test_year_uses_jakarta_harvest_dates_and_projection_dates_and_never_multiplies_land(): void
    {
        $farm = $this->farm('Lahan Lintas Tahun', 10);
        $first = $this->cycle($farm, 'Jagung', 2, 12);
        $this->harvest($first, 5, '2025-12-31 18:30:00+00'); // 1 Jan Jakarta
        $second = $this->cycle($farm, 'Jagung', 3, 20);
        $second->update(['estimated_harvest_at' => '2027-01-01', 'status' => 'planned']);
        $this->get('/ringkasan-eksekutif?year=2026')->assertInertia(fn (Assert $page) => $page
            ->has('summary.rows', 1)->where('summary.rows.0.harvestedTon', 5)
            ->where('summary.rows.0.projectedTon', 80)->where('summary.rows.0.plantedHa', 2)
            ->where('summary.rows.0.farm.areaHa', 10));
        $this->get('/ringkasan-eksekutif?year=2027')->assertInertia(fn (Assert $page) => $page
            ->where('summary.rows.0.harvestedTon', 0)->where('summary.rows.0.projectedTon', 0)
            ->where('summary.rows.0.plantedHa', 2));
    }

    public function test_quarters_use_harvest_and_projection_dates_with_jakarta_boundaries(): void
    {
        $farm = $this->farm('Lahan Kuartal', 10);
        $q1 = $this->cycle($farm, 'Jagung', 2, 12);
        $q1->update(['planted_at' => '2026-01-01', 'estimated_harvest_at' => '2026-03-31']);
        $q2 = $this->cycle($farm, 'Jagung', 3, 20);
        $q2->update(['planted_at' => '2026-01-01', 'estimated_harvest_at' => '2026-04-01']);
        $q3 = $this->cycle($farm, 'Jagung', 1, 8);
        $q3->update(['planted_at' => '2026-01-01', 'estimated_harvest_at' => '2026-07-01']);
        $this->harvest($q1, 5, '2026-03-31 16:59:59+00');
        $this->harvest($q2, 7, '2026-03-31 17:00:00+00');
        $this->harvest($q3, 9, '2026-07-01 00:00:00+07');
        foreach (['Q1' => [5, 80], 'Q2' => [7, 120], 'Q3' => [9, 40], 'Q4' => [0, 0], 'all' => [21, 240]] as $quarter => [$actual, $projection]) {
            $this->get('/ringkasan-eksekutif?year=2026&quarter='.$quarter)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('summary.filters.quarter', $quarter)
                ->where('summary.rows.0.harvestedTon', $actual)
                ->where('summary.rows.0.projectedTon', $projection)
                ->where('summary.rows.0.plantedHa', 6)
                ->where('summary.rows.0.farm.areaHa', 10)
                ->missing('summary.cycles'));
        }
    }

    public function test_calendar_year_is_added_automatically_at_jakarta_new_year(): void
    {
        $this->cycle($this->farm('Lahan Tahun Sebelumnya', 10), 'Jagung', 2, 12);
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-12-31 17:00:00+00'));
        $this->get('/ringkasan-eksekutif')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.defaultYear', 2027)
            ->where('summary.filters.year', 2027)
            ->where('summary.filters.quarter', 'all')
            ->where('summary.years', [2027, 2026]));
    }

    public function test_legacy_cycle_query_no_longer_limits_production(): void
    {
        $farm = $this->farm('Lahan Semua Batch', 10);
        $a = $this->cycle($farm, 'Jagung', 2, 12);
        $b = $this->cycle($farm, 'Jagung', 3, 20);
        $this->harvest($a, 5);
        $this->harvest($b, 7);
        $this->get('/ringkasan-eksekutif?cycle='.$a->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.filters.quarter', 'all')
            ->where('summary.rows.0.harvestedTon', 12)
            ->missing('summary.filters.cycle')->missing('summary.cycles'));
    }

    public function test_cross_tenant_records_never_enter_rows_options_or_totals(): void
    {
        $visible = $this->farm('Lahan Terlihat', 3);
        $this->cycle($visible, 'Jagung', 1, 10);
        $this->plannedBatch($this->block($visible, 'Blok Terlihat', 'Jagung', 3), 1);
        $otherTeam = Team::factory()->create(['is_personal' => false]);
        $foreign = $this->farm('Lahan Rahasia', 999, 'Mitra Rahasia', $otherTeam);
        $foreignCycle = $this->cycle($foreign, 'Sorgum', 99, 999);
        $this->plannedBatch($this->block($foreign, 'Blok Rahasia', 'Sorgum', 999), 1);
        $this->harvest($foreignCycle, 999);
        $this->get('/ringkasan-eksekutif?team_id='.$otherTeam->id)->assertInertia(fn (Assert $page) => $page
            ->has('summary.farms', 1)->has('summary.rows', 1)->has('summary.commodities', 1)
            ->has('summary.partners', 1)->where('summary.partners.0.name', 'Belum tercatat')
            ->missing('summary.cycles')->where('summary.rows.0.farm.id', $visible->id));
        $this->get('/ringkasan-eksekutif?farm='.$foreign->id)->assertForbidden();
        $this->team->members()->detach($this->owner);
        $this->get('/ringkasan-eksekutif')->assertForbidden();
    }

    public function test_missing_estimates_and_hidden_relationships_never_fabricate_values_or_labels(): void
    {
        $cycle = $this->cycle($this->farm('Lahan Data Parsial', 10), 'Jagung', 2, null);
        $this->harvest($cycle, 6);
        $this->get('/ringkasan-eksekutif')->assertInertia(fn (Assert $page) => $page
            ->where('summary.rows.0.projectedTon', 80)->where('summary.rows.0.projectionMissing', 0));
        $roles = app(RolePermissions::class)->roles();
        unset($roles['owner']['crop_varieties.view']);
        config(['access.roles' => $roles]);
        $this->get('/ringkasan-eksekutif')->assertInertia(fn (Assert $page) => $page
            ->where('summary.rows.0.commodity.name', 'Belum teridentifikasi')->has('summary.commodities', 0)
            ->where('summary.rows.0.plantedHa', 2)->where('summary.rows.0.harvestedTon', 6));
        unset($roles['owner']['farms.view']);
        config(['access.roles' => $roles]);
        $this->get('/ringkasan-eksekutif')->assertForbidden();
    }

    public function test_hidden_commodity_keeps_production_and_fallback_without_exposing_its_label(): void
    {
        $cycle = $this->cycle($this->farm('Lahan Komoditas Terbatas', 10), 'Jagung', 2, null);
        $this->harvest($cycle, 6);
        $roles = app(RolePermissions::class)->roles();
        unset($roles['owner']['commodities.view']);
        config(['access.roles' => $roles]);

        $this->get('/ringkasan-eksekutif')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('summary.rows', 1)->has('summary.commodities', 0)
            ->where('summary.rows.0.commodity.id', '')
            ->where('summary.rows.0.commodity.name', 'Belum teridentifikasi')
            ->where('summary.rows.0.plantedHa', 2)->where('summary.rows.0.harvestedTon', 6)
            ->where('summary.rows.0.projectedTon', null));
    }

    public function test_cancelled_and_future_planting_do_not_count_as_current_planted_area(): void
    {
        $farm = $this->farm('Lahan Status', 10);
        $this->cycle($farm, 'Jagung', 2, 10)->update(['status' => 'cancelled']);
        $this->cycle($farm, 'Jagung', 3, 20)->update(['planted_at' => '2026-12-01']);
        $this->get('/ringkasan-eksekutif')->assertInertia(fn (Assert $page) => $page
            ->where('summary.rows.0.plantedHa', 0)->where('summary.rows.0.projectedTon', null));
    }

    public function test_bad_filters_have_validation_errors(): void
    {
        $this->get('/ringkasan-eksekutif?year=invalid&quarter=Q5&farm=invalid&commodity=invalid')
            ->assertSessionHasErrors(['year', 'quarter', 'farm', 'commodity']);
        $this->getJson('/ringkasan-eksekutif?farm[]=00000000-0000-4000-8000-000000000000')
            ->assertUnprocessable()->assertJsonValidationErrors('farm');
        $this->get('/ringkasan-eksekutif?farm=00000000-0000-4000-8000-000000000000')->assertNotFound();
    }

    public function test_block_commodity_without_any_planting_cycle_is_included(): void
    {
        $crop = Commodity::create(['name' => 'Sorgum']);
        $farm = $this->farm('Lahan Baru Sorgum', 5);
        $block = new FarmBlock(['name' => 'Blok Sorgum', 'area_ha' => 5, 'is_active' => true]);
        $block->forceFill(['farm_id' => $farm->id, 'commodity_id' => $crop->id,
            'created_by' => $this->owner->id, 'land_clearing_started_at' => '2026-11-01'])->save();
        $this->get('/ringkasan-eksekutif')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('summary.rows', 1)->where('summary.rows.0.commodity.id', $crop->id)
            ->where('summary.rows.0.plantedHa', 0)->where('summary.rows.0.farm.areaHa', 5)
            ->where('summary.rows.0.projectedTon', null));
    }

    public function test_an_older_active_batch_without_a_harvest_schedule_is_unavailable_instead_of_zero(): void
    {
        $batch = $this->cycle($this->farm('Lahan Jadwal Belum Lengkap', 10), 'Jagung', 2, 21);
        $batch->update(['planted_at' => '2025-12-01', 'estimated_harvest_at' => null]);

        $this->get('/ringkasan-eksekutif?year=2026')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.rows.0.projectedTon', null));

        $this->cycle($batch->farm, 'Jagung', 3, 12);
        $this->get('/ringkasan-eksekutif?year=2026')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.rows.0.projectedTon', null)->where('summary.rows.0.projectionMissing', 1));
    }

    public function test_cileuksa_batches_without_varieties_use_block_commodities_and_keep_incomplete_estimates_unavailable(): void
    {
        $farm = $this->farm('Lahan Cileuksa', 20);
        $north = $this->block($farm, 'Blok Utara', 'Jagung', 10);
        $south = $this->block($farm, 'Blok Selatan', 'Jagung', 2.5);
        $grass = $this->block($farm, 'Blok Rumput', 'Rumput', 5);
        $active = $this->cycle($farm, 'Jagung', 4.25, 21);
        $active->forceFill(['block_id' => $north->id, 'batch_number' => 1, 'subblock_label' => 'A',
            'reserved_area_ha' => 4.25, 'created_by' => $this->owner->id, 'estimated_harvest_at' => '2027-01-15'])->save();
        $this->plannedBatch($south, 2);
        $this->plannedBatch($grass, 3);

        foreach ([2026, 2027] as $year) {
            $this->get('/ringkasan-eksekutif?year='.$year.'&farm='.$farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('summary.rows', 2)->has('summary.commodities', 2)
                ->where('summary.rows.0.commodity.name', 'Jagung')
                ->where('summary.rows.0.plantedHa', 4.25)
                ->where('summary.rows.0.projectedTon', $year === 2027 ? 170 : 0)->where('summary.rows.0.projectionMissing', 0)
                ->where('summary.rows.1.commodity.name', 'Rumput')
                ->where('summary.rows.1.projectedTon', null)->where('summary.rows.1.projectionMissing', 0));
        }
        foreach ([$north, $grass] as $block) {
            $this->get('/ringkasan-eksekutif?farm='.$farm->id.'&commodity='.$block->getAttribute('commodity_id'))
                ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('summary.rows', 1)->where('summary.rows.0.commodity.id', $block->getAttribute('commodity_id')));
        }
    }

    public function test_block_fallback_preserves_harvest_attribution_projection_dates_and_explicit_zero_estimates(): void
    {
        $farm = $this->farm('Lahan Proyeksi Blok', 10);
        $corn = $this->block($farm, 'Blok Jagung', 'Jagung', 5);
        $grass = $this->block($farm, 'Blok Rumput', 'Rumput', 5);
        $batch = $this->plannedBatch($corn, 1);
        $batch->update(['estimated_harvest_at' => '2027-01-15', 'estimated_weight_ton' => 21]);
        $zero = $this->plannedBatch($grass, 2);
        $zero->update(['estimated_harvest_at' => '2026-12-01', 'estimated_weight_ton' => 0]);
        $this->harvest($batch, 3);

        foreach ([2026 => [null, 3], 2027 => [null, 0]] as $year => [$projection, $actual]) {
            $this->get('/ringkasan-eksekutif?year='.$year)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('summary.rows', 2)
                ->where('summary.rows.0.commodity.name', 'Jagung')
                ->where('summary.rows.0.projectedTon', $projection)
                ->where('summary.rows.0.harvestedTon', $actual)
                ->where('summary.rows.1.commodity.name', 'Rumput')
                ->where('summary.rows.1.projectedTon', null));
        }
    }

    public function test_a_present_variety_takes_precedence_over_the_blocks_commodity(): void
    {
        $farm = $this->farm('Lahan Varietas Terpilih', 10);
        $grass = $this->block($farm, 'Blok Rumput', 'Rumput', 10);
        $batch = $this->cycle($farm, 'Jagung', 2, 12);
        $batch->forceFill(['block_id' => $grass->id, 'batch_number' => 1, 'subblock_label' => 'A',
            'reserved_area_ha' => 2, 'created_by' => $this->owner->id])->save();
        $this->harvest($batch, 5);

        $this->get('/ringkasan-eksekutif')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('summary.rows', 2)->where('summary.rows.0.commodity.name', 'Jagung')
            ->where('summary.rows.0.projectedTon', 80)->where('summary.rows.0.harvestedTon', 5)
            ->where('summary.rows.1.commodity.name', 'Rumput')->where('summary.rows.1.harvestedTon', 0));
    }

    public function test_block_fallback_cannot_use_hidden_blocks_or_commodities(): void
    {
        $farm = $this->farm('Lahan Batas Akses', 10);
        $block = $this->block($farm, 'Blok Jagung', 'Jagung', 10);
        $this->plannedBatch($block, 1);
        $roles = app(RolePermissions::class)->roles();

        foreach (['farm_blocks.view', 'commodities.view'] as $grant) {
            $restricted = $roles;
            unset($restricted['owner'][$grant]);
            config(['access.roles' => $restricted]);
            $this->get('/ringkasan-eksekutif')->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('summary.rows', 1)->has('summary.commodities', 0)
                ->where('summary.rows.0.commodity.id', '')
                ->where('summary.rows.0.commodity.name', 'Belum teridentifikasi')
                ->where('summary.rows.0.projectedTon', null));
        }
    }

    public function test_hidden_present_varieties_are_not_replaced_with_the_blocks_commodity(): void
    {
        $farm = $this->farm('Lahan Varietas Tersembunyi', 10);
        $block = $this->block($farm, 'Blok Jagung', 'Jagung', 10);
        $batch = $this->cycle($farm, 'Jagung', 2, 12);
        $batch->forceFill(['block_id' => $block->id, 'batch_number' => 1, 'subblock_label' => 'A',
            'reserved_area_ha' => 2, 'created_by' => $this->owner->id])->save();
        $roles = app(RolePermissions::class)->roles();
        unset($roles['owner']['crop_varieties.view']);
        config(['access.roles' => $roles]);

        $this->get('/ringkasan-eksekutif')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('summary.rows', 2)
            ->where('summary.rows.0.commodity.name', 'Jagung')->where('summary.rows.0.plantedHa', 0)
            ->where('summary.rows.1.commodity.name', 'Belum teridentifikasi')->where('summary.rows.1.plantedHa', 2));
    }

    private function block(Farm $farm, string $name, string $crop, float $area): FarmBlock
    {
        $commodity = Commodity::firstOrCreate(['name' => $crop]);
        $block = new FarmBlock(['name' => $name, 'area_ha' => $area, 'is_active' => true]);
        $block->forceFill(['farm_id' => $farm->id, 'commodity_id' => $commodity->id,
            'created_by' => $this->owner->id, 'land_clearing_started_at' => '2026-11-01'])->save();

        return $block;
    }

    private function plannedBatch(FarmBlock $block, int $number): PlantingCycle
    {
        $batch = new PlantingCycle;
        $batch->forceFill(['farm_id' => $block->farm_id, 'block_id' => $block->id,
            'batch_number' => $number, 'subblock_label' => 'A', 'status' => 'planned',
            'planned_planting_at' => '2026-11-20', 'planted_area_ha' => 0,
            'reserved_area_ha' => 0, 'created_by' => $this->owner->id])->save();

        return $batch;
    }

    public function test_summary_query_count_does_not_grow_per_farm_or_crop(): void
    {
        $this->harvest($this->cycle($this->farm('Lahan Query 1', 10), 'Komoditas 1', 2, 12), 5);
        $service = app(ExecutiveSummaryService::class);
        // Warm the actor's authorization relations before comparing equal read paths.
        $service->summary($this->owner, $this->team, []);
        $measure = function () use ($service): array {
            $connection = DB::connection();
            $connection->flushQueryLog();
            $connection->enableQueryLog();
            try {
                $summary = $service->summary($this->owner, $this->team, []);

                return [$summary, count($connection->getQueryLog())];
            } finally {
                $connection->disableQueryLog();
                $connection->flushQueryLog();
            }
        };
        [$small, $smallCount] = $measure();
        for ($number = 2; $number <= 8; $number++) {
            $this->harvest($this->cycle($this->farm('Lahan Query '.$number, 10), 'Komoditas '.$number, 2, 12), 5);
        }
        [$large, $largeCount] = $measure();

        $this->assertCount(1, $small['rows']);
        $this->assertCount(8, $large['rows']);
        $this->assertGreaterThan(0, $smallCount);
        $this->assertSame($smallCount, $largeCount, 'Summary queries must remain constant as farms, varieties and harvests increase.');
    }

    private function farm(string $name, float $area, ?string $party = null, ?Team $team = null): Farm
    {
        $farm = new Farm(['name' => $name, 'total_area_ha' => $area, 'province' => 'Jawa Barat', 'city' => 'Bogor', 'land_tenure' => $party ? 'partnership' : 'owned']);
        $farm->forceFill(['team_id' => ($team ?? $this->team)->id, 'created_by' => $this->owner->id, 'land_tenure_party' => $party])->save();

        return $farm;
    }

    private function cycle(Farm $farm, string $crop, float $area, ?float $estimate): PlantingCycle
    {
        $commodity = Commodity::firstOrCreate(['name' => $crop]);
        $variety = CropVariety::firstOrCreate(['commodity_id' => $commodity->id, 'name' => $crop.' unggul']);

        return PlantingCycle::create(['farm_id' => $farm->id, 'variety_id' => $variety->id, 'planted_area_ha' => $area,
            'planted_at' => '2026-09-01', 'estimated_harvest_at' => '2026-12-01', 'estimated_weight_ton' => $estimate, 'status' => 'active']);
    }

    private function harvest(PlantingCycle $cycle, float $ton, string $date = '2026-11-01 09:00:00+07'): void
    {
        $type = ActivityType::firstOrCreate(['code' => 'harvest'], ['name' => 'Panen']);
        $activity = FarmActivity::create(['farm_id' => $cycle->farm_id, 'planting_cycle_id' => $cycle->id,
            'activity_type_id' => $type->id, 'performed_by' => $this->owner->id, 'activity_date' => $date, 'status' => 'completed']);
        HarvestDetail::create(['activity_id' => $activity->id, 'gross_weight_ton' => $ton]);
    }
}
