<?php

namespace Tests\Feature\Activity;

use App\Enums\TeamRole;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\User;
use App\Services\Activity\AdminLogbookService;
use App\Services\Activity\DailyActivityLogService;
use Database\Seeders\BlockMappingActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminLogbookTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlockMappingActivityTypeSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->currentTeam->update(['is_personal' => false]);
        $this->farm = $this->farm($this->admin, 'Kebun Arjuna');
    }

    public function test_admission_and_navigation_follow_the_existing_admin_and_resource_policies(): void
    {
        $this->get('/admin/logbook')->assertRedirect('/login');
        foreach (['owner', 'petugas'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get('/admin/logbook')->assertForbidden();
            $this->get('/admin/logbook/'.$this->farm->id)->assertForbidden();
            $this->get('/settings/profile')->assertInertia(fn (Assert $page) => $page->where('navigation.logbook', false));
        }
        $this->actingAs($this->admin)->get('/admin/logbook')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('logbook/Index')->where('navigation.logbook', true));
        $developer = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($developer)->get('/admin/logbook')->assertOk();
    }

    public function test_directory_counts_and_search_exclude_other_tenants_and_include_empty_farms(): void
    {
        $this->report($this->farm, $this->admin, '2026-10-27', true);
        $this->report($this->farm, $this->admin, '2026-10-26');
        $this->farm($this->admin, 'Kebun Belum Melapor');
        [$foreign, $other] = $this->foreignFarm();
        $this->report($foreign, $other, '2026-10-28', true);
        $this->actingAs($this->admin)->get('/admin/logbook')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('farms.total', 2)->has('farms.data', 2)
            ->where('farms.data.0.log_count', 2)->where('farms.data.0.latest_work_date', '2026-10-27')
            ->where('farms.data.1.log_count', 0)->where('farms.data.1.latest_work_date', null)
            ->where('summary.farms', 2)->where('summary.reports', 2)->where('summary.activities', 2)->where('summary.obstacles', 1));
        $this->get('/admin/logbook?search=Rahasia')->assertInertia(fn (Assert $page) => $page
            ->where('farms.total', 0)->where('summary.reports', 2));
    }

    public function test_history_filters_and_report_parent_binding_never_cross_the_farm_boundary(): void
    {
        $recent = $this->report($this->farm, $this->admin, '2026-10-27');
        $this->report($this->farm, $this->admin, '2026-10-12');
        [$foreign, $other] = $this->foreignFarm();
        $hidden = $this->report($foreign, $other, '2026-10-27');
        $url = '/admin/logbook/'.$this->farm->id;
        $this->actingAs($this->admin)->get($url.'?from_date=2026-10-20&to_date=2026-10-28')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('logbook/Farm')->where('logs.total', 1)->where('logs.data.0.id', $recent->id)
            ->where('summary.reports', 2)->has('recorders', 1)->where('recorders.0.id', $this->admin->id));
        $this->get($url.'?recorded_by='.$other->id)->assertInertia(fn (Assert $page) => $page->where('logs.total', 0));
        $this->get('/admin/logbook/'.$foreign->id)->assertForbidden();
        $this->get('/admin/logbook/'.$foreign->id.'/'.$hidden->id)->assertForbidden();
        $this->get($url.'/'.$hidden->id)->assertNotFound();
        $this->get($url.'?from_date=2026-10-28&to_date=2026-10-20')->assertSessionHasErrors('to_date');
    }

    public function test_admin_logbook_is_read_only_in_ui_policy_and_existing_write_endpoints(): void
    {
        $log = $this->report($this->farm, $this->admin, '2026-10-27');
        $url = '/admin/logbook/'.$this->farm->id;
        $this->actingAs($this->admin)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('logbook/Farm')->missing('can.create')->missing('blocks')->missing('recorder'));
        $this->get($url.'/'.$log->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('logbook/Show')->where('log.activities.0.block_mapping.length_m', '35.000'));
        $this->post($url, $this->payload('2026-10-28'))->assertStatus(405);
        $this->post('/kebun/'.$this->farm->id.'/daily-logs', $this->payload('2026-10-28'))->assertForbidden();
        $this->postJson('/api/kebun/'.$this->farm->id.'/daily-logs', $this->payload('2026-10-28'))->assertForbidden();
        foreach (['create', 'update', 'delete'] as $ability) {
            $subject = $ability === 'create' ? [DailyActivityLog::class, $this->farm] : $log;
            $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($this->admin)->allows($ability, $subject));
        }
        $this->get('/kebun/'.$this->farm->id.'/daily-logs')->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.create', false));
        $this->assertDatabaseCount('daily_activity_logs', 1);
    }

    public function test_petugas_records_on_mobile_and_admin_can_read_the_saved_report(): void
    {
        [$foreign, $other] = $this->foreignFarm();
        $petugas = User::factory()->create(['role' => 'petugas']);
        $petugas->teams()->attach($this->admin->currentTeam, ['role' => TeamRole::Member->value]);
        $petugas->forceFill(['assigned_farm_id' => $this->farm->id, 'current_team_id' => $this->admin->current_team_id])->save();
        $payload = $this->payload('2026-10-27');
        $payload['recorded_by'] = $other->id;
        $url = '/kebun/'.$this->farm->id.'/daily-logs';
        $this->actingAs($petugas)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farmer/DailyActivityLog')->where('can.create', true));
        $this->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect($url);
        $log = DailyActivityLog::query()->where('farm_id', $this->farm->id)->firstOrFail();
        $this->assertSame($petugas->id, $log->recorded_by);
        $this->post('/kebun/'.$foreign->id.'/daily-logs', $payload)->assertForbidden();
        $this->post($url, [...$payload, 'farm_id' => $foreign->id])->assertSessionHasErrors('farm_id');
        $invalid = $payload;
        $invalid['activities'][0]['block_mapping']['length_m'] = -1;
        $this->post($url, $invalid)->assertSessionHasErrors('activities.0.block_mapping.length_m');
        $this->actingAs($this->admin)->get('/admin/logbook/'.$this->farm->id.'/'.$log->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('log.recorder_name', $petugas->name)->where('log.worker_count', 6));
        $this->assertDatabaseCount('daily_activity_logs', 1);
    }

    public function test_missing_grants_deny_admission_and_empty_service_totals(): void
    {
        $this->report($this->farm, $this->admin, '2026-10-27');
        $grants = config('access.roles.admin');
        $grants['daily_activity_logs.view'] = null;
        config(['access.roles.admin' => $grants]);
        $this->actingAs($this->admin)->get('/admin/logbook')->assertForbidden();
        $this->get('/admin/logbook/'.$this->farm->id)->assertForbidden();
        $this->get('/settings/profile')->assertInertia(fn (Assert $page) => $page->where('navigation.logbook', false));
        $summary = app(AdminLogbookService::class)->summary($this->admin, $this->admin->currentTeam);
        $this->assertSame(0, $summary['reports']);
        $this->assertSame(0, $summary['activities']);
        $grants['daily_activity_logs.view'] = 'any';
        $grants['farms.view'] = null;
        config(['access.roles.admin' => $grants]);
        $this->assertSame(0, app(AdminLogbookService::class)->summary($this->admin, $this->admin->currentTeam)['reports']);
    }

    public function test_service_own_scope_keeps_the_petugas_single_assigned_farm(): void
    {
        $this->report($this->farm, $this->admin, '2026-10-27');
        $second = $this->farm($this->admin, 'Kebun Kedua');
        $this->report($second, $this->admin, '2026-10-27');
        $petugas = User::factory()->create(['role' => 'petugas']);
        $petugas->teams()->attach($this->admin->currentTeam, ['role' => TeamRole::Member->value]);
        $petugas->forceFill(['assigned_farm_id' => $this->farm->id, 'current_team_id' => $this->admin->current_team_id])->save();
        $summary = app(AdminLogbookService::class)->summary($petugas->fresh(), $this->admin->currentTeam);
        $this->assertSame(1, $summary['farms']);
        $this->assertSame(1, $summary['reports']);
        $petugas->forceFill(['assigned_farm_id' => null])->save();
        $this->assertSame(0, app(AdminLogbookService::class)->summary($petugas->fresh(), $this->admin->currentTeam)['reports']);
    }

    private function farm(User $actor, string $name): Farm
    {
        return Farm::forceCreate(['name' => $name, 'team_id' => $actor->current_team_id, 'created_by' => $actor->id, 'total_area_ha' => '12.5000', 'land_tenure' => 'owned']);
    }

    private function foreignFarm(): array
    {
        $other = User::factory()->create(['role' => 'admin']);
        $other->currentTeam->update(['is_personal' => false]);

        return [$this->farm($other, 'Kebun Rahasia'), $other];
    }

    private function report(Farm $farm, User $actor, string $date, bool $obstacle = false): DailyActivityLog
    {
        $payload = $this->payload($date);
        $payload['recorded_by'] = $actor->id;
        if ($obstacle) {
            $payload['activities'][0]['obstacle_status'] = 'reported';
            $payload['activities'][0]['obstacles'] = 'Batas blok mengikuti lereng.';
        }

        return app(DailyActivityLogService::class)->create($farm, $payload);
    }

    private function payload(string $date): array
    {
        return ['work_date' => $date, 'start_time' => '08:00', 'end_time' => '16:00', 'worker_count' => 6,
            'activities' => [['type' => 'block_mapping', 'obstacle_status' => 'none',
                'block_mapping' => ['block_name' => 'Blok Utara', 'length_m' => 35, 'width_m' => 50]]]];
    }
}
