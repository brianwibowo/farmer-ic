<?php

namespace Tests\Feature\Admin;

use App\Enums\TeamRole;
use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HarvestTransactionTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    private Farm $farm;

    private FarmBlock $block;

    private PlantingCycle $cycle;

    private CropVariety $variety;

    protected function setUp(): void
    {
        parent::setUp();
        $this->team = Team::factory()->create(['is_personal' => false]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->join($this->admin, $this->team);
        $this->farm = $this->farm('Kebun Panen', $this->team, $this->admin);
        $this->block = $this->block($this->farm, $this->team, $this->admin, 'B01');
        $commodity = Commodity::create(['name' => 'Jagung']);
        $this->variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Bisi 18']);
        $this->cycle = $this->cycle($this->block, $this->team, $this->admin, $this->variety);
        $this->actingAs($this->admin);
    }

    private function join(User $user, Team $team): void
    {
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->unsetRelation('teams')->unsetRelation('currentTeam');
    }

    private function farm(string $name, Team $team, User $actor): Farm
    {
        $farm = new Farm(['name' => $name, 'land_tenure' => 'owned', 'total_area_ha' => '10.0000']);
        $farm->forceFill(['team_id' => $team->id, 'created_by' => $actor->id])->save();

        return $farm;
    }

    private function block(Farm $farm, Team $team, User $actor, string $code): FarmBlock
    {
        return app(FarmBlockService::class)->create($farm, $actor, $team, [
            'code' => $code, 'name' => 'Blok '.$code, 'area_ha' => '5.0000',
        ]);
    }

    private function cycle(FarmBlock $block, Team $team, User $actor, CropVariety $variety): PlantingCycle
    {
        $cycle = app(FarmBlockService::class)->createBatch($block, $actor, $team, [
            'reserved_area_ha' => '2.0000', 'planned_planting_at' => '2026-09-01',
        ]);
        $cycle->forceFill([
            'variety_id' => $variety->id,
            'planted_at' => '2026-09-01',
            'planted_area_ha' => '2.0000',
            'status' => 'active',
        ])->save();

        return $cycle;
    }

    private function report(Farm $farm, FarmBlock $block, PlantingCycle $cycle, User $actor, string $date, string $area, string $weight, int $workers, string $ripeness = 'Matang panen'): FarmActivity
    {
        $type = ActivityType::firstOrCreate(['code' => 'harvest'], [
            'name' => 'Panen', 'detail_key' => 'harvest', 'is_selectable' => true, 'sort_order' => 60,
        ]);
        $log = DailyActivityLog::create([
            'farm_id' => $farm->id,
            'recorded_by' => $actor->id,
            'work_date' => $date,
            'start_time' => '07:00',
            'end_time' => '11:00',
            'notes' => 'Laporan panen '.$date,
        ]);
        $activity = FarmActivity::create([
            'farm_id' => $farm->id,
            'planting_cycle_id' => $cycle->id,
            'activity_type_id' => $type->id,
            'performed_by' => $actor->id,
            'activity_date' => CarbonImmutable::parse($date.' 09:00', 'Asia/Jakarta'),
            'description' => 'Panen '.$block->code.' '.$date,
            'status' => 'completed',
            'daily_activity_log_id' => $log->id,
            'position' => 1,
            'worker_count' => $workers,
            'obstacle_status' => 'none',
        ]);
        $activity->forceFill(['block_id' => $block->id])->save();
        $activity->harvestDetail()->create([
            'harvested_area_ha' => $area,
            'gross_weight_ton' => $weight,
            'moisture_percentage' => '14.50',
            'color' => 'Kuning keemasan',
            'ripeness' => $ripeness,
            'cleanliness' => 'Bersih setelah sortasi',
        ]);
        ActivityPhoto::create([
            'activity_id' => $activity->id,
            'label' => 'Bukti panen',
            'caption' => 'Foto panen '.$date,
            'image_url' => '/images/kebun-placeholder.svg',
            'uploaded_at' => now(),
        ]);

        return $activity;
    }

    public function test_admin_sees_scoped_harvest_reports_and_filtered_summary(): void
    {
        $this->report($this->farm, $this->block, $this->cycle, $this->admin, '2026-11-04', '1.5000', '1.500', 4);

        $secondFarm = $this->farm('Kebun Panen B', $this->team, $this->admin);
        $secondBlock = $this->block($secondFarm, $this->team, $this->admin, 'B02');
        $secondCycle = $this->cycle($secondBlock, $this->team, $this->admin, $this->variety);
        $this->report($secondFarm, $secondBlock, $secondCycle, $this->admin, '2026-11-05', '0.5000', '2.500', 2, 'Matang fisiologis');

        $foreignTeam = Team::factory()->create(['is_personal' => false]);
        $foreignAdmin = User::factory()->create(['role' => 'admin']);
        $this->join($foreignAdmin, $foreignTeam);
        $foreignFarm = $this->farm('Kebun Asing', $foreignTeam, $foreignAdmin);
        $foreignBlock = $this->block($foreignFarm, $foreignTeam, $foreignAdmin, 'B99');
        $foreignCycle = $this->cycle($foreignBlock, $foreignTeam, $foreignAdmin, $this->variety);
        $this->report($foreignFarm, $foreignBlock, $foreignCycle, $foreignAdmin, '2026-11-06', '1.0000', '3.000', 3);

        $this->get('/admin/transaksi-panen')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('harvest-transactions/Index')
            ->where('summary.subblocks', 2)->where('summary.reports', 2)
            ->where('summary.area_ha', '2.0000')->where('summary.weight_ton', '4.000')
            ->has('summary', 4)
            ->where('transactions.total', 2)->has('transactions.data', 2)
            ->where('transactions.data.0.farm.name', 'Kebun Panen B')
            ->where('transactions.data.0.block.code', 'B02')
            ->where('transactions.data.0.variety.name', 'Bisi 18')
            ->where('transactions.data.0.harvested_area_ha', '0.5000')
            ->where('transactions.data.0.gross_weight_ton', '2.500')
            ->where('transactions.data.0.report_count', 1)
            ->where('transactions.data.0.first_activity_date', '2026-11-05')
            ->where('transactions.data.0.last_activity_date', '2026-11-05')
            ->has('transactions.data.0.reports', 1)
            ->where('transactions.data.0.reports.0.harvested_area_ha', '0.5000')
            ->where('transactions.data.0.reports.0.gross_weight_ton', '2.500')
            ->has('transactions.data.0.reports.0.photos', 1)
            ->has('farms', 2)->has('blocks', 2)->has('varieties', 1)
            ->where('filters.per_page', 15));

        $this->get('/admin/transaksi-panen?farm_id='.$this->farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.subblocks', 1)->where('summary.reports', 1)
            ->where('summary.area_ha', '1.5000')->where('summary.weight_ton', '1.500')
            ->where('transactions.total', 1)
            ->where('transactions.data.0.farm.id', $this->farm->id));

        $this->get('/admin/transaksi-panen?block_id='.$secondBlock->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('transactions.total', 1)->where('transactions.data.0.block.id', $secondBlock->id));

        $this->get('/admin/transaksi-panen?variety_id='.$this->variety->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('transactions.total', 2));

        $this->get('/admin/transaksi-panen?from_date=2026-11-05')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('transactions.total', 1)->where('transactions.data.0.last_activity_date', '2026-11-05'));

        $this->get('/admin/transaksi-panen?search=Bisi')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('transactions.total', 2));

        $this->get('/admin/transaksi-panen?search=Kebun Asing')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('transactions.total', 0));

        $this->assertSame(3, FarmActivity::count());
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_multiple_harvest_reports_for_the_same_subblock_are_one_grouped_row(): void
    {
        $this->report($this->farm, $this->block, $this->cycle, $this->admin, '2026-11-04', '1.0000', '1.500', 4);
        $this->report($this->farm, $this->block, $this->cycle, $this->admin, '2026-11-05', '0.5000', '2.500', 3);

        $this->get('/admin/transaksi-panen')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.subblocks', 1)
            ->where('summary.reports', 2)
            ->where('summary.area_ha', '1.5000')
            ->where('summary.weight_ton', '4.000')
            ->where('transactions.total', 1)
            ->has('transactions.data', 1)
            ->where('transactions.data.0.report_count', 2)
            ->where('transactions.data.0.harvested_area_ha', '1.5000')
            ->where('transactions.data.0.gross_weight_ton', '4.000')
            ->where('transactions.data.0.first_activity_date', '2026-11-04')
            ->where('transactions.data.0.last_activity_date', '2026-11-05')
            ->has('transactions.data.0.reports', 2)
            ->where('transactions.data.0.reports.0.activity_date', '2026-11-04')
            ->where('transactions.data.0.reports.0.harvested_area_ha', '1.0000')
            ->where('transactions.data.0.reports.0.gross_weight_ton', '1.500')
            ->where('transactions.data.0.reports.1.activity_date', '2026-11-05')
            ->where('transactions.data.0.reports.1.harvested_area_ha', '0.5000')
            ->where('transactions.data.0.reports.1.gross_weight_ton', '2.500'));
    }

    public function test_empty_state_and_filter_validation(): void
    {
        $this->get('/admin/transaksi-panen')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.subblocks', 0)->where('summary.reports', 0)
            ->where('summary.area_ha', '0.0000')->where('summary.weight_ton', '0.000')
            ->has('summary', 4)
            ->where('transactions.total', 0)->has('transactions.data', 0));

        $this->get('/admin/transaksi-panen?from_date=2026-11-05&to_date=2026-11-04')
            ->assertSessionHasErrors('to_date');
        $this->get('/admin/transaksi-panen?farm_id=not-a-uuid')
            ->assertSessionHasErrors('farm_id');
    }

    public function test_non_admin_roles_cannot_open_the_harvest_ledger(): void
    {
        $petugas = User::factory()->create(['role' => 'petugas']);
        $this->join($petugas, $this->team);
        $petugas->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->actingAs($petugas)->get('/admin/transaksi-panen')->assertForbidden();

        $owner = User::factory()->create(['role' => 'owner']);
        $this->join($owner, $this->team);
        $this->actingAs($owner)->get('/admin/transaksi-panen')->assertForbidden();
    }
}
