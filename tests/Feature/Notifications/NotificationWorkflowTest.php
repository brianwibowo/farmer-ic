<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendWhatsAppNotification;
use App\Models\ActivityType;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Channels\InAppChannel;
use App\Notifications\SopDeviationNotification;
use App\Services\Notifications\FonnteClient;
use App\Services\Notifications\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesNotificationTasks;
use Tests\TestCase;

/** Integrated PostgreSQL workflow, recipient boundaries and queue revalidation. */
class NotificationWorkflowTest extends TestCase
{
    use CreatesNotificationTasks, RefreshDatabase;

    private Team $team;

    private Farm $farm;

    private User $admin;

    private User $owner;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-15 20:00:00', 'Asia/Jakarta'));
        config(['inertia.ssr.enabled' => false, 'operational_notifications.enabled' => true,
            'operational_notifications.connection' => 'database', 'operational_notifications.fonnte.enabled' => true,
            'operational_notifications.fonnte.token' => 'fake-token']);
        Http::preventStrayRequests();
        Bus::fake();
        $this->team = Team::factory()->create(['is_personal' => false]);
        $this->admin = $this->actor('admin');
        $this->owner = $this->actor('owner');
        $this->officer = $this->actor('petugas');
        $this->farm = $this->makeFarm('Kebun Cianjur');
        $this->officer->forceFill(['assigned_farm_id' => $this->farm->id])->save();
    }

    private function actor(string $role, ?Team $team = null): User
    {
        $user = User::factory()->create(['role' => $role, 'phone_number' => '6281234567890']);
        ($team ?? $this->team)->members()->attach($user, ['role' => 'member']);

        return $user;
    }

    private function makeFarm(string $name, ?Team $team = null): Farm
    {
        $farm = new Farm(['name' => $name, 'total_area_ha' => '100.0000']);
        $farm->forceFill(['team_id' => ($team ?? $this->team)->id, 'created_by' => $this->admin->id])->save();

        return $farm;
    }

    private function officerJob(): SendWhatsAppNotification
    {
        return Bus::dispatched(SendWhatsAppNotification::class)->first(fn ($job) => $job->notification->data['user_id'] === $this->officer->id);
    }

    private function fakeProvider(): void
    {
        Http::fake([
            'api.fonnte.com/validate' => Http::response(['status' => true, 'registered' => ['6281234567890'], 'not_registered' => []]),
            'api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['accepted']]),
        ]);
    }

    public function test_weekly_admin_is_per_farm_and_owner_is_per_region(): void
    {
        $this->makeFarm('Kebun Bogor');
        $this->makeFarm('Kebun Sukabumi');
        $this->actor('superadmin');
        $this->assertSame(2, app(NotificationService::class)->weeklyReports());
        $jobs = Bus::dispatched(SendWhatsAppNotification::class);
        $this->assertCount(4, $jobs);
        $this->assertCount(3, $jobs->filter(fn ($job) => $job->notification->data['audience'] === 'admin'));
        $ownerJob = $jobs->first(fn ($job) => $job->notification->data['audience'] === 'owner');
        $this->assertCount(3, $ownerJob->notification->data['farms']);
        $this->fakeProvider();
        foreach ($jobs as $job) {
            $job->handle(app(NotificationService::class), app(FonnteClient::class));
        }
        Http::assertSentCount(8);
        $queued = Bus::dispatched(\Illuminate\Notifications\SendQueuedNotifications::class)
            ->first(fn ($job) => $job->notification->data['audience'] === 'owner')->notification;
        app(InAppChannel::class)->send($this->owner, $queued);
        app(InAppChannel::class)->send($this->owner, $queued);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->owner->id, 'type' => 'weekly_report', 'team_id' => null]);
    }

    public function test_weekly_is_sent_without_activity_or_harvest_but_not_without_farms(): void
    {
        $emptyTeam = Team::factory()->create(['is_personal' => false]);
        $emptyOwner = $this->actor('owner', $emptyTeam);
        $this->assertSame(2, app(NotificationService::class)->weeklyReports());
        Bus::assertNotDispatched(SendWhatsAppNotification::class, fn ($job) => $job->notification->data['user_id'] === $emptyOwner->id);
        foreach (Bus::dispatched(SendWhatsAppNotification::class) as $job) {
            $expected = $job->notification->data['audience'] === 'owner'
                ? 'Tidak ada panen yang terjadi minggu ini.' : 'Belum ada aktivitas yang dilaporkan Petugas minggu ini.';
            $this->assertStringContainsString($expected, $job->notification->toInApp()['message']);
        }
    }

    public function test_admin_weekly_uses_only_petugas_activity_inside_the_week_and_rolls_up_blocks(): void
    {
        $first = $this->notificationTask($this->farm);
        $block = FarmBlock::findOrFail($first->plantingCycle->block_id);
        $this->notificationTask($this->farm, 1, [], $block);
        $type = ActivityType::firstOrCreate(['code' => 'block_mapping'], ['name' => 'Pemetaan Blok']);
        foreach (['2026-11-08 23:59:59', '2026-11-09 00:00:00', '2026-11-15 20:00:00', '2026-11-15 20:00:01'] as $at) {
            $activity = FarmActivity::create(['farm_id' => $this->farm->id, 'block_id' => $block->id, 'activity_type_id' => $type->id,
                'performed_by' => $this->officer->id, 'activity_date' => CarbonImmutable::parse($at, 'Asia/Jakarta'), 'description' => 'Memeriksa kebun']);
            $activity->forceFill(['block_id' => $block->id])->save();
            $activity->blockMappingDetail()->create(['block_name' => $block->getAttribute('name'), 'length_m' => 10, 'width_m' => 10]);
        }
        $activity = FarmActivity::create(['farm_id' => $this->farm->id, 'activity_type_id' => $type->id,
            'performed_by' => $this->admin->id, 'activity_date' => now(), 'description' => 'Catatan Admin']);
        $activity->blockMappingDetail()->create(['block_name' => 'Blok Admin', 'length_m' => 10, 'width_m' => 10]);
        app(NotificationService::class)->weeklyReports();
        $notification = Bus::dispatched(SendWhatsAppNotification::class)
            ->first(fn ($job) => $job->notification->data['audience'] === 'admin')->notification;
        $report = $notification->data['farms'][0];
        $this->assertSame('2026-11-09', $notification->data['period_start']);
        $this->assertSame('2026-11-15', $notification->data['period_end']);
        $this->assertSame(2, $report['activity_count']);
        $this->assertCount(1, $report['blocks']);
        $this->assertSame(['Pemetaan Blok' => 2], $report['blocks'][0]['activity_counts']);
        $this->assertStringNotContainsString('Subblok', $notification->toInApp()['message']);
        $this->assertStringNotContainsString('Catatan Admin', $notification->toInApp()['message']);
    }

    public function test_owner_combines_tenants_and_never_includes_foreign_farms(): void
    {
        $second = Team::factory()->create(['is_personal' => false]);
        $second->members()->attach($this->owner, ['role' => 'member']);
        $this->makeFarm('Kebun Bandung', $second);
        $foreign = Team::factory()->create(['is_personal' => false]);
        $this->makeFarm('Kebun Rahasia', $foreign);
        $this->assertSame(2, app(NotificationService::class)->weeklyReports());
        $job = Bus::dispatched(SendWhatsAppNotification::class)->first(fn ($job) => $job->notification->data['audience'] === 'owner');
        $this->assertEqualsCanonicalizing([$this->team->id, $second->id], $job->notification->data['team_ids']);
        $this->assertCount(2, $job->notification->data['farms']);
    }

    public function test_reminders_include_only_h3_h1_h0_and_only_the_assigned_officer(): void
    {
        foreach ([3, 1, 0, 2, 7, -1] as $days) {
            $this->notificationTask($this->farm, $days);
        }
        $this->notificationTask($this->farm, 0, ['status' => 'completed', 'completed_at' => now()]);
        $this->notificationTask($this->farm, 0, ['status' => 'skipped', 'skip_reason' => 'Tidak diperlukan']);
        $otherFarm = $this->makeFarm('Kebun Bogor');
        $otherTask = $this->notificationTask($otherFarm);
        $foreign = Team::factory()->create(['is_personal' => false]);
        $foreignTask = $this->notificationTask($this->makeFarm('Kebun Rahasia', $foreign));
        $this->assertSame(1, app(NotificationService::class)->taskReminders());
        $jobs = Bus::dispatched(SendWhatsAppNotification::class);
        $this->assertSame([$this->officer->id], $jobs->map(fn ($job) => $job->notification->data['user_id'])->all());
        $this->assertCount(3, $this->officerJob()->notification->data['tasks']);
        $this->assertEqualsCanonicalizing([0, 1, 3], array_column($this->officerJob()->notification->data['tasks'], 'days_before'));
        $this->assertNotContains($otherTask->id, array_column($this->officerJob()->notification->data['tasks'], 'id'));
        $this->assertNotContains($foreignTask->id, array_column($this->officerJob()->notification->data['tasks'], 'id'));
        Bus::assertNotDispatched(\Illuminate\Notifications\SendQueuedNotifications::class,
            fn ($job) => $job->notification->data['user_id'] === $this->admin->id);
    }

    public function test_already_queued_admin_reminders_are_rejected_by_both_channels(): void
    {
        $this->notificationTask($this->farm);
        app(NotificationService::class)->taskReminders();
        $data = [...$this->officerJob()->notification->data, 'user_id' => $this->admin->id];
        $notification = new \App\Notifications\TaskReminderNotification($data);
        app(InAppChannel::class)->send($this->admin, $notification);
        (new SendWhatsAppNotification($notification))->handle(app(NotificationService::class), app(FonnteClient::class));
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($this->admin)->allows('notifications.taskReminder', $this->team));
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($this->admin)->allows('notifications.sopDeviation', $this->team));
    }

    public function test_empty_reminders_and_deviations_queue_nothing(): void
    {
        $this->assertSame(0, app(NotificationService::class)->taskReminders());
        $this->assertSame(0, app(NotificationService::class)->sopDeviations());
        Bus::assertNothingDispatched();
    }

    public function test_cancelled_and_unplanted_cycles_produce_no_task_notifications(): void
    {
        $task = $this->notificationTask($this->farm);
        $task->plantingCycle->update(['status' => 'cancelled']);
        $this->assertSame(0, app(NotificationService::class)->taskReminders());
        $task->plantingCycle->update(['status' => 'active', 'planted_at' => now()->addDay()->toDateString()]);
        $this->assertSame(0, app(NotificationService::class)->taskReminders());
    }

    public function test_reschedule_moves_reminders_and_tolerance_without_violating_the_old_limit(): void
    {
        $task = $this->notificationTask($this->farm, -10);
        app(\App\Services\PlantingCycleTaskManagementService::class)->reschedule($task, $this->admin, [
            'rescheduled_date' => now()->addDay()->toDateString(), 'reschedule_reason' => 'Menunggu kondisi cuaca',
        ]);
        $this->assertSame(1, app(NotificationService::class)->taskReminders());
        $this->assertSame(1, $this->officerJob()->notification->data['tasks'][0]['days_before']);
        $this->assertSame(0, app(NotificationService::class)->sopDeviations());
    }

    public function test_deviation_is_admin_only_strictly_after_dynamic_tolerance_and_repeats_daily(): void
    {
        $task = $this->notificationTask($this->farm, -20, [
            'rescheduled_date' => now()->subDays(2)->toDateString(), 'due_date' => now()->toDateString(),
        ]);
        $this->assertSame(0, app(NotificationService::class)->sopDeviations());
        $this->travel(1)->days();
        $this->assertSame(1, app(NotificationService::class)->sopDeviations());
        $job = Bus::dispatched(SendWhatsAppNotification::class)->first();
        $this->assertInstanceOf(SopDeviationNotification::class, $job->notification);
        $this->assertSame($this->admin->id, $job->notification->data['user_id']);
        $this->assertSame($task->id, $job->notification->data['tasks'][0]['id']);
        app(InAppChannel::class)->send($this->admin, $job->notification);
        app(InAppChannel::class)->send($this->admin, $job->notification);
        $this->assertDatabaseCount('notifications', 1);
        $this->travel(1)->days();
        $this->assertSame(1, app(NotificationService::class)->sopDeviations());
        $next = Bus::dispatched(SendWhatsAppNotification::class)->last();
        app(InAppChannel::class)->send($this->admin, $next->notification);
        $this->assertDatabaseCount('notifications', 2);
        $task->update(['status' => 'completed', 'completed_at' => now()]);
        $this->assertSame(0, app(NotificationService::class)->sopDeviations());
    }

    public function test_zero_tolerance_reports_only_from_the_following_day(): void
    {
        $this->notificationTask($this->farm, 0, ['tolerance_days' => 0, 'due_date' => today()]);
        $this->assertSame(0, app(NotificationService::class)->sopDeviations());
        $this->travel(1)->days();
        $this->assertSame(1, app(NotificationService::class)->sopDeviations());
    }

    public function test_changed_task_is_removed_without_suppressing_other_tasks_in_the_bundle(): void
    {
        $completed = $this->notificationTask($this->farm, 0, ['name' => 'Selesai sebelum dikirim']);
        $this->notificationTask($this->farm, 0, ['name' => 'Masih perlu dikerjakan']);
        app(NotificationService::class)->taskReminders();
        $completed->update(['status' => 'completed', 'completed_at' => now()]);
        $job = $this->officerJob();
        app(InAppChannel::class)->send($this->officer, $job->notification);
        $notice = $this->officer->notifications()->firstOrFail();
        $this->assertStringContainsString('Masih perlu dikerjakan', $notice->message);
        $this->assertStringNotContainsString('Selesai sebelum dikirim', $notice->message);
        $this->assertSame('task_reminder', $notice->content['kind']);
        $names = collect($notice->content['groups'])->flatMap(fn ($group) => array_column($group['items'], 'name'))->all();
        $this->assertSame(['Masih perlu dikerjakan'], $names);
    }

    public function test_reschedule_during_whatsapp_validation_cancels_the_send(): void
    {
        $task = $this->notificationTask($this->farm);
        app(NotificationService::class)->taskReminders();
        Http::fake(function () use ($task) {
            $task->update(['rescheduled_date' => now()->addDays(7)->toDateString(), 'due_date' => now()->addDays(9)->toDateString()]);

            return Http::response(['status' => true, 'registered' => ['6281234567890'], 'not_registered' => []]);
        });
        $this->officerJob()->handle(app(NotificationService::class), app(FonnteClient::class));
        Http::assertSentCount(1);
        app(InAppChannel::class)->send($this->officer, $this->officerJob()->notification);
        $this->assertDatabaseCount('notifications', 0);
    }

    public static function accessChanges(): array
    {
        return [['membership'], ['assignment'], ['inactive'], ['role'], ['admin'], ['superadmin']];
    }

    #[DataProvider('accessChanges')]
    public function test_access_changes_during_whatsapp_validation_prevent_sending(string $change): void
    {
        $this->notificationTask($this->farm);
        app(NotificationService::class)->taskReminders();
        Http::fake(function () use ($change) {
            match ($change) {
                'membership' => $this->team->members()->detach($this->officer),
                'assignment' => $this->officer->forceFill(['assigned_farm_id' => null])->save(),
                'inactive' => $this->officer->forceFill(['is_active' => false])->save(),
                // Owner is not a task-reminder recipient.
                'role' => $this->officer->forceFill(['role_id' => \App\Models\Role::firstOrCreate(['name' => 'owner'])->id])->save(),
                'admin' => $this->officer->forceFill(['role_id' => \App\Models\Role::firstOrCreate(['name' => 'admin'])->id])->save(),
                'superadmin' => $this->officer->forceFill(['role_id' => \App\Models\Role::firstOrCreate(['name' => 'superadmin'])->id])->save(),
            };

            return Http::response(['status' => true, 'registered' => ['6281234567890'], 'not_registered' => []]);
        });
        $this->officerJob()->handle(app(NotificationService::class), app(FonnteClient::class));
        Http::assertSentCount(1);
    }

    public function test_foreign_farm_and_revoked_membership_invalidate_weekly_payloads(): void
    {
        app(NotificationService::class)->weeklyReports();
        $job = Bus::dispatched(SendWhatsAppNotification::class)->first(fn ($job) => $job->notification->data['audience'] === 'owner');
        $this->farm->forceFill(['team_id' => Team::factory()->create(['is_personal' => false])->id])->save();
        app(InAppChannel::class)->send($this->owner, $job->notification);
        $this->assertDatabaseCount('notifications', 0);
        $this->farm->forceFill(['team_id' => $this->team->id])->save();
        $this->team->members()->detach($this->owner);
        $job->handle(app(NotificationService::class), app(FonnteClient::class));
        Http::assertNothingSent();
    }

    public function test_disabled_whatsapp_and_expired_payload_do_not_contact_provider(): void
    {
        $this->notificationTask($this->farm);
        app(NotificationService::class)->taskReminders();
        config(['operational_notifications.fonnte.enabled' => false]);
        $this->officerJob()->handle(app(NotificationService::class), app(FonnteClient::class));
        config(['operational_notifications.fonnte.enabled' => true]);
        $this->travelTo(now()->endOfDay());
        $this->officerJob()->handle(app(NotificationService::class), app(FonnteClient::class));
        $this->travel(1)->days();
        app(InAppChannel::class)->send($this->officer, $this->officerJob()->notification);
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
    }

    public function test_new_types_are_visible_in_inbox_and_cannot_be_read_by_other_users(): void
    {
        foreach (['weekly_report', 'task_reminder', 'sop_deviation', 'daily_report', 'harvest_reminder'] as $type) {
            $notice = new \App\Models\Notification;
            $notice->forceFill(['user_id' => $this->admin->id, 'team_id' => $this->team->id, 'type' => $type, 'title' => 'Judul bebas'])->save();
        }
        $this->actingAs($this->admin)->getJson('/notifications/unread')->assertOk()->assertJson(['count' => 5]);
        $this->get('/notifications')->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->has('notifications.data', 5));
        $notice = $this->admin->notifications()->firstOrFail();
        $this->actingAs($this->owner)->patch('/notifications/'.$notice->id.'/read')->assertForbidden();
    }

    public function test_schedules_use_sunday_evening_and_daily_0600_wib(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());
        $this->assertSame('0 20 * * 0', $events->first(fn ($event) => str_contains($event->command ?? '', 'weekly-report'))->expression);
        foreach (['task-reminder', 'sop-deviation'] as $kind) {
            $event = $events->first(fn ($event) => str_contains($event->command ?? '', $kind));
            $this->assertSame('0 6 * * *', $event->expression);
            $this->assertSame('Asia/Jakarta', $event->timezone);
        }
        $this->artisan('notifications:dispatch', ['kind' => 'daily-report'])->assertExitCode(2);
    }

    public function test_missing_grants_and_master_switch_prevent_dispatch(): void
    {
        $this->notificationTask($this->farm);
        foreach (['owner', 'admin', 'petugas'] as $role) {
            $grants = config('access.roles.'.$role);
            unset($grants['planting_cycle_tasks.view'], $grants['farm_activities.view']);
            config(['access.roles.'.$role => $grants]);
        }
        $this->assertSame(0, app(NotificationService::class)->weeklyReports());
        $this->assertSame(0, app(NotificationService::class)->taskReminders());
        config(['operational_notifications.enabled' => false]);
        $this->assertSame(0, app(NotificationService::class)->sopDeviations());
        Bus::assertNothingDispatched();
    }

    public function test_type_migration_classifies_old_operational_rows_without_changing_their_content(): void
    {
        $daily = $this->admin->notifications()->create(['title' => 'Report Harian Petugas', 'message' => 'Rekap tersimpan.']);
        $harvest = $this->officer->notifications()->create(['title' => 'Panen Reminder H-3', 'message' => 'Jadwal tersimpan.']);
        $other = $this->admin->notifications()->create(['title' => 'Pemberitahuan lama lainnya']);
        $migration = require database_path('migrations/2026_10_31_000002_add_type_to_notifications.php');
        $migration->down();
        $migration->up();
        $this->assertSame('daily_report', $daily->fresh()->type);
        $this->assertSame('Rekap tersimpan.', $daily->fresh()->message);
        $this->assertSame('harvest_reminder', $harvest->fresh()->type);
        $this->assertSame('Jadwal tersimpan.', $harvest->fresh()->message);
        $this->assertNull($other->fresh()->type);
    }

    public function test_phone_number_migration_preserves_saved_numbers_on_rollback(): void
    {
        $migration = require database_path('migrations/2026_10_31_000001_add_phone_number_to_users.php');
        try {
            $migration->down();
            $this->fail('Migration must preserve saved user phone numbers.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('Refusing to discard saved user phone numbers', $exception->getMessage());
            $this->assertTrue(Schema::hasColumn('users', 'phone_number'));
        }
    }

    public static function phones(): array
    {
        return [['+62081212345678'], ['081212345678'], ['6281212345678'], ['+62 812-1234-5678']];
    }

    #[DataProvider('phones')]
    public function test_phone_number_is_normalized_in_own_profile(string $input): void
    {
        $this->actingAs($this->officer)->patch('/settings/profile', ['name' => $this->officer->name,
            'email' => $this->officer->email, 'phone_number' => $input])->assertSessionHasNoErrors();
        $this->assertSame('6281212345678', $this->officer->fresh()->phone_number);
    }

    public function test_invalid_phone_number_is_rejected_and_blank_clears_it(): void
    {
        $this->actingAs($this->officer)->patch('/settings/profile', ['name' => $this->officer->name,
            'email' => $this->officer->email, 'phone_number' => 'invalid'])->assertSessionHasErrors('phone_number');
        $this->patch('/settings/profile', ['name' => $this->officer->name,
            'email' => $this->officer->email, 'phone_number' => ''])->assertSessionHasNoErrors();
        $this->assertNull($this->officer->fresh()->phone_number);
    }

    public function test_admin_can_save_a_normalized_phone_through_user_management(): void
    {
        $this->actingAs($this->admin)->post('/admin/user-management', [
            'name' => 'Petugas Penerima Notifikasi', 'email' => 'notif-officer@example.test',
            'phone_number' => '+62081212345678', 'role' => 'petugas',
            'password' => 'notification-password', 'password_confirmation' => 'notification-password',
            'team_ids' => [$this->team->id], 'assigned_farm_id' => $this->farm->id,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'notif-officer@example.test', 'phone_number' => '6281212345678']);
    }
}
