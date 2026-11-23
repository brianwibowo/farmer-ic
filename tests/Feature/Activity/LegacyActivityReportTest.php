<?php

namespace Tests\Feature\Activity;

use App\Models\ActivityType;
use App\Models\DailyActivityLog;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoActivitySeeder;
use Database\Seeders\DemoFarmSeeder;
use Database\Seeders\DemoIdentitySeeder;
use Database\Seeders\DemoSopSeeder;
use Database\Seeders\SopActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegacyActivityReportTest extends TestCase
{
    use RefreshDatabase;

    private function petugas(): User
    {
        $this->seed([DemoIdentitySeeder::class, DemoFarmSeeder::class, SopActivityTypeSeeder::class,
            DemoSopSeeder::class, DemoActivitySeeder::class]);

        return User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['petugas']['email'])->firstOrFail();
    }

    private function oldHarvest(User $petugas, string $weight = '2.750'): FarmActivity
    {
        $cycle = PlantingCycle::query()->where('farm_id', $petugas->assigned_farm_id)->firstOrFail();
        $activity = FarmActivity::create([
            'farm_id' => $petugas->assigned_farm_id, 'planting_cycle_id' => $cycle->id,
            'activity_type_id' => ActivityType::query()->where('code', 'harvest')->firstOrFail()->id,
            'performed_by' => $petugas->id, 'activity_date' => CarbonImmutable::parse('2026-12-15 09:00', 'Asia/Jakarta'),
            'description' => 'Panen percobaan Blok 2', 'status' => 'completed',
        ]);
        $activity->harvestDetail()->create([
            'harvested_area_ha' => '0.5000', 'gross_weight_ton' => $weight, 'moisture_percentage' => '18.50',
            'color' => 'Kuning cerah', 'ripeness' => 'Matang panen', 'cleanliness' => 'Bersih',
        ]);

        return $activity;
    }

    public function test_legacy_detail_and_jakarta_date_filter_do_not_expose_other_farms(): void
    {
        $petugas = $this->petugas();
        $activity = $this->oldHarvest($petugas);
        $activity->forceFill(['activity_date' => CarbonImmutable::parse('2026-12-15 00:15', 'Asia/Jakarta')])->save();
        $this->actingAs($petugas)->get('/petugas/laporan/'.$activity->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('petugas/ReportShow')->where('log', null)->where('legacyActivity.id', $activity->id)
            ->where('date', '2026-12-15')->missing('canUpdate'));
        $this->get('/petugas/laporan?date=2026-12-15')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('reports.total', 1)->where('reports.data.0.id', $activity->id));
        $this->get('/petugas/laporan?date=2026-12-14')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('reports.total', 0));
        $petugas->forceFill(['assigned_farm_id' => null])->save();
        $petugas->unsetRelation('assignedFarm');
        $this->actingAs($petugas)->get('/petugas/laporan/'.$activity->id)->assertForbidden();
    }

    public function test_standard_activity_seeder_links_only_matching_fixture_to_existing_header(): void
    {
        $petugas = $this->petugas();
        $activity = $this->oldHarvest($petugas);
        $unrelated = $this->oldHarvest($petugas, '3.000');
        $header = DailyActivityLog::create([
            'farm_id' => $activity->farm_id, 'recorded_by' => $petugas->id, 'work_date' => '2026-12-15',
        ]);
        $before = $header->refresh()->getRawOriginal();
        $this->assertNotNull(\App\Models\HarvestDetail::visibleTo($petugas, $activity->farm->team)->where('activity_id', $activity->id)->first());
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($header->id, $activity->fresh()->daily_activity_log_id);
        $this->assertNull($unrelated->fresh()->daily_activity_log_id);
        $this->assertSame($before, $header->refresh()->getRawOriginal());
        $position = $activity->fresh()->position;
        $this->seed(DemoActivitySeeder::class);
        $this->assertSame($position, $activity->fresh()->position);
    }

    public function test_standard_activity_seeder_leaves_missing_header_unmodified(): void
    {
        $petugas = $this->petugas();
        $activity = $this->oldHarvest($petugas);
        $count = DailyActivityLog::query()->count();
        $this->seed(DemoActivitySeeder::class);
        $this->assertNull($activity->fresh()->daily_activity_log_id);
        $this->assertSame($count, DailyActivityLog::query()->count());
        $this->actingAs($petugas)->get('/petugas/laporan/'.$activity->id)->assertOk();
    }
}
