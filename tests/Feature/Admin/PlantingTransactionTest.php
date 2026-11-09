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

class PlantingTransactionTest extends TestCase
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
        $this->farm = $this->farm('Kebun Penanaman', $this->team, $this->admin);
        $this->block = $this->block($this->farm, $this->team, $this->admin, 'B01');
        $commodity = Commodity::create(['name' => 'Jagung']);
        $this->variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Bisi 18']);
        $this->cycle = $this->cycle($this->block, $this->team, $this->admin, $this->variety, '2026-11-04');
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

    private function cycle(FarmBlock $block, Team $team, User $actor, CropVariety $variety, string $date): PlantingCycle
    {
        $cycle = app(FarmBlockService::class)->createBatch($block, $actor, $team, [
            'reserved_area_ha' => '2.0000', 'planned_planting_at' => $date,
        ]);
        $cycle->forceFill([
            'variety_id' => $variety->id,
            'planted_at' => $date,
            'planted_area_ha' => '1.5000',
            'status' => 'active',
        ])->save();

        return $cycle;
    }

    private function report(Farm $farm, FarmBlock $block, PlantingCycle $cycle, User $actor, string $date, string $area, string $seedKg, int $workers): FarmActivity
    {
        $type = ActivityType::firstOrCreate(['code' => 'planting'], [
            'name' => 'Penanaman', 'detail_key' => 'planting', 'is_selectable' => true, 'sort_order' => 30,
        ]);
        $log = DailyActivityLog::create([
            'farm_id' => $farm->id,
            'recorded_by' => $actor->id,
            'work_date' => $date,
            'start_time' => '07:00',
            'end_time' => '11:00',
            'notes' => 'Laporan penanaman '.$date,
        ]);
        $activity = FarmActivity::create([
            'farm_id' => $farm->id,
            'planting_cycle_id' => $cycle->id,
            'activity_type_id' => $type->id,
            'performed_by' => $actor->id,
            'activity_date' => CarbonImmutable::parse($date.' 09:00', 'Asia/Jakarta'),
            'description' => 'Penanaman '.$block->code.' '.$date,
            'status' => 'completed',
            'daily_activity_log_id' => $log->id,
            'position' => 1,
            'worker_count' => $workers,
            'obstacle_status' => 'none',
        ]);
        $activity->forceFill(['block_id' => $block->id])->save();
        $activity->plantingDetail()->create([
            'seed_name' => 'Benih Jagung Bisi 18',
            'quantity_kg' => $seedKg,
            'planting_pattern' => '20 x 40 cm',
            'area_ha' => $area,
        ]);
        ActivityPhoto::create([
            'activity_id' => $activity->id,
            'label' => 'Bukti tanam',
            'caption' => 'Foto penanaman '.$date,
            'image_url' => '/images/kebun-placeholder.svg',
            'uploaded_at' => now(),
        ]);

        return $activity;
    }

    public function test_admin_sees_scoped_planting_reports_and_filtered_summary(): void
    {
        $this->report($this->farm, $this->block, $this->cycle, $this->admin, '2026-11-04', '1.5000', '12.500', 4);

        $secondFarm = $this->farm('Kebun Penanaman B', $this->team, $this->admin);
        $secondBlock = $this->block($secondFarm, $this->team, $this->admin, 'B02');
        $secondCycle = $this->cycle($secondBlock, $this->team, $this->admin, $this->variety, '2026-11-05');
        $this->report($secondFarm, $secondBlock, $secondCycle, $this->admin, '2026-11-05', '0.5000', '5.000', 2);

        $foreignTeam = Team::factory()->create(['is_personal' => false]);
        $foreignAdmin = User::factory()->create(['role' => 'admin']);
        $this->join($foreignAdmin, $foreignTeam);
        $foreignFarm = $this->farm('Kebun Asing', $foreignTeam, $foreignAdmin);
        $foreignBlock = $this->block($foreignFarm, $foreignTeam, $foreignAdmin, 'B99');
        $foreignCycle = $this->cycle($foreignBlock, $foreignTeam, $foreignAdmin, $this->variety, '2026-11-06');
        $this->report($foreignFarm, $foreignBlock, $foreignCycle, $foreignAdmin, '2026-11-06', '1.0000', '8.000', 3);

        $this->get('/admin/transaksi-penanaman')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('planting-transactions/Index')
            ->where('summary.reports', 2)->where('summary.area_ha', '2.0000')
            ->where('summary.seed_kg', '17.500')->where('summary.workers', 6)->where('summary.farms', 2)
            ->where('transactions.total', 2)->has('transactions.data', 2)
            ->where('transactions.data.0.farm.name', 'Kebun Penanaman B')
            ->where('transactions.data.0.block.code', 'B02')
            ->where('transactions.data.0.variety.name', 'Bisi 18')
            ->where('transactions.data.0.area_ha', '0.5000')
            ->where('transactions.data.0.quantity_kg', '5.000')
            ->where('transactions.data.0.worker_count', 2)
            ->where('transactions.data.0.source', 'Realisasi tanam')
            ->has('transactions.data.0.photos', 1)
            ->has('farms', 2)->has('blocks', 2)->has('varieties', 1)
            ->where('filters.per_page', 15));

        $this->get('/admin/transaksi-penanaman?farm_id='.$this->farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.reports', 1)->where('summary.area_ha', '1.5000')
            ->where('summary.seed_kg', '12.500')->where('summary.workers', 4)->where('summary.farms', 1)
            ->where('transactions.total', 1)
            ->where('transactions.data.0.farm.id', $this->farm->id));

        $this->get('/admin/transaksi-penanaman?block_id='.$secondBlock->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('transactions.total', 1)->where('transactions.data.0.block.id', $secondBlock->id));

        $this->get('/admin/transaksi-penanaman?variety_id='.$this->variety->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('transactions.total', 2));

        $this->get('/admin/transaksi-penanaman?from_date=2026-11-05')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('transactions.total', 1)->where('transactions.data.0.activity_date', '2026-11-05'));

        $this->get('/admin/transaksi-penanaman?search=Bisi')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('transactions.total', 2));

        $this->get('/admin/transaksi-penanaman?search=Kebun Asing')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('transactions.total', 0));

        $this->assertSame(3, FarmActivity::count());
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_empty_state_and_filter_validation(): void
    {
        $this->get('/admin/transaksi-penanaman')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.reports', 0)->where('summary.area_ha', '0.0000')
            ->where('summary.seed_kg', '0.000')->where('summary.workers', 0)->where('summary.farms', 0)
            ->where('transactions.total', 0)->has('transactions.data', 0));

        $this->get('/admin/transaksi-penanaman?from_date=2026-11-05&to_date=2026-11-04')
            ->assertSessionHasErrors('to_date');
        $this->get('/admin/transaksi-penanaman?farm_id=not-a-uuid')
            ->assertSessionHasErrors('farm_id');
    }

    public function test_non_admin_roles_cannot_open_the_planting_ledger(): void
    {
        $petugas = User::factory()->create(['role' => 'petugas']);
        $this->join($petugas, $this->team);
        $petugas->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->actingAs($petugas)->get('/admin/transaksi-penanaman')->assertForbidden();

        $owner = User::factory()->create(['role' => 'owner']);
        $this->join($owner, $this->team);
        $this->actingAs($owner)->get('/admin/transaksi-penanaman')->assertForbidden();
    }
}
