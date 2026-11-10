<?php

namespace Tests\Feature\Database;

use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\HarvestDetail;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\PlantObservation;
use App\Models\Team;
use Database\Seeders\DemoIdentitySeeder;
use Database\Seeders\DemoOwnerDashboardCoverageData;
use Database\Seeders\DemoOwnerDashboardCoverageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Optional local dashboard fixtures remain additive and idempotent. */
class DemoOwnerDashboardCoverageSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_regional_dashboard_coverage_without_duplicate_rows_on_rerun(): void
    {
        $this->seed(DemoIdentitySeeder::class);
        $this->seed(DemoOwnerDashboardCoverageSeeder::class);

        $team = Team::query()->where('slug', 'farm-ic-demo')->firstOrFail();
        $sites = DemoOwnerDashboardCoverageData::farms();
        $this->assertDatabaseCount('farms', count($sites));
        $this->assertSame(14, Farm::query()->where('team_id', $team->id)->count());
        $this->assertSame(14, PlantingCycle::query()->count());
        $this->assertSame(84, PlantingCycleStage::query()->count());
        $this->assertSame(14, FarmActivity::query()->count());
        $this->assertSame(12, PlantObservation::query()->count());
        $this->assertSame(2, HarvestDetail::query()->count());
        $this->assertSame(
            ['Kabupaten Bandung Barat', 'Kabupaten Bogor', 'Kabupaten Cianjur', 'Kabupaten Garut', 'Kabupaten Sukabumi'],
            Farm::query()->where('team_id', $team->id)->distinct()->orderBy('city')->pluck('city')->all(),
        );
        $this->assertSame(
            ['Kementan', 'Polri', 'PT Bumi Hijauan', 'TNI AD', 'TNI AL', 'TNI AU'],
            Farm::query()->where('team_id', $team->id)
                ->whereIn('land_tenure', ['rented', 'partnership'])
                ->distinct()->orderBy('land_tenure_party')->pluck('land_tenure_party')->all(),
        );
        $this->assertSame(0, Farm::query()->where('team_id', $team->id)->where('land_tenure', 'owned')
            ->whereNotNull('land_tenure_party')->count());

        $this->seed(DemoOwnerDashboardCoverageSeeder::class);

        $this->assertDatabaseCount('farms', count($sites));
        $this->assertSame(14, PlantingCycle::query()->count());
        $this->assertSame(84, PlantingCycleStage::query()->count());
        $this->assertSame(14, FarmActivity::query()->count());
        $this->assertSame(12, PlantObservation::query()->count());
        $this->assertSame(2, HarvestDetail::query()->count());
    }
}
