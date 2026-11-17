<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmRisk;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Services\AdminDashboardService;
use App\Support\RolePermissions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Integration coverage against the guarded, dedicated PostgreSQL test database. */
class DashboardIncidentReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:00:00', 'Asia/Jakarta'));
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->team = Team::factory()->create(['is_personal' => false]);
        $this->team->members()->attach($this->admin, ['role' => TeamRole::Member->value]);
        $this->admin->switchTeam($this->team);
        $this->actingAs($this->admin);
    }

    private function farm(string $name, ?Team $team = null): Farm
    {
        $farm = new Farm(['name' => $name, 'total_area_ha' => 10]);
        $farm->forceFill(['team_id' => ($team ?? $this->team)->id, 'created_by' => $this->admin->id])->save();

        return $farm;
    }

    private function incident(Farm $farm, ?string $status = null, array $details = [], ?string $date = null): FarmActivity
    {
        $type = ActivityType::firstOrCreate(['code' => 'incident'], [
            'name' => 'Pelaporan insiden', 'detail_key' => 'incident', 'is_selectable' => true, 'is_active' => true,
        ]);
        $activity = FarmActivity::create([
            'farm_id' => $farm->id, 'activity_type_id' => $type->id, 'performed_by' => $this->admin->id,
            'activity_date' => $date ?? now()->subHour(), 'status' => $status,
            'description' => 'Catatan penanganan saluran air',
        ]);
        $activity->forceFill(['task_details' => json_encode($details, JSON_THROW_ON_ERROR)])->save();
        $activity->incidentDetail()->create(['incident_category' => 'Cuaca', 'severity' => 'Darurat', 'affected_area_ha' => '0.01']);

        return $activity;
    }

    private function risk(Farm $farm, string $status = 'open'): FarmRisk
    {
        $commodity = Commodity::firstOrCreate(['name' => 'Jagung']);
        $variety = CropVariety::firstOrCreate(['commodity_id' => $commodity->id, 'name' => 'Bisi 18']);
        $cycle = PlantingCycle::create([
            'farm_id' => $farm->id, 'variety_id' => $variety->id, 'planted_area_ha' => 1,
            'planted_at' => '2026-10-01', 'status' => 'active',
        ]);

        return FarmRisk::create([
            'planting_cycle_id' => $cycle->id, 'risk_description' => 'Drainase perlu ditinjau',
            'status' => $status, 'detected_at' => now()->subDays(2),
        ]);
    }

    public function test_petugas_incident_submission_appears_in_both_admin_risk_cards(): void
    {
        Storage::fake('local');
        $farm = $this->farm('Kebun Pelaporan');
        $petugas = User::factory()->create(['role' => 'petugas', 'assigned_farm_id' => $farm->id]);
        $this->team->members()->attach($petugas, ['role' => TeamRole::Member->value]);
        $petugas->switchTeam($this->team);
        ActivityType::firstOrCreate(['code' => 'incident'], [
            'name' => 'Pelaporan insiden', 'detail_key' => 'incident', 'is_selectable' => true, 'is_active' => true,
        ]);

        foreach (['Open', 'Resolved'] as $status) {
            $this->actingAs($petugas)->post('/petugas/aktivitas/mandiri/incident', [
                'work_date' => today()->toDateString(), 'start_time' => '07:00', 'worker_count' => 2,
                'notes' => 'Tim sedang memeriksa drainase',
                'details' => ['incident_category' => 'Cuaca', 'description' => 'Tanaman rebah akibat angin',
                    'area' => 100, 'area_unit' => 'm²', 'severity' => 'Darurat', 'incident_status' => $status],
                'photos' => [UploadedFile::fake()->image('laporan-insiden.jpg')],
            ])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertDatabaseCount('incident_details', 2);
        $this->assertDatabaseCount('farm_risks', 0);
        $open = FarmActivity::where('task_details->incident_status', 'Open')->firstOrFail();

        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.metrics.3.value', 1)->where('dashboard.risks.total', 1)
            ->has('dashboard.risks.items', 1)->where('dashboard.risks.items.0.id', 'incident:'.$open->id)
            ->where('dashboard.risks.items.0.title', 'Tanaman rebah akibat angin')
            ->where('dashboard.risks.items.0.detectedAt', '07:00 · 17 November 2026'));
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_unresolved_statuses_json_fallback_and_column_precedence_match_the_total(): void
    {
        $farm = $this->farm('Kebun Status');
        foreach (['open', 'progress', 'in_progress', 'pending', 'reported'] as $status) {
            $this->incident($farm, $status);
        }
        $this->incident($farm); // Old reports without a status remain open.
        $this->incident($farm, null, ['incident_status' => 'In Progress']);
        $this->incident($farm, '  ', ['incident_status' => 'Open']);
        $this->incident($farm, 'open', ['incident_status' => 'Resolved']); // Persisted status wins.
        foreach (['resolved', 'completed', 'closed', 'cancelled', 'draft', 'unknown'] as $status) {
            $this->incident($farm, $status);
        }
        $this->incident($farm, null, ['incident_status' => 'Resolved']);
        $this->incident($farm, 'resolved', ['incident_status' => 'Open']);
        $this->incident($farm, 'open', date: now()->addDay()->toIso8601String());
        $this->risk($farm);
        $this->risk($farm, 'closed');

        $this->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.metrics.3.value', 10)->where('dashboard.risks.total', 10)
            ->has('dashboard.risks.items', 10)
            ->where('dashboard.risks.items.9.title', 'Drainase perlu ditinjau'));
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_incident_counts_and_rows_follow_farm_filter_and_tenant_visibility(): void
    {
        $first = $this->farm('Kebun Utara');
        $second = $this->farm('Kebun Selatan');
        $foreignTeam = Team::factory()->create(['is_personal' => false]);
        $foreign = $this->farm('Kebun Rahasia', $foreignTeam);
        $visible = $this->incident($first, details: ['description' => 'Genangan di kebun utara']);
        $this->incident($second);
        $this->incident($foreign);

        $this->get('/admin/dashboard?team_id='.$foreignTeam->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.metrics.3.value', 2)->where('dashboard.risks.total', 2)->has('dashboard.risks.items', 2));
        $this->get('/admin/dashboard?farm_id='.$first->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.metrics.3.value', 1)->where('dashboard.risks.total', 1)->has('dashboard.risks.items', 1)
            ->where('dashboard.risks.items.0.id', 'incident:'.$visible->id));
        $this->get('/admin/dashboard?farm_id='.$foreign->id)->assertForbidden();

        $petugas = User::factory()->create(['role' => 'petugas', 'assigned_farm_id' => $first->id]);
        $this->team->members()->attach($petugas, ['role' => TeamRole::Member->value]);
        $petugas->switchTeam($this->team);
        $this->actingAs($petugas)->get('/admin/dashboard')->assertForbidden();
        Gate::forUser($petugas)->authorize('viewAny', [Farm::class, $this->team]);
        $summary = app(AdminDashboardService::class)->summary($petugas, $this->team);
        $this->assertSame(1, $summary['risks']['total']);
        $this->assertSame('incident:'.$visible->id, $summary['risks']['items'][0]['id']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    #[DataProvider('incidentReadGrants')]
    public function test_missing_related_read_grant_excludes_incident_counts_and_payload(string $grant): void
    {
        $farm = $this->farm('Kebun Izin');
        $this->incident($farm);
        $this->risk($farm);
        $roles = app(RolePermissions::class)->roles();
        unset($roles['admin'][$grant]);
        config(['access.roles' => $roles]);

        $this->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.metrics.3.value', 1)->where('dashboard.risks.total', 1)
            ->has('dashboard.risks.items', 1)->where('dashboard.risks.items.0.title', 'Drainase perlu ditinjau'));
    }

    public static function incidentReadGrants(): array
    {
        return [['incident_details.view'], ['farm_activities.view']];
    }

    public function test_preview_is_bounded_and_sorted_without_truncating_the_total(): void
    {
        $farm = $this->farm('Kebun Banyak Laporan');
        $this->risk($farm);
        for ($hour = 0; $hour < 12; $hour++) {
            $this->incident($farm, details: ['description' => 'Laporan insiden '.$hour], date: now()->subHours($hour)->toIso8601String());
        }

        $this->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.metrics.3.value', 13)->where('dashboard.risks.total', 13)
            ->has('dashboard.risks.items', 10)->where('dashboard.risks.items.0.title', 'Laporan insiden 0')
            ->where('dashboard.risks.items.9.title', 'Laporan insiden 9'));
    }
}
