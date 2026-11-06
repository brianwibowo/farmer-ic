<?php

namespace Tests\Feature\Owner;

use App\Enums\TeamRole;
use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\Team;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** HTTP/persistence integration on the guarded dedicated PostgreSQL test database. */
class OwnerDashboardTableTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(12, 0));
        $this->owner = User::factory()->create(['role' => 'owner']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->team = Team::factory()->create(['name' => 'Tenant Kebun Owner', 'is_personal' => false]);
        foreach ([$this->owner, $this->admin] as $actor) {
            $this->team->members()->attach($actor, ['role' => TeamRole::Member->value]);
            $actor->switchTeam($this->team);
        }
        $this->actingAs($this->owner);
    }

    public function test_live_rows_match_admin_population_and_link_to_the_owner_farm_detail(): void
    {
        $farm = $this->farm('Kebun Cibogo', '10.1250', 'partnership');
        $active = $this->cycle($farm, 'Jagung', 'BISI-18', 'active');
        $this->cycle($farm, 'Kedelai', 'Anjasmoro', 'harvested');
        $this->cycle($farm, 'Kentang', 'Granola', 'planned');
        $this->cycle($farm, 'Tomat', 'Servo', 'cancelled');
        $this->stage($active, 'soil_preparation', 2, 'completed');
        $this->stage($active, 'planting', 4, 'in_progress');
        $latest = $this->activity($farm, 'Penanaman terbaru Cibogo', '2026-11-04 09:00:00+07');
        $this->activity($farm, 'Laporan lebih lama', '2026-10-30 09:00:00+07');

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/Dashboard')
            ->where('landTable.total', 1)
            ->where('landTable.total_area_ha', '10.1250')
            ->where('landTable.rows.0.id', $farm->id)
            ->where('landTable.rows.0.name', 'Kebun Cibogo')
            ->where('landTable.rows.0.detail_url', '/kebun/'.$farm->id)
            ->where('landTable.rows.0.area_ha', '10.1250')
            ->where('landTable.rows.0.legality', 'Kerja sama')
            ->has('landTable.rows.0.crops', 2)
            ->where('landTable.rows.0.crops.0.name', 'Jagung')
            ->where('landTable.rows.0.crops.0.varieties.0', 'BISI-18')
            ->where('landTable.rows.0.crops.1.name', 'Kedelai')
            ->where('landTable.rows.0.progress.completed_stages', 1)
            ->where('landTable.rows.0.progress.total_stages', 2)
            ->where('landTable.rows.0.progress.current_stages', ['Penanaman'])
            ->where('landTable.rows.0.progress.running_batches', 2)
            ->where('landTable.rows.0.resume.id', $latest->id)
            ->where('landTable.rows.0.resume.title', 'Pengecekan pertumbuhan')
            ->where('landTable.rows.0.resume.description', 'Penanaman terbaru Cibogo')
            ->where('landTable.rows.0.resume.detail_url', '/aktivitas/'.$latest->id)
            ->where('dashboard.metrics.0.value', 1));
        $this->get('/kebun/'.$farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/Farm')->where('farm.id', $farm->id));
        $this->patch('/admin/farm-management/'.$farm->id, ['name' => 'Tidak diizinkan'])->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.metrics.0.value', 1)
            ->where('dashboard.commodities.0.name', 'Jagung')
            ->where('dashboard.commodities.1.name', 'Kedelai'));
    }

    public function test_admin_database_changes_appear_on_the_next_owner_request(): void
    {
        $farm = $this->farm('Kebun Awal', '10.0000', 'owned');
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('landTable.rows.0.name', 'Kebun Awal')
            ->where('landTable.total_area_ha', '10.0000'));

        $this->actingAs($this->admin)->patch('/admin/farm-management/'.$farm->id, [
            'name' => 'Kebun Diperbarui Admin', 'total_area_ha' => '12.3750', 'land_tenure' => 'rented',
        ])->assertRedirect();
        $report = $this->activity($farm, 'Resume baru dari database', '2026-11-05 10:15:00+07');
        $this->actingAs($this->owner)->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('landTable.rows.0.name', 'Kebun Diperbarui Admin')
            ->where('landTable.rows.0.area_ha', '12.3750')
            ->where('landTable.rows.0.legality', 'Sewa')
            ->where('landTable.rows.0.resume.id', $report->id)
            ->where('landTable.total_area_ha', '12.3750'));
    }

    public function test_dashboard_always_shows_all_visible_farms_even_with_a_previous_farm_filter(): void
    {
        $first = $this->farm('Kebun A', '10.1250', 'owned');
        $second = $this->farm('Kebun B', '2.8750', 'rented');
        $firstReport = $this->activity($first, 'Laporan A', '2026-11-04 07:00:00+07');
        $secondReport = $this->activity($second, 'Laporan B', '2026-11-05 08:00:00+07');
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('landTable.rows', 2)->where('landTable.total_area_ha', '13.0000')
            ->where('landTable.rows.0.resume.id', $firstReport->id)
            ->where('landTable.rows.1.resume.id', $secondReport->id));
        foreach ([$first, $second] as $previous) {
            $this->get('/?farm_id='.$previous->id)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('landTable.rows', 2)->where('landTable.total', 2)
                ->where('landTable.rows.0.id', $first->id)->where('landTable.rows.1.id', $second->id)
                ->where('landTable.total_area_ha', '13.0000')->has('dashboard.landMap.farms', 2)
                ->where('dashboard.metrics.0.value', 2)->where('dashboard.filters.farm_id', null));
        }
    }

    public function test_owner_pie_includes_every_scoped_commodity_while_admin_keeps_its_top_three(): void
    {
        $first = $this->farm('Kebun Komoditas A', '20.0000');
        $second = $this->farm('Kebun Komoditas B', '5.0000');
        $foreignTeam = Team::factory()->create(['is_personal' => false]);
        $foreign = $this->farm('Kebun Komoditas Rahasia', '999.0000', null, $foreignTeam);
        foreach ([
            [$first, 'Jagung', 'BISI-18', 'active', '2.0000'],
            [$first, 'Jagung', 'BISI-18', 'harvested', '3.0000'],
            [$first, 'Kedelai', 'Anjasmoro', 'active', '3.0000'],
            [$first, 'Padi', 'Ciherang', 'harvested', '2.0000'],
            [$first, 'Kacang', 'Gajah', 'active', '1.0000'],
            [$second, 'Padi', 'Ciherang', 'active', '0.5000'],
            [$second, 'Ubi', 'Cilembu', 'active', '0.2500'],
            [$first, 'Kentang rencana', 'Granola', 'planned', '100.0000'],
            [$first, 'Tomat batal', 'Servo', 'cancelled', '100.0000'],
            [$foreign, 'Komoditas rahasia', 'Varietas rahasia', 'active', '999.0000'],
        ] as [$farm, $commodity, $variety, $status, $area]) {
            $this->cycle($farm, $commodity, $variety, $status)->update(['planted_area_ha' => $area]);
        }

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.commodities', 5)
            ->where('dashboard.commodities', fn ($rows) => collect($rows)->pluck('areaValue', 'name')->all() === [
                'Jagung' => 5, 'Kedelai' => 3, 'Padi' => 2.5, 'Kacang' => 1, 'Ubi' => 0.25,
            ])
            ->where('dashboard.commodities.0.plantedArea', '5')
            ->where('dashboard.commodities.0.cycleCount', 2)
            ->where('dashboard.commodities.2.plantedArea', '2,5')
            ->where('dashboard.commodities.2.cycleCount', 2));

        $this->get('/?farm_id='.$second->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.commodities', 5)
            ->where('dashboard.commodities', fn ($rows) => collect($rows)->pluck('areaValue', 'name')->all() === [
                'Jagung' => 5, 'Kedelai' => 3, 'Padi' => 2.5, 'Kacang' => 1, 'Ubi' => 0.25,
            ]));

        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.commodities', 3)
            ->where('dashboard.commodities', fn ($rows) => collect($rows)->pluck('name')->all() === ['Jagung', 'Kedelai', 'Padi']));
    }

    public function test_other_tenants_never_enter_rows_crops_resume_or_total_and_foreign_links_deny(): void
    {
        $own = $this->farm('Kebun Terlihat', '3.2500');
        $foreignTeam = Team::factory()->create(['is_personal' => false]);
        $foreign = $this->farm('Kebun Rahasia', '999.0000', null, $foreignTeam);
        $this->cycle($foreign, 'Komoditas rahasia', 'Varietas rahasia');
        $this->activity($foreign, 'Resume rahasia', '2026-11-05 09:00:00+07');
        $this->get('/?team_id='.$foreignTeam->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('landTable.total', 1)->where('landTable.total_area_ha', '3.2500')
            ->where('landTable.rows.0.id', $own->id)
            ->has('landTable.rows.0.crops', 0)->where('landTable.rows.0.resume', null));
        $this->get('/?farm_id='.$foreign->id)->assertForbidden();
        $this->get('/kebun/'.$foreign->id)->assertForbidden();
    }

    public function test_owner_second_membership_still_requires_persisted_active_team_switch(): void
    {
        $own = $this->farm('Kebun Tim Awal', '5.0000');
        $second = Team::factory()->create(['is_personal' => false]);
        $second->members()->attach($this->owner, ['role' => TeamRole::Member->value]);
        $other = $this->farm('Kebun Tim Kedua', '8.5000', null, $second);
        $this->get('/?farm_id='.$other->id)->assertNotFound();
        $this->get('/?team_id='.$second->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('landTable.rows.0.id', $own->id)->where('landTable.total_area_ha', '5.0000'));
        $this->owner->refresh()->switchTeam($second);
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('landTable.rows.0.id', $other->id)->where('landTable.total_area_ha', '8.5000'));
    }

    public function test_missing_information_and_empty_farms_do_not_fabricate_values(): void
    {
        $farm = $this->farm('Kebun Belum Diisi', '7.6250');
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('landTable.rows.0.id', $farm->id)->where('landTable.rows.0.legality', null)
            ->has('landTable.rows.0.crops', 0)->where('landTable.rows.0.resume', null)
            ->where('landTable.rows.0.progress', [
                'completed_stages' => 0, 'total_stages' => 0, 'current_stages' => [], 'running_batches' => 0,
            ]));
        $farm->delete();
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('landTable.total', 0)->has('landTable.rows', 0)->where('landTable.total_area_ha', '0'));
    }

    public function test_latest_resume_is_all_time_and_is_not_rewritten_by_activity_chart_period(): void
    {
        $farm = $this->farm('Kebun Riwayat', '2.0000');
        $report = $this->activity($farm, 'Catatan historis terakhir', '2026-09-01 08:30:00+07');
        foreach ([7, 30] as $period) {
            $this->get('/?period='.$period)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('landTable.rows.0.resume.id', $report->id)
                ->where('landTable.rows.0.resume.date', '2026-09-01T08:30:00+07:00')
                ->where('dashboard.trend.total', 0));
        }
    }

    public function test_related_model_grants_are_applied_before_nested_values_and_stage_counts(): void
    {
        $farm = $this->farm('Kebun Terbatas', '4.0000');
        $cycle = $this->cycle($farm, 'Jagung', 'BISI-18');
        $this->stage($cycle, 'planting', 4, 'in_progress');
        $this->activity($farm, 'Aktivitas terlihat', '2026-11-04 07:00:00+07');
        $roles = app(RolePermissions::class)->roles();
        unset($roles['owner']['crop_varieties.view'], $roles['owner']['planting_cycle_stages.view'], $roles['owner']['farm_activities.view']);
        config(['access.roles' => $roles]);
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('landTable.rows.0.crops', 0)
            ->where('landTable.rows.0.progress.total_stages', 0)
            ->where('landTable.rows.0.progress.current_stages', [])
            ->where('landTable.rows.0.progress.running_batches', 1)
            ->where('landTable.rows.0.resume', null));
    }

    private function farm(string $name, string $area, ?string $tenure = null, ?Team $team = null): Farm
    {
        $farm = new Farm(['name' => $name, 'total_area_ha' => $area, 'land_tenure' => $tenure]);
        $farm->forceFill(['team_id' => ($team ?? $this->team)->id, 'created_by' => $this->admin->id])->save();

        return $farm;
    }

    private function cycle(Farm $farm, string $commodityName, string $varietyName, string $status = 'active'): PlantingCycle
    {
        $commodity = Commodity::firstOrCreate(['name' => $commodityName]);
        $variety = CropVariety::firstOrCreate(['commodity_id' => $commodity->id, 'name' => $varietyName]);

        return PlantingCycle::create([
            'farm_id' => $farm->id, 'variety_id' => $variety->id, 'planted_at' => '2026-09-01',
            'planted_area_ha' => '1.0000', 'status' => $status,
        ]);
    }

    private function activity(Farm $farm, string $description, string $date): FarmActivity
    {
        $type = ActivityType::firstOrCreate(['code' => 'growth_check'], ['name' => 'Pengamatan pertumbuhan']);

        return FarmActivity::create([
            'farm_id' => $farm->id, 'activity_type_id' => $type->id, 'performed_by' => $this->admin->id,
            'activity_date' => $date, 'description' => $description, 'status' => 'completed',
        ]);
    }

    private function stage(PlantingCycle $cycle, string $code, int $position, string $status): void
    {
        $stage = new PlantingCycleStage;
        $stage->forceFill([
            'planting_cycle_id' => $cycle->id, 'code' => $code, 'position' => $position, 'status' => $status,
            'started_at' => '2026-09-01 08:00:00+07',
            'completed_at' => $status === 'completed' ? '2026-09-02 08:00:00+07' : null,
        ])->save();
    }
}
