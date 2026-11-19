<?php

namespace Tests\Feature\Incidents;

use App\Enums\TeamRole;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\IncidentTicket;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use Carbon\CarbonImmutable;
use Database\Seeders\SopActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Integration coverage for the incident ticketing lifecycle against PostgreSQL. */
class IncidentTicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    private User $petugas;

    private Farm $farm;

    private FarmBlock $block;

    private PlantingCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00:00', 'Asia/Jakarta'));
        $this->seed(SopActivityTypeSeeder::class);

        $this->team = Team::factory()->create(['is_personal' => false]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->team->members()->attach($this->admin, ['role' => TeamRole::Member->value]);
        $this->admin->switchTeam($this->team);

        $this->farm = new Farm([
            'name' => 'Kebun Ticketing',
            'total_area_ha' => '10.0000',
            'land_tenure' => 'owned',
        ]);
        $this->farm->forceFill(['team_id' => $this->team->id, 'created_by' => $this->admin->id])->save();

        $this->petugas = User::factory()->create(['role' => 'petugas', 'assigned_farm_id' => $this->farm->id]);
        $this->team->members()->attach($this->petugas, ['role' => TeamRole::Member->value]);
        $this->petugas->switchTeam($this->team);

        $commodity = Commodity::firstOrCreate(['name' => 'Jagung']);
        $variety = CropVariety::firstOrCreate(['commodity_id' => $commodity->id, 'name' => 'Bisi 18']);

        $blocks = app(FarmBlockService::class);
        $this->block = $blocks->create($this->farm, $this->admin, $this->team, [
            'name' => 'Blok Selatan',
            'area_ha' => '4.0000',
            'commodity_id' => $commodity->id,
        ]);

        $this->cycle = $blocks->createBatch($this->block, $this->admin, $this->team);
        $this->cycle->forceFill([
            'variety_id' => $variety->id,
            'reserved_area_ha' => '2.0000',
            'planted_area_ha' => '2.0000',
            'planted_at' => '2026-11-01',
            'status' => 'active',
        ])->save();
    }

    public function test_petugas_submission_enforces_three_severities_and_two_initial_statuses(): void
    {
        // Legacy 'Berat' and 'In Progress' are rejected at the HTTP boundary.
        $this->actingAs($this->petugas)
            ->post('/petugas/aktivitas/mandiri/incident', [
                'work_date' => '2026-11-18',
                'worker_count' => 1,
                'cycle_id' => $this->cycle->id,
                'details' => [
                    'incident_category' => 'Hama',
                    'description' => 'Ulat grayak muncul.',
                    'area' => 200,
                    'area_unit' => 'm²',
                    'severity' => 'Berat',
                    'incident_status' => 'In Progress',
                ],
                'photos' => [UploadedFile::fake()->image('incident.jpg')],
            ])
            ->assertSessionHasErrors(['details.severity', 'details.incident_status']);

        // Immediate resolution without immediate_action fails validation.
        $this->actingAs($this->petugas)
            ->post('/petugas/aktivitas/mandiri/incident', [
                'work_date' => '2026-11-18',
                'worker_count' => 2,
                'cycle_id' => $this->cycle->id,
                'details' => [
                    'incident_category' => 'Alat rusak',
                    'description' => 'Selang irigasi sobek.',
                    'area' => 50,
                    'area_unit' => 'm²',
                    'severity' => 'Ringan',
                    'incident_status' => 'Sudah ditangani langsung',
                    'immediate_action' => '',
                ],
                'photos' => [UploadedFile::fake()->image('hose.jpg')],
            ])
            ->assertSessionHasErrors(['details.immediate_action']);

        // Immediate resolution WITH immediate_action and worker_count creates a resolved ticket + synced FarmActivity.
        $this->actingAs($this->petugas)
            ->post('/petugas/aktivitas/mandiri/incident', [
                'work_date' => '2026-11-18',
                'worker_count' => 2,
                'cycle_id' => $this->cycle->id,
                'details' => [
                    'incident_category' => 'Alat rusak',
                    'description' => 'Selang irigasi sobek.',
                    'area' => 50,
                    'area_unit' => 'm²',
                    'severity' => 'Ringan',
                    'incident_status' => 'Sudah ditangani langsung',
                    'immediate_action' => 'Mengganti sambungan selang baru.',
                ],
                'photos' => [UploadedFile::fake()->image('hose-fixed.jpg')],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $ticket = IncidentTicket::orderByDesc('id')->firstOrFail();
        $this->assertSame('resolved', $ticket->status);
        $this->assertSame('light', $ticket->severity);
        $this->assertSame('Mengganti sambungan selang baru.', $ticket->resolution_note);
        $this->assertSame('resolved', $ticket->activity->status);
        $this->assertSame(2, $ticket->activity->worker_count);
        $this->assertSame('light', $ticket->activity->incidentDetail->severity);

        // Immediate resolution WITHOUT worker_count succeeds and stores null.
        $this->travel(1)->seconds();
        $this->actingAs($this->petugas)
            ->post('/petugas/aktivitas/mandiri/incident', [
                'work_date' => '2026-11-18',
                'cycle_id' => $this->cycle->id,
                'details' => [
                    'incident_category' => 'Alat rusak',
                    'description' => 'Kran air kendor.',
                    'area' => 20,
                    'area_unit' => 'm²',
                    'severity' => 'Ringan',
                    'incident_status' => 'Sudah ditangani langsung',
                    'immediate_action' => 'Dikencangkan kembali.',
                ],
                'photos' => [UploadedFile::fake()->image('valve-fixed.jpg')],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $resolvedNoWorkers = IncidentTicket::orderByDesc('id')->firstOrFail();
        $this->assertSame('resolved', $resolvedNoWorkers->status);
        $this->assertNull($resolvedNoWorkers->activity->worker_count);

        // Invalid worker_count (0) is rejected even on incident reports.
        $this->actingAs($this->petugas)
            ->post('/petugas/aktivitas/mandiri/incident', [
                'work_date' => '2026-11-18',
                'worker_count' => 0,
                'cycle_id' => $this->cycle->id,
                'details' => [
                    'incident_category' => 'Alat rusak',
                    'description' => 'Pipa bocor.',
                    'area' => 10,
                    'area_unit' => 'm²',
                    'severity' => 'Ringan',
                    'incident_status' => 'Sudah ditangani langsung',
                    'immediate_action' => 'Ditambal.',
                ],
                'photos' => [UploadedFile::fake()->image('pipe.jpg')],
            ])
            ->assertSessionHasErrors('worker_count');

        // Omitting incident_status and worker_count defaults to open ('Perlu penanganan') with null worker_count.
        $this->travel(1)->seconds();
        $this->actingAs($this->petugas)
            ->post('/petugas/aktivitas/mandiri/incident', [
                'work_date' => '2026-11-18',
                'cycle_id' => $this->cycle->id,
                'details' => [
                    'incident_category' => 'Banjir',
                    'description' => 'Genangan air di sisi barat subblok.',
                    'area' => 500,
                    'area_unit' => 'm²',
                    'severity' => 'Darurat',
                ],
                'photos' => [UploadedFile::fake()->image('flood.jpg')],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $openTicket = IncidentTicket::orderByDesc('id')->firstOrFail();
        $this->assertSame('open', $openTicket->status);
        $this->assertSame('emergency', $openTicket->severity);
        $this->assertSame('open', $openTicket->activity->status);
        $this->assertNull($openTicket->activity->worker_count);
        $this->assertSame('emergency', $openTicket->activity->incidentDetail->severity);
        $this->assertSame('Genangan air di sisi barat subblok.', $openTicket->activity->description);

        // Sending worker_count on an open incident still normalizes stored worker_count to null.
        $this->travel(1)->seconds();
        $this->actingAs($this->petugas)
            ->post('/petugas/aktivitas/mandiri/incident', [
                'work_date' => '2026-11-18',
                'worker_count' => 3,
                'cycle_id' => $this->cycle->id,
                'details' => [
                    'incident_category' => 'Cuaca',
                    'description' => 'Angin kencang di blok selatan.',
                    'area' => 100,
                    'area_unit' => 'm²',
                    'severity' => 'Sedang',
                    'incident_status' => 'Perlu penanganan',
                ],
                'photos' => [UploadedFile::fake()->image('wind.jpg')],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $openWithWorkers = IncidentTicket::orderByDesc('id')->firstOrFail();
        $this->assertSame('open', $openWithWorkers->status);
        $this->assertNull($openWithWorkers->activity->worker_count);

        // Non-incident voluntary activities still require worker_count.
        $this->actingAs($this->petugas)
            ->post('/petugas/aktivitas/mandiri/other', [
                'work_date' => '2026-11-18',
                'details' => ['description' => 'Pembersihan gudang alat.'],
                'photos' => [UploadedFile::fake()->image('shed.jpg')],
            ])
            ->assertSessionHasErrors('worker_count');

        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_admin_can_respond_adjust_severity_direct_resolve_and_reopen_tickets(): void
    {
        $ticket = $this->createOpenTicket('cycle');

        $this->actingAs($this->admin)
            ->get('/admin/incidents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('incidents/Index')
                ->where('summary.open', 1)
                ->has('tickets.data', 1)
                ->where('tickets.data.0.id', $ticket->id));

        // Admin responds with instructions and upgrades severity to emergency.
        $this->actingAs($this->admin)
            ->post('/admin/incidents/'.$ticket->id.'/respond', [
                'admin_note' => 'Segera lakukan penyemprotan insektisida terarah.',
                'severity' => 'emergency',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('in_progress', $ticket->status);
        $this->assertSame('emergency', $ticket->severity);
        $this->assertSame('Segera lakukan penyemprotan insektisida terarah.', $ticket->admin_note);
        $this->assertSame('in_progress', $ticket->activity->status);
        $this->assertSame('emergency', $ticket->activity->incidentDetail->severity);

        // Petugas sees Admin instructions on Home alerts linking directly to the ticket.
        $this->actingAs($this->petugas)
            ->get('/petugas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('home.incidents.total', 1)
                ->where('home.incidents.data.0.ticket_id', $ticket->id)
                ->where('home.incidents.data.0.detail_url', route('petugas.incidents.show', $ticket))
                ->where('home.incidents.data.0.severity', 'emergency')
                ->where('home.incidents.data.0.status', 'in_progress')
                ->where('home.incidents.data.0.action', 'Segera lakukan penyemprotan insektisida terarah.'));

        // Admin closes ticket directly.
        $this->actingAs($this->admin)
            ->post('/admin/incidents/'.$ticket->id.'/resolve', [
                'resolution_note' => 'Sudah dicek tim lapang, kondisi pulih.',
                'severity' => 'light',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertSame('light', $ticket->severity);
        $this->assertSame('Sudah dicek tim lapang, kondisi pulih.', $ticket->resolution_note);
        $this->assertSame('resolved', $ticket->activity->status);
        $this->assertSame('light', $ticket->activity->incidentDetail->severity);

        // Dashboard & Home open incident metrics drop to 0.
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('dashboard.metrics.3.value', 0));

        // Admin reopens ticket with mandatory reason.
        $this->actingAs($this->admin)
            ->post('/admin/incidents/'.$ticket->id.'/reopen', ['reason' => 'no'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->admin)
            ->post('/admin/incidents/'.$ticket->id.'/reopen', [
                'reason' => 'Serangan hama muncul kembali pada pinggir blok.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->resolution_note);
        $this->assertSame('Serangan hama muncul kembali pada pinggir blok.', $ticket->reopen_reason);
        $this->assertSame('open', $ticket->activity->status);

        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_petugas_can_resolve_ticket_via_locked_follow_up_activity_and_reopen_it(): void
    {
        $ticket = $this->createOpenTicket('cycle');

        // Also put the ticket into in_progress to prove follow-up works from both open and in_progress.
        $this->actingAs($this->admin)
            ->post('/admin/incidents/'.$ticket->id.'/respond', [
                'admin_note' => 'Lakukan pemeliharaan gulma segera.',
            ])
            ->assertSessionHasNoErrors();

        // Incompatible category for a cycle-scoped ticket redirects with error toast back to ticket detail.
        $this->actingAs($this->petugas)
            ->get('/petugas/aktivitas/mandiri/land_cleaning?incident_ticket_id='.$ticket->id)
            ->assertRedirect(route('petugas.incidents.show', $ticket));

        // Compatible category loads VoluntaryCreate with locked incident context.
        $this->actingAs($this->petugas)
            ->get('/petugas/aktivitas/mandiri/maintenance?incident_ticket_id='.$ticket->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('petugas/VoluntaryCreate')
                ->where('incidentTicket.id', $ticket->id)
                ->where('incidentTicket.cycle_id', $this->cycle->id));

        // Tampering with location (wrong cycle) is rejected.
        $otherCycle = app(FarmBlockService::class)->createBatch($this->block, $this->admin, $this->team);
        $otherCycle->forceFill([
            'variety_id' => $this->cycle->variety_id,
            'reserved_area_ha' => '1.0000',
            'planted_area_ha' => '1.0000',
            'planted_at' => '2026-11-01',
            'status' => 'active',
        ])->save();

        $this->actingAs($this->petugas)
            ->post('/petugas/aktivitas/mandiri/maintenance', [
                'work_date' => '2026-11-18',
                'worker_count' => 2,
                'cycle_id' => $otherCycle->id,
                'incident_ticket_id' => $ticket->id,
                'details' => ['area' => 500, 'area_unit' => 'm²'],
                'photos' => [UploadedFile::fake()->image('wrong.jpg')],
            ])
            ->assertSessionHasErrors('cycle_id');

        // Valid follow-up activity logs to daily logbook and resolves ticket.
        $this->actingAs($this->petugas)
            ->post('/petugas/aktivitas/mandiri/maintenance', [
                'work_date' => '2026-11-18',
                'worker_count' => 2,
                'cycle_id' => $this->cycle->id,
                'notes' => 'Penyemprotan dan pembersihan gulma selesai dilakukan.',
                'incident_ticket_id' => $ticket->id,
                'details' => ['area' => 1000, 'area_unit' => 'm²'],
                'photos' => [UploadedFile::fake()->image('followup.jpg')],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('petugas.incidents.show', $ticket));

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertNotNull($ticket->resolved_activity_id);
        $this->assertNotSame($ticket->activity_id, $ticket->resolved_activity_id);
        $this->assertSame('resolved', $ticket->activity->status);
        $this->assertSame('Penyemprotan dan pembersihan gulma selesai dilakukan.', $ticket->resolution_note);

        // Second attempt to follow up an already resolved ticket fails cleanly.
        $this->actingAs($this->petugas)
            ->post('/petugas/aktivitas/mandiri/other', [
                'work_date' => '2026-11-18',
                'worker_count' => 1,
                'cycle_id' => $this->cycle->id,
                'incident_ticket_id' => $ticket->id,
                'details' => ['description' => 'Percobaan ulang.'],
                'photos' => [UploadedFile::fake()->image('dup.jpg')],
            ])
            ->assertSessionHasErrors('incident_ticket_id');

        // Petugas can reopen the resolved ticket cleanly.
        $this->actingAs($this->petugas)
            ->post('/petugas/incidents/'.$ticket->id.'/reopen', [
                'reason' => 'Kondisi kembali memburuk setelah hujan.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('petugas.incidents.show', $ticket));

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->resolved_activity_id);
        $this->assertSame('open', $ticket->activity->status);

        // And can resolve again via 'other' on a farm-wide ticket too.
        $farmWideTicket = $this->createOpenTicket('farm');
        $this->actingAs($this->petugas)
            ->post('/petugas/aktivitas/mandiri/other', [
                'work_date' => '2026-11-18',
                'worker_count' => 1,
                'notes' => 'Perbaikan pagar keliling selesai.',
                'incident_ticket_id' => $farmWideTicket->id,
                'details' => ['description' => 'Memperbaiki pagar pembatas kebun.'],
                'photos' => [UploadedFile::fake()->image('fence.jpg')],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('petugas.incidents.show', $farmWideTicket));

        $this->assertSame('resolved', $farmWideTicket->refresh()->status);

        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_block_scoped_ticket_offers_block_prep_and_other_only(): void
    {
        $blockTicket = $this->createOpenTicket('block');

        $this->actingAs($this->petugas)
            ->get('/petugas/incidents/'.$blockTicket->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('petugas/IncidentShow')
                ->where('ticket.follow_up_options', function ($options): bool {
                    $codes = collect($options)->pluck('code')->sort()->values()->all();

                    return $codes === ['base_fertilization', 'land_cleaning', 'other', 'soil_preparation'];
                }));

        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_authorization_cross_tenant_isolation_and_force_delete(): void
    {
        $ticket = $this->createOpenTicket('cycle');

        // Petugas cannot access admin ticket queue or admin actions.
        $this->actingAs($this->petugas)->get('/admin/incidents')->assertForbidden();
        $this->actingAs($this->petugas)->post('/admin/incidents/'.$ticket->id.'/respond', [
            'admin_note' => 'Arahan tidak sah.',
        ])->assertForbidden();
        $this->actingAs($this->petugas)->post('/admin/incidents/'.$ticket->id.'/resolve', [
            'resolution_note' => 'Resolusi langsung tidak sah.',
        ])->assertForbidden();

        // Petugas from another farm in the same team cannot access the ticket.
        $otherFarm = new Farm(['name' => 'Kebun Sebelah', 'total_area_ha' => '5.0000', 'land_tenure' => 'owned']);
        $otherFarm->forceFill(['team_id' => $this->team->id, 'created_by' => $this->admin->id])->save();
        $otherPetugas = User::factory()->create(['role' => 'petugas', 'assigned_farm_id' => $otherFarm->id]);
        $this->team->members()->attach($otherPetugas, ['role' => TeamRole::Member->value]);
        $otherPetugas->switchTeam($this->team);

        $this->actingAs($otherPetugas)->get('/petugas/incidents/'.$ticket->id)->assertForbidden();

        // Admin from another team cannot see or mutate this team's ticket.
        $foreignTeam = Team::factory()->create(['is_personal' => false]);
        $foreignAdmin = User::factory()->create(['role' => 'admin']);
        $foreignTeam->members()->attach($foreignAdmin, ['role' => TeamRole::Member->value]);
        $foreignAdmin->switchTeam($foreignTeam);

        $this->actingAs($foreignAdmin)->get('/admin/incidents')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('tickets.data', 0),
        );
        $this->actingAs($foreignAdmin)->get('/admin/incidents/'.$ticket->id)->assertForbidden();
        $this->actingAs($foreignAdmin)->post('/admin/incidents/'.$ticket->id.'/respond', [
            'admin_note' => 'Lintas tenant.',
        ])->assertForbidden();

        // Force delete removes farm and incident_tickets cleanly without FK issues.
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $url = '/admin/farm-management/'.$this->farm->id.'/force-delete';
        $summary = $this->actingAs($superadmin)->get($url)->assertOk()->viewData('page')['props']['summary'];
        $this->actingAs($superadmin)->delete($url, [
            'confirmation_name' => $this->farm->name,
            'reason' => 'Hapus data uji tiket insiden',
            'summary_token' => $summary['token'],
        ])->assertRedirect();
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $this->assertDatabaseCount('incident_tickets', 0);
        $this->assertDatabaseCount('farms', 1); // $otherFarm remains
    }

    public function test_missing_incident_ticket_permissions_block_routes_honestly(): void
    {
        $ticket = $this->createOpenTicket('cycle');

        $roles = config('access.roles');
        unset($roles['admin']['incident_tickets.update']);
        config(['access.roles' => $roles]);

        $this->actingAs($this->admin)->post('/admin/incidents/'.$ticket->id.'/respond', [
            'admin_note' => 'Tanpa izin update.',
        ])->assertForbidden();
        $this->actingAs($this->admin)->post('/admin/incidents/'.$ticket->id.'/resolve', [
            'resolution_note' => 'Tanpa izin update.',
        ])->assertForbidden();

        unset($roles['petugas']['incident_tickets.view']);
        config(['access.roles' => $roles]);
        $this->actingAs($this->petugas)->get('/petugas/incidents')->assertForbidden();
        $this->actingAs($this->petugas)->get('/petugas/incidents/'.$ticket->id)->assertForbidden();
    }

    public function test_unplanted_subblock_incident_uses_block_area_limit_instead_of_zero(): void
    {
        $unplanted = app(FarmBlockService::class)->createBatch($this->block, $this->admin, $this->team);
        $this->assertSame('planned', $unplanted->status);
        $this->assertSame('0.0000', (string) $unplanted->planted_area_ha);
        $this->assertSame('0.0000', (string) $unplanted->getAttribute('reserved_area_ha'));

        $this->actingAs($this->petugas)
            ->get('/petugas/aktivitas/mandiri/incident')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cycles', function ($cycles) use ($unplanted): bool {
                    $plannedCycle = collect($cycles)->firstWhere('id', $unplanted->id);
                    $plantedCycle = collect($cycles)->firstWhere('id', $this->cycle->id);

                    return is_array($plannedCycle)
                        && $plannedCycle['area_limit_m2'] === 40000
                        && is_array($plantedCycle)
                        && $plantedCycle['area_limit_m2'] === 20000;
                }));

        // Exceeding the 4 ha (40,000 m²) block area limit still fails validation.
        $this->actingAs($this->petugas)
            ->post('/petugas/aktivitas/mandiri/incident', [
                'work_date' => '2026-11-18',
                'cycle_id' => $unplanted->id,
                'details' => [
                    'incident_category' => 'Banjir',
                    'description' => 'Air meluap melebihi blok.',
                    'area' => 40001,
                    'area_unit' => 'm²',
                    'severity' => 'Darurat',
                    'incident_status' => 'Perlu penanganan',
                ],
                'photos' => [UploadedFile::fake()->image('too-big.jpg')],
            ])
            ->assertSessionHasErrors('details.area');

        // Valid area within the block limit succeeds on the unplanted subblock.
        $this->actingAs($this->petugas)
            ->post('/petugas/aktivitas/mandiri/incident', [
                'work_date' => '2026-11-18',
                'cycle_id' => $unplanted->id,
                'details' => [
                    'incident_category' => 'Banjir',
                    'description' => 'Genangan sebelum penanaman pada subblok baru.',
                    'area' => 500,
                    'area_unit' => 'm²',
                    'severity' => 'Sedang',
                    'incident_status' => 'Perlu penanganan',
                ],
                'photos' => [UploadedFile::fake()->image('unplanted.jpg')],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $ticket = IncidentTicket::orderByDesc('id')->firstOrFail();
        $this->assertSame($unplanted->id, $ticket->planting_cycle_id);
        $this->assertSame('0.0500', (string) $ticket->activity->incidentDetail->affected_area_ha);

        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    private function createOpenTicket(string $scope): IncidentTicket
    {
        $payload = [
            'work_date' => '2026-11-18',
            'worker_count' => 2,
            'details' => [
                'incident_category' => 'Hama',
                'description' => 'Temuan gangguan lapangan ('.$scope.').',
                'area' => 300,
                'area_unit' => 'm²',
                'severity' => 'Sedang',
                'incident_status' => 'Perlu penanganan',
            ],
            'photos' => [UploadedFile::fake()->image('ticket-'.$scope.'.jpg')],
        ];

        if ($scope === 'cycle') {
            $payload['cycle_id'] = $this->cycle->id;
        } elseif ($scope === 'block') {
            $payload['block_id'] = $this->block->id;
        }

        $this->travel(1)->seconds();
        $this->actingAs($this->petugas)
            ->post('/petugas/aktivitas/mandiri/incident', $payload)
            ->assertSessionHasNoErrors();

        return IncidentTicket::orderByDesc('id')->firstOrFail();
    }
}
