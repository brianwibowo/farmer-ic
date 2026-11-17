<?php

namespace Tests\Feature\Activity;

use App\Enums\TeamRole;
use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycleTask;
use App\Models\User;
use App\Services\Activity\AdminLogbookService;
use App\Services\Activity\DailyActivityLogService;
use App\Services\FarmBlockService;
use Database\Seeders\BlockMappingActivityTypeSeeder;
use Database\Seeders\SopActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $this->seed(SopActivityTypeSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->currentTeam->update(['is_personal' => false]);
        $this->farm = $this->farm($this->admin, 'Kebun Arjuna');
    }

    public function test_admission_and_navigation_follow_the_existing_admin_and_resource_policies(): void
    {
        $activity = $this->incidentReport($this->farm, $this->admin, '2026-10-27');

        $this->get('/admin/logbook')->assertRedirect('/login');
        foreach (['owner', 'petugas'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get('/admin/logbook')->assertForbidden();
            $this->get('/admin/logbook/'.$this->farm->id)->assertForbidden();
            $this->get('/admin/logbook/aktivitas/'.$activity->id)->assertForbidden();
            $this->get('/settings/profile')->assertInertia(fn (Assert $page) => $page->where('navigation.logbook', false));
        }

        $this->actingAs($this->admin)->get('/admin/logbook')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('logbook/Index')->missing('view')->missing('farms')->where('navigation.logbook', true));
        $developer = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($developer)->get('/admin/logbook')->assertOk();
    }

    public function test_activity_feed_counts_and_search_exclude_other_tenants(): void
    {
        $incident = $this->incidentReport($this->farm, $this->admin, '2026-10-27');
        $mapping = $this->report($this->farm, $this->admin, '2026-10-26');
        $this->farm($this->admin, 'Kebun Belum Melapor');
        [$foreign, $other] = $this->foreignFarm();
        $this->incidentReport($foreign, $other, '2026-10-28');

        $this->actingAs($this->admin)->get('/admin/logbook')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('logbook/Index')
            ->where('activities.total', 2)
            ->where('activities.data.0.id', $incident->id)
            ->where('activities.data.0.code', 'incident')
            ->where('activities.data.0.source', 'mandiri')
            ->where('activities.data.1.daily_log_id', $mapping->id)
            ->missing('farms')
            ->missing('view')
            ->where('summary.farms', 2)
            ->where('summary.reports', 2)
            ->where('summary.activities', 2)
            ->where('summary.incidents', 1));

        $this->get('/admin/logbook?search=Rahasia')->assertInertia(fn (Assert $page) => $page
            ->where('activities.total', 0)
            ->where('summary.incidents', 1));
    }

    public function test_activity_feed_filters_by_farm_type_source_performer_and_dates(): void
    {
        $secondFarm = $this->farm($this->admin, 'Kebun Bromo');
        $petugas = User::factory()->create(['role' => 'petugas']);
        $petugas->teams()->attach($this->admin->currentTeam, ['role' => TeamRole::Member->value]);
        $petugas->forceFill(['assigned_farm_id' => $this->farm->id, 'current_team_id' => $this->admin->current_team_id])->save();

        $incident = $this->incidentReport($this->farm, $petugas, '2026-10-27');
        $sopActivity = $this->sopHarvestActivity($secondFarm, $this->admin, '2026-10-24');

        $this->actingAs($this->admin)->get('/admin/logbook?farm_id='.$this->farm->id.'&activity_type=incident&performed_by='.$petugas->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activities.total', 1)
                ->where('activities.data.0.id', $incident->id));

        $this->get('/admin/logbook?source=sop&from_date=2026-10-20&to_date=2026-10-25')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activities.total', 1)
                ->where('activities.data.0.id', $sopActivity->id)
                ->where('activities.data.0.source', 'sop'));

        $this->get('/admin/logbook?from_date=2026-10-28&to_date=2026-10-20')
            ->assertSessionHasErrors('to_date');
    }

    public function test_activity_detail_displays_modern_typed_details_photos_and_isolates_tenants(): void
    {
        Storage::fake('local');
        $petugas = User::factory()->create(['role' => 'petugas']);
        $petugas->teams()->attach($this->admin->currentTeam, ['role' => TeamRole::Member->value]);
        $petugas->forceFill(['assigned_farm_id' => $this->farm->id, 'current_team_id' => $this->admin->current_team_id])->save();

        $activity = $this->sopHarvestActivity($this->farm, $petugas, '2026-10-27');
        $photo = ActivityPhoto::create(['activity_id' => $activity->id, 'image_url' => '/images/kebun-placeholder.svg']);

        [$foreign, $other] = $this->foreignFarm();
        $foreignActivity = $this->incidentReport($foreign, $other, '2026-10-27');

        $this->actingAs($this->admin)->get('/admin/logbook/aktivitas/'.$activity->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('logbook/Activity')
                ->where('farm.id', $this->farm->id)
                ->where('activity.id', $activity->id)
                ->where('activity.code', 'harvest')
                ->where('activity.source', 'sop')
                ->where('activity.block', 'Blok Utama')
                ->where('activity.subblok', 'SB-01')
                ->where('activity.performer_name', $petugas->name)
                ->where('activity.worker_count', 5)
                ->where('activity.task.name', 'Panen Raya')
                ->where('activity.daily_log.id', $activity->daily_activity_log_id)
                ->has('activity.details', 2)
                ->where('activity.details.0.label', 'Luas panen (ha)')
                ->where('activity.details.1.label', 'Berat (ton)')
                ->has('activity.photos', 1)
                ->where('activity.photos.0.id', $photo->id)
                ->missing('activity.obstacle_status')
                ->missing('activity.obstacles')
                ->missing('activity.proposed_solution')
                ->missing('activity.action_taken'));

        $this->get('/kebun/'.$this->farm->id.'/daily-logs/activities/'.$activity->id.'/photos/'.$photo->id)
            ->assertOk();

        $this->get('/admin/logbook/aktivitas/'.$foreignActivity->id)->assertForbidden();
        $this->get('/admin/logbook/aktivitas/'.Str::uuid())->assertNotFound();
    }

    public function test_history_filters_and_report_parent_binding_never_cross_the_farm_boundary(): void
    {
        $recent = $this->report($this->farm, $this->admin, '2026-10-27');
        $incidentActivity = $this->incidentReport($this->farm, $this->admin, '2026-10-12');
        [$foreign, $other] = $this->foreignFarm();
        $hidden = $this->report($foreign, $other, '2026-10-27');
        $url = '/admin/logbook/'.$this->farm->id;

        $this->actingAs($this->admin)->get($url.'?from_date=2026-10-20&to_date=2026-10-28')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('logbook/Farm')->where('logs.total', 1)->where('logs.data.0.id', $recent->id)
            ->where('logs.data.0.incident_count', 0)
            ->where('summary.reports', 2)->where('summary.incidents', 1)
            ->has('recorders', 1)->where('recorders.0.id', $this->admin->id));

        $this->get($url.'?from_date=2026-10-01&to_date=2026-10-15')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('logs.data.0.id', $incidentActivity->daily_activity_log_id)
            ->where('logs.data.0.incident_count', 1));

        $this->get($url.'?recorded_by='.$other->id)->assertInertia(fn (Assert $page) => $page->where('logs.total', 0));
        $this->get('/admin/logbook/'.$foreign->id)->assertForbidden();
        $this->get('/admin/logbook/'.$foreign->id.'/'.$hidden->id)->assertForbidden();
        $this->get($url.'/'.$hidden->id)->assertNotFound();
        $this->get($url.'?from_date=2026-10-28&to_date=2026-10-20')->assertSessionHasErrors('to_date');
    }

    public function test_admin_logbook_is_read_only_and_show_renders_modern_activity_details(): void
    {
        $log = $this->report($this->farm, $this->admin, '2026-10-27');
        $url = '/admin/logbook/'.$this->farm->id;
        $this->actingAs($this->admin)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('logbook/Farm')->missing('can.create')->missing('blocks')->missing('recorder'));
        $this->get($url.'/'.$log->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('logbook/Show')
            ->where('log.activities.0.details.1.label', 'Panjang (m)')
            ->where('log.activities.0.details.1.value', '35.000')
            ->missing('log.activities.0.obstacle_status')
            ->missing('log.activities.0.obstacles')
            ->missing('log.activities.0.proposed_solution')
            ->missing('log.activities.0.action_taken'));
        $this->post($url, $this->payload('2026-10-28'))->assertStatus(405);
        $this->post('/kebun/'.$this->farm->id.'/daily-logs', $this->payload('2026-10-28'))->assertForbidden();
        $this->postJson('/api/kebun/'.$this->farm->id.'/daily-logs', $this->payload('2026-10-28'))->assertForbidden();
        $this->patch('/kebun/'.$this->farm->id.'/daily-logs/'.$log->id, [])->assertStatus(405);
        foreach (['create', 'delete'] as $ability) {
            $subject = $ability === 'create' ? [DailyActivityLog::class, $this->farm] : $log;
            $this->assertFalse(Gate::forUser($this->admin)->allows($ability, $subject));
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
            ->where('log.recorder_name', $petugas->name)->where('log.activities.0.worker_count', 6));
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
        $this->assertSame(0, $summary['incidents']);
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
        $this->assertSame(1, $summary['activities']);
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

    private function report(Farm $farm, User $actor, string $date): DailyActivityLog
    {
        $payload = $this->payload($date);
        $payload['recorded_by'] = $actor->id;

        return app(DailyActivityLogService::class)->create($farm, $payload);
    }

    private function incidentReport(Farm $farm, User $actor, string $date): FarmActivity
    {
        $log = DailyActivityLog::create([
            'farm_id' => $farm->id,
            'recorded_by' => $actor->id,
            'work_date' => $date,
        ]);
        $type = ActivityType::query()->where('code', 'incident')->firstOrFail();
        $activity = FarmActivity::create([
            'farm_id' => $farm->id,
            'activity_type_id' => $type->id,
            'performed_by' => $actor->id,
            'activity_date' => $date.' 09:00:00+07:00',
            'daily_activity_log_id' => $log->id,
            'position' => 1,
            'worker_count' => 2,
            'description' => 'Saluran air tersumbat lumpur',
            'obstacle_status' => 'none',
        ]);
        $activity->incidentDetail()->create([
            'incident_category' => 'Banjir',
            'severity' => 'medium',
            'affected_area_ha' => '0.2500',
            'proposed_solution' => 'Pembersihan parit utama',
        ]);

        return $activity;
    }

    private function sopHarvestActivity(Farm $farm, User $actor, string $date): FarmActivity
    {
        $team = $farm->team;
        $commodity = Commodity::create(['name' => 'Jagung']);
        $variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Bisi 18']);
        $block = app(FarmBlockService::class)->create($farm, $this->admin, $team, [
            'code' => 'U1',
            'name' => 'Blok Utama',
            'area_ha' => '4.0000',
        ]);
        $block->forceFill(['commodity_id' => $commodity->id])->save();
        $cycle = app(FarmBlockService::class)->createBatch($block, $this->admin, $team, [
            'reserved_area_ha' => '2.0000',
            'planned_planting_at' => $date,
        ]);
        $cycle->forceFill([
            'subblock_label' => 'SB-01',
            'planted_at' => $date,
            'variety_id' => $variety->id,
            'planted_area_ha' => '2.0000',
            'status' => 'active',
        ])->save();
        $harvestStage = $cycle->stages()->where('code', 'harvest')->firstOrFail();
        $task = PlantingCycleTask::create([
            'planting_cycle_id' => $cycle->id,
            'planting_cycle_stage_id' => $harvestStage->id,
            'name' => 'Panen Raya',
            'activity_category' => 'harvest',
            'status' => 'completed',
            'offset_days' => 60,
            'tolerance_days' => 5,
            'scheduled_date' => $date,
            'due_date' => $date,
            'is_required' => true,
        ]);
        $log = DailyActivityLog::create([
            'farm_id' => $farm->id,
            'recorded_by' => $actor->id,
            'work_date' => $date,
        ]);
        $type = ActivityType::query()->where('code', 'harvest')->firstOrFail();
        $activity = new FarmActivity([
            'farm_id' => $farm->id,
            'planting_cycle_id' => $cycle->id,
            'activity_type_id' => $type->id,
            'performed_by' => $actor->id,
            'activity_date' => $date.' 10:00:00+07:00',
            'daily_activity_log_id' => $log->id,
            'position' => 1,
            'worker_count' => 5,
            'description' => 'Panen pagi kualitas baik.',
            'obstacle_status' => 'none',
        ]);
        $activity->forceFill([
            'block_id' => $block->id,
            'planting_cycle_task_id' => $task->id,
            'task_details' => json_encode(['area_ha' => '1.2500', 'weight_ton' => '6.500'], JSON_THROW_ON_ERROR),
        ])->save();
        $activity->harvestDetail()->create([
            'harvested_area_ha' => '1.2500',
            'gross_weight_ton' => '6.500',
        ]);

        return $activity;
    }

    private function payload(string $date): array
    {
        return ['work_date' => $date, 'worker_count' => 6,
            'activities' => [['type' => 'block_mapping', 'obstacle_status' => 'none',
                'block_mapping' => ['block_name' => 'Blok Utara', 'length_m' => 35, 'width_m' => 50]]]];
    }
}
