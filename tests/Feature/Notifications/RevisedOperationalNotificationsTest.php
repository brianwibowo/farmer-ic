<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendWhatsAppNotification;
use App\Models\ActivityType;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Channels\InAppChannel;
use App\Services\Notifications\FonnteClient;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesNotificationTasks;
use Tests\TestCase;

class RevisedOperationalNotificationsTest extends TestCase
{
    use CreatesNotificationTasks, RefreshDatabase;

    private User $admin;

    private User $officer;

    private Farm $farm;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-16 06:00', 'Asia/Jakarta'));
        config(['operational_notifications.enabled' => true, 'operational_notifications.fonnte.enabled' => true,
            'operational_notifications.fonnte.token' => 'fake', 'operational_notifications.connection' => 'database', 'inertia.ssr.enabled' => false]);
        Bus::fake();
        Http::preventStrayRequests();
        $this->team = Team::factory()->create(['is_personal' => false]);
        $this->admin = User::factory()->create(['role' => 'admin', 'phone_number' => '6281234567890']);
        $this->officer = User::factory()->create(['role' => 'petugas', 'phone_number' => '6281234567890']);
        $this->team->members()->attach([$this->admin->id, $this->officer->id], ['role' => 'member']);
        $this->farm = new Farm(['name' => 'Lahan Cianjur', 'total_area_ha' => 100]);
        $this->farm->forceFill(['team_id' => $this->team->id, 'created_by' => $this->admin->id])->save();
        $this->officer->forceFill(['assigned_farm_id' => $this->farm->id])->save();
    }

    public function test_only_harvest_is_reminded_to_both_roles_once_per_milestone_despite_rescheduling(): void
    {
        $task = $this->notificationTask($this->farm, 3, ['activity_category' => 'harvest', 'activity_subcategory' => null, 'name' => 'Panen']);
        $this->notificationTask($this->farm, 3, ['activity_category' => 'maintenance', 'activity_subcategory' => 'irrigation']);
        $service = app(NotificationService::class);
        $this->assertSame(2, $service->taskReminders());
        $this->assertSame(0, $service->taskReminders());
        $this->assertDatabaseCount('operational_notification_events', 2);
        foreach (Bus::dispatched(SendWhatsAppNotification::class) as $job) {
            $this->assertSame([$task->id], array_column($job->notification->data['tasks'], 'id'));
            $subblocks = $job->notification->toInboxContent()['groups'][0]['items'][0]['subblocks'];
            $this->assertSame($task->planting_cycle_id, $subblocks[0]['id']);
            $this->assertSame($task->plantingCycle->subblock_label, $subblocks[0]['name']);
        }
        $this->travel(2)->days();
        $this->assertSame(2, $service->taskReminders());
        $task->forceFill(['rescheduled_date' => now()->addDays(3)])->save();
        $this->assertSame(0, $service->taskReminders(), 'H-3 must not repeat after a reschedule.');
    }

    public function test_both_sop_conditions_are_distinct_once_only_events(): void
    {
        $task = $this->notificationTask($this->farm, -10, ['rescheduled_date' => now()->addDay()->toDateString()]);
        $service = app(NotificationService::class);
        $this->assertSame(1, $service->sopDeviations());
        $this->assertSame(0, $service->sopDeviations());
        $this->travel(4)->days();
        $this->assertSame(1, $service->sopDeviations());
        $this->assertSame(0, $service->sopDeviations());
        $rows = Bus::dispatched(SendWhatsAppNotification::class)->map(fn ($job) => $job->notification->data['tasks'][0]);
        $this->assertSame([$task->id.':reschedule_limit', $task->id.':unfinished_limit'], $rows->pluck('event_key')->all());
    }

    private function incident(Farm $farm, User $reporter): FarmActivity
    {
        return DB::transaction(function () use ($farm, $reporter) {
            $type = ActivityType::firstOrCreate(['code' => 'incident'], ['name' => 'Insiden']);
            $activity = FarmActivity::create(['farm_id' => $farm->id, 'activity_type_id' => $type->id,
                'performed_by' => $reporter->id, 'activity_date' => now(), 'description' => 'Pompa rusak']);
            $activity->incidentDetail()->create(['incident_category' => 'Alat', 'severity' => 'light']);

            return $activity;
        });
    }

    public function test_incidents_are_batched_once_and_only_from_scoped_petugas(): void
    {
        $first = $this->incident($this->farm, $this->officer);
        $this->incident($this->farm, $this->admin);
        $service = app(NotificationService::class);
        $this->assertSame(1, $service->incidentReports());
        $this->assertSame(0, $service->incidentReports());
        $notification = Bus::dispatched(SendWhatsAppNotification::class)->first()->notification;
        $this->assertSame([$first->id], array_column($notification->data['incidents'], 'id'));
        (new InAppChannel)->send($this->admin, $notification);
        $this->assertDatabaseHas('notifications', ['type' => 'incident_report', 'user_id' => $this->admin->id]);
        $this->actingAs($this->admin)->get('/notifications')->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('notifications.data.0.content.kind', 'incident_report'));
        $this->travel(30)->minutes();
        $this->incident($this->farm, $this->officer);
        $this->assertSame(1, $service->incidentReports());
        $this->team->members()->detach($this->admin);
        $this->assertNull($service->validRecipient($notification->data));
    }

    public function test_whatsapp_is_a_single_durable_prompt_and_inbox_is_present_first(): void
    {
        $this->incident($this->farm, $this->officer);
        $service = app(NotificationService::class);
        $service->incidentReports();
        Http::fake([
            'api.fonnte.com/validate' => Http::response(['status' => true, 'registered' => ['6281234567890'], 'not_registered' => []]),
            'api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['accepted']]),
        ]);
        $job = Bus::dispatched(SendWhatsAppNotification::class)->first();
        $job->handle($service, app(FonnteClient::class));
        $job->handle($service, app(FonnteClient::class));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/send')
            && str_contains($request['message'], '/notifications') && ! str_contains($request['message'], 'Pompa rusak')
            && ! str_contains($request['message'], 'Cianjur'));
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('operational_notification_deliveries', ['whatsapp_state' => 'accepted']);
    }

    public function test_incident_dispatch_query_count_does_not_grow_per_report(): void
    {
        $this->incident($this->farm, $this->officer);
        $service = app(NotificationService::class);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $service->incidentReports();
        $one = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 29; $i++) {
            $this->incident($this->farm, $this->officer);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $service->incidentReports();
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($one, $many);
    }

    public function test_admin_weekly_contains_actual_harvest_weight_but_whatsapp_does_not(): void
    {
        $task = $this->notificationTask($this->farm);
        DB::transaction(function () use ($task): void {
            $type = ActivityType::firstOrCreate(['code' => 'harvest'], ['name' => 'Panen']);
            $activity = FarmActivity::create(['farm_id' => $this->farm->id, 'planting_cycle_id' => $task->planting_cycle_id,
                'activity_type_id' => $type->id, 'performed_by' => $this->officer->id, 'activity_date' => now()]);
            $activity->harvestDetail()->create(['gross_weight_ton' => 2.5, 'harvested_area_ha' => 0.01]);
        });
        app(NotificationService::class)->weeklyReports();
        $notification = Bus::dispatched(SendWhatsAppNotification::class)->first()->notification;
        $block = $notification->toInApp()['content']['farms'][0]['blocks'][0];
        $this->assertSame(2.5, $block['harvest_ton']);
        $this->assertSame(1, $block['harvest_count']);
        $this->assertStringNotContainsString('2,5', $notification->toWhatsAppText());
        $this->assertStringContainsString('/notifications', $notification->toWhatsAppText());
    }

    public function test_uncertain_send_is_not_automatically_retried(): void
    {
        $this->incident($this->farm, $this->officer);
        $service = app(NotificationService::class);
        $service->incidentReports();
        Http::fake([
            'api.fonnte.com/validate' => Http::response(['status' => true, 'registered' => ['6281234567890'], 'not_registered' => []]),
            'api.fonnte.com/send' => Http::response('unknown', 502),
        ]);
        $job = Bus::dispatched(SendWhatsAppNotification::class)->first();
        $job->handle($service, app(FonnteClient::class));
        $job->handle($service, app(FonnteClient::class));
        Http::assertSentCount(2);
        $this->assertDatabaseHas('operational_notification_deliveries', ['whatsapp_state' => 'failed']);
    }

    public function test_validation_failure_of_a_duplicate_job_cannot_reset_another_jobs_acceptance(): void
    {
        $this->incident($this->farm, $this->officer);
        $service = app(NotificationService::class);
        $service->incidentReports();
        Http::fake(function () {
            DB::table('operational_notification_deliveries')->update(['whatsapp_state' => 'accepted']);

            return Http::response(['status' => false, 'reason' => 'device disconnected']);
        });
        $job = Bus::dispatched(SendWhatsAppNotification::class)->first();
        $job->handle($service, app(FonnteClient::class));
        $job->handle($service, app(FonnteClient::class));
        Http::assertSentCount(1);
        $this->assertDatabaseHas('operational_notification_deliveries', ['whatsapp_state' => 'accepted']);
    }
}
