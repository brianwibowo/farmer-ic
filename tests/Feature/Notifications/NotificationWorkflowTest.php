<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendWhatsAppNotification;
use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Channels\InAppChannel;
use App\Notifications\DailyReportNotification;
use App\Notifications\HarvestReminderNotification;
use App\Services\Notifications\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Farm $farm;

    private User $admin;

    private User $owner;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-30 20:00:00', 'Asia/Jakarta'));
        config(['inertia.ssr.enabled' => false, 'operational_notifications.enabled' => true,
            'operational_notifications.connection' => 'database', 'operational_notifications.fonnte.enabled' => true,
            'operational_notifications.fonnte.token' => 'fake-token']);
        Http::preventStrayRequests();
        Bus::fake();
        $this->team = Team::factory()->create(['is_personal' => false]);
        $this->admin = $this->actor('admin');
        $this->owner = $this->actor('owner');
        $this->officer = $this->actor('petugas');
        $this->farm = new Farm(['name' => 'Kebun Cianjur', 'total_area_ha' => '5.0000']);
        $this->farm->forceFill(['team_id' => $this->team->id, 'created_by' => $this->admin->id])->save();
        $this->officer->forceFill(['assigned_farm_id' => $this->farm->id])->save();
    }

    private function actor(string $role, ?Team $team = null): User
    {
        $user = User::factory()->create(['role' => $role, 'phone_number' => '6281234567890']);
        ($team ?? $this->team)->members()->attach($user, ['role' => 'member']);

        return $user;
    }

    private function cycle(string $date = '2026-11-06', string $status = 'active'): PlantingCycle
    {
        $commodity = Commodity::firstOrCreate(['name' => 'Jagung Notifikasi']);
        $variety = CropVariety::firstOrCreate(['commodity_id' => $commodity->id, 'name' => 'Bisi Notifikasi']);

        return PlantingCycle::create(['farm_id' => $this->farm->id, 'variety_id' => $variety->id,
            'planted_at' => '2026-09-01', 'estimated_harvest_at' => $date, 'planted_area_ha' => '1.0000', 'status' => $status]);
    }

    private function activity(User $performer, string $description, ?Farm $farm = null): void
    {
        $type = ActivityType::firstOrCreate(['code' => 'block_mapping'], ['name' => 'Pemetaan Blok']);
        $activity = FarmActivity::create(['farm_id' => ($farm ?? $this->farm)->id, 'activity_type_id' => $type->id,
            'performed_by' => $performer->id, 'activity_date' => now(), 'description' => $description]);
        $activity->blockMappingDetail()->create(['block_name' => 'Blok A', 'length_m' => 10, 'width_m' => 10]);
    }

    private function reminderData(): array
    {
        $this->cycle();
        $this->assertSame(1, app(NotificationService::class)->harvestReminders());
        $job = Bus::dispatched(SendWhatsAppNotification::class)->first();

        return $job->notification->data;
    }

    public function test_daily_report_targets_active_tenant_admins_and_owners_and_combines_petugas_logs(): void
    {
        $other = $this->actor('petugas');
        $this->actor('owner', Team::factory()->create(['is_personal' => false]));
        $this->activity($this->officer, 'Menyiram tanaman');
        $this->activity($other, 'Memeriksa daun');
        $this->activity($this->admin, 'Catatan admin');
        $this->assertSame(2, app(NotificationService::class)->dailyReports());
        $jobs = Bus::dispatched(SendWhatsAppNotification::class);
        $this->assertEqualsCanonicalizing([$this->admin->id, $this->owner->id], $jobs->map(fn ($job) => $job->notification->data['user_id'])->all());
        foreach ($jobs as $job) {
            $this->assertInstanceOf(DailyReportNotification::class, $job->notification);
            $this->assertStringContainsString('Menyiram tanaman', $job->notification->toWhatsAppText());
            $this->assertStringContainsString('Memeriksa daun', $job->notification->toInApp()['message']);
            $this->assertStringNotContainsString('Catatan admin', $job->notification->toInApp()['message']);
        }
    }

    private function makeFarm(string $name, ?Team $team = null): Farm
    {
        $farm = new Farm(['name' => $name, 'total_area_ha' => '2.0000']);
        $farm->forceFill(['team_id' => ($team ?? $this->team)->id, 'created_by' => $this->admin->id])->save();

        return $farm;
    }

    public function test_three_farms_are_delivered_as_one_tenant_report_per_admin_and_owner(): void
    {
        $bogor = $this->makeFarm('Kebun Bogor');
        $this->makeFarm('Kebun Sukabumi');
        $other = $this->actor('petugas');
        $other->forceFill(['assigned_farm_id' => $bogor->id])->save();
        $this->activity($this->officer, 'Menyiram kebun Cianjur');
        $this->activity($other, 'Memeriksa daun kebun Bogor', $bogor);
        Http::fake([
            'api.fonnte.com/validate' => Http::response(['status' => true, 'registered' => ['6281234567890'], 'not_registered' => []]),
            'api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['tenant-report']]),
        ]);
        $this->assertSame(2, app(NotificationService::class)->dailyReports());
        $jobs = Bus::dispatched(SendWhatsAppNotification::class);
        $this->assertCount(2, $jobs);
        foreach ($jobs as $job) {
            $data = $job->notification->data;
            $this->assertSame(['Kebun Bogor', 'Kebun Cianjur', 'Kebun Sukabumi'], array_column($data['farms'], 'name'));
            $this->assertSame('Memeriksa daun kebun Bogor', $data['farms'][0]['activities'][0]['description']);
            $this->assertSame('Menyiram kebun Cianjur', $data['farms'][1]['activities'][0]['description']);
            $this->assertSame([], $data['farms'][2]['activities']);
            $this->assertStringContainsString('*3. Kebun: Kebun Sukabumi*', $job->notification->toWhatsAppText());
            $this->assertSame(1, substr_count($job->notification->toWhatsAppText(), 'Petugas belum mengisi aktivitas hari ini'));
            app(InAppChannel::class)->send(User::findOrFail($data['user_id']), $job->notification);
            $job->handle(app(NotificationService::class), app(\App\Services\Notifications\FonnteClient::class));
        }
        $this->assertDatabaseCount('notifications', 2);
        Http::assertSentCount(4);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.fonnte.com/send'
            && str_contains($request['message'], '*1. Kebun: Kebun Bogor*')
            && str_contains($request['message'], '*2. Kebun: Kebun Cianjur*')
            && str_contains($request['message'], '*3. Kebun: Kebun Sukabumi*'));
    }

    public function test_owner_gets_separate_reports_per_tenant_and_admins_never_receive_other_tenants(): void
    {
        $secondTeam = Team::factory()->create(['is_personal' => false]);
        $secondTeam->members()->attach($this->owner, ['role' => 'member']);
        $secondAdmin = $this->actor('admin', $secondTeam);
        $secondOfficer = $this->actor('petugas', $secondTeam);
        $otherFarm = $this->makeFarm('Kebun Tenant Kedua', $secondTeam);
        $this->activity($this->officer, 'Aktivitas tenant pertama');
        $this->activity($secondOfficer, 'Aktivitas tenant kedua', $otherFarm);
        $this->assertSame(4, app(NotificationService::class)->dailyReports());
        $jobs = Bus::dispatched(SendWhatsAppNotification::class);
        $this->assertCount(2, $jobs->filter(fn ($job) => $job->notification->data['user_id'] === $this->owner->id));
        foreach ($jobs as $job) {
            $data = $job->notification->data;
            $isFirst = $data['team_id'] === $this->team->id;
            $this->assertSame([$isFirst ? $this->farm->id : $otherFarm->id], array_column($data['farms'], 'id'));
            $this->assertStringContainsString($isFirst ? 'Aktivitas tenant pertama' : 'Aktivitas tenant kedua', $job->notification->toWhatsAppText());
            $this->assertStringNotContainsString($isFirst ? 'Aktivitas tenant kedua' : 'Aktivitas tenant pertama', $job->notification->toWhatsAppText());
            if ($data['user_id'] === $this->admin->id) {
                $this->assertSame($this->team->id, $data['team_id']);
            }
            if ($data['user_id'] === $secondAdmin->id) {
                $this->assertSame($secondTeam->id, $data['team_id']);
            }
        }
    }

    public function test_repeated_daily_dispatch_keeps_one_in_app_report_per_tenant_recipient_date(): void
    {
        $this->makeFarm('Kebun Bogor');
        $this->assertSame(2, app(NotificationService::class)->dailyReports());
        $this->assertSame(2, app(NotificationService::class)->dailyReports());
        $jobs = Bus::dispatched(SendWhatsAppNotification::class);
        $this->assertCount(4, $jobs);
        foreach ($jobs as $job) {
            app(InAppChannel::class)->send(User::findOrFail($job->notification->data['user_id']), $job->notification);
        }
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_moving_a_snapshot_farm_to_another_tenant_prevents_both_channel_effects(): void
    {
        $farm = $this->makeFarm('Kebun Bogor');
        app(NotificationService::class)->dailyReports();
        $notification = Bus::dispatched(SendWhatsAppNotification::class)->first()->notification;
        $team = Team::factory()->create(['is_personal' => false]);
        $farm->forceFill(['team_id' => $team->id])->save();
        $this->assertNull(app(NotificationService::class)->validRecipient($notification->data));
        app(InAppChannel::class)->send(User::findOrFail($notification->data['user_id']), $notification);
        (new SendWhatsAppNotification($notification))->handle(app(NotificationService::class), app(\App\Services\Notifications\FonnteClient::class));
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
    }

    public function test_revoked_membership_and_old_per_farm_payload_do_not_send_a_daily_report(): void
    {
        app(NotificationService::class)->dailyReports();
        $notification = Bus::dispatched(SendWhatsAppNotification::class)->first()->notification;
        $oldData = $notification->data;
        unset($oldData['farms']);
        $oldData['farm_id'] = $this->farm->id;
        $this->assertNull(app(NotificationService::class)->validRecipient($oldData));
        $this->team->members()->detach($notification->data['user_id']);
        app(InAppChannel::class)->send(User::findOrFail($notification->data['user_id']), $notification);
        (new SendWhatsAppNotification($notification))->handle(app(NotificationService::class), app(\App\Services\Notifications\FonnteClient::class));
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
    }

    public function test_tenant_without_visible_farms_gets_no_empty_daily_notification(): void
    {
        $this->actor('admin', Team::factory()->create(['is_personal' => false]));
        $this->assertSame(2, app(NotificationService::class)->dailyReports());
        Bus::assertDispatchedTimes(SendWhatsAppNotification::class, 2);
    }

    public function test_empty_farm_report_has_default_and_duplicate_in_app_job_only_stores_one_row(): void
    {
        app(NotificationService::class)->dailyReports();
        $notification = Bus::dispatched(SendWhatsAppNotification::class)->first()->notification;
        $data = $notification->data;
        $this->assertStringContainsString('Petugas belum mengisi aktivitas hari ini', $notification->toInApp()['message']);
        $recipient = User::findOrFail($data['user_id']);
        $channel = app(InAppChannel::class);
        $channel->send($recipient, $notification);
        $channel->send($recipient, $notification);
        $this->assertDatabaseCount('notifications', 1);
        $this->actingAs($recipient)->getJson('/notifications/unread')->assertOk()->assertJson(['count' => 1]);
        $this->get('/notifications')->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('notifications.data.0.created_at', '30 Oktober 2026 20:00 WIB'));
        $this->patch('/notifications/'.$data['id'].'/read')->assertRedirect();
        $this->getJson('/notifications/unread')->assertJson(['count' => 0]);
    }

    public function test_scheduled_times_and_harvest_milestones(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());
        $this->assertSame('0 20 * * *', $events->first(fn ($e) => str_contains($e->command ?? '', 'daily-report'))->expression);
        $this->assertSame('0 6 * * *', $events->first(fn ($e) => str_contains($e->command ?? '', 'harvest-reminder'))->expression);
        $this->assertFalse($events->contains(fn ($e) => str_contains($e->command ?? '', 'notifications:dispatch pending')));
        $this->cycle();
        $this->travelTo(CarbonImmutable::parse('2026-10-30 05:59:00', 'Asia/Jakarta'));
        // The scheduler controls the official time; direct manual dispatch is allowed before it.
        $this->assertSame(1, app(NotificationService::class)->harvestReminders());
        $this->travelTo(CarbonImmutable::parse('2026-10-30 06:00:00', 'Asia/Jakarta'));
        $this->assertSame(1, app(NotificationService::class)->harvestReminders());
        $this->travelTo(CarbonImmutable::parse('2026-11-03 06:00:00', 'Asia/Jakarta'));
        $this->assertSame(1, app(NotificationService::class)->harvestReminders());
        $this->travelTo(CarbonImmutable::parse('2026-11-06 06:00:00', 'Asia/Jakarta'));
        $this->assertSame(1, app(NotificationService::class)->harvestReminders());
        $this->assertSame([7, 7, 3, 0], Bus::dispatched(SendWhatsAppNotification::class)->map(fn ($job) => $job->notification->data['days_before'])->all());
    }

    public function test_no_matching_active_schedule_or_assignment_produces_no_reminder(): void
    {
        $this->cycle('2026-11-05');
        $this->cycle('2026-11-06', 'harvested');
        $this->assertSame(0, app(NotificationService::class)->harvestReminders());
        $this->officer->forceFill(['assigned_farm_id' => null])->save();
        $this->cycle();
        $this->assertSame(0, app(NotificationService::class)->harvestReminders());
    }

    public function test_reschedule_cancellation_and_midnight_prevent_queued_in_app_delivery(): void
    {
        $data = $this->reminderData();
        $channel = app(InAppChannel::class);
        $cycle = PlantingCycle::findOrFail($data['cycle_id']);
        $cycle->update(['estimated_harvest_at' => '2026-11-02']);
        $channel->send($this->officer, new HarvestReminderNotification($data));
        $this->assertDatabaseCount('notifications', 0);
        $cycle->update(['estimated_harvest_at' => $data['harvest_date'], 'status' => 'cancelled']);
        $channel->send($this->officer, new HarvestReminderNotification($data));
        $this->assertDatabaseCount('notifications', 0);
        $cycle->update(['status' => 'active']);
        $this->travelTo(CarbonImmutable::parse('2026-10-31 00:00:00', 'Asia/Jakarta'));
        $channel->send($this->officer, new HarvestReminderNotification($data));
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_whatsapp_uses_current_number_and_skips_changed_or_expired_schedule(): void
    {
        $data = $this->reminderData();
        Http::fake([
            'api.fonnte.com/validate' => Http::response(['status' => true, 'registered' => ['6289876543210'], 'not_registered' => []]),
            'api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['123']]),
        ]);
        $this->officer->update(['phone_number' => '6289876543210']);
        (new SendWhatsAppNotification(new HarvestReminderNotification($data)))->handle(app(NotificationService::class), app(\App\Services\Notifications\FonnteClient::class));
        Http::assertSent(fn ($request) => $request['target'] === '6289876543210');
        PlantingCycle::findOrFail($data['cycle_id'])->update(['status' => 'cancelled']);
        (new SendWhatsAppNotification(new HarvestReminderNotification($data)))->handle(app(NotificationService::class), app(\App\Services\Notifications\FonnteClient::class));
        Http::assertSentCount(2);
    }

    public function test_repeated_scheduler_can_queue_whatsapp_again_without_outbox(): void
    {
        $this->cycle();
        $this->assertSame(1, app(NotificationService::class)->harvestReminders());
        $this->assertSame(1, app(NotificationService::class)->harvestReminders());
        Bus::assertDispatchedTimes(SendWhatsAppNotification::class, 2);
    }

    public function test_not_registered_number_receives_in_app_without_sending_whatsapp(): void
    {
        $data = $this->reminderData();
        Http::fake(['api.fonnte.com/validate' => Http::response([
            'status' => true, 'registered' => [], 'not_registered' => ['6281234567890'],
        ])]);
        $notification = new HarvestReminderNotification($data);
        (new SendWhatsAppNotification($notification))->handle(app(NotificationService::class), app(\App\Services\Notifications\FonnteClient::class));
        app(InAppChannel::class)->send($this->officer, $notification);
        $this->assertDatabaseCount('notifications', 1);
        Http::assertNotSent(fn ($request) => $request->url() === 'https://api.fonnte.com/send');
        Http::assertSentCount(1);
    }

    public function test_missing_number_and_disabled_provider_do_not_even_validate_the_number(): void
    {
        $data = $this->reminderData();
        $job = new SendWhatsAppNotification(new HarvestReminderNotification($data));
        $this->officer->update(['phone_number' => null]);
        $job->handle(app(NotificationService::class), app(\App\Services\Notifications\FonnteClient::class));
        $this->officer->update(['phone_number' => '6281234567890']);
        config(['operational_notifications.fonnte.enabled' => false]);
        $job->handle(app(NotificationService::class), app(\App\Services\Notifications\FonnteClient::class));
        Http::assertNothingSent();
    }

    public function test_reschedule_during_registration_check_prevents_send(): void
    {
        $data = $this->reminderData();
        Http::fake(function ($request) use ($data) {
            $this->assertSame('https://api.fonnte.com/validate', $request->url());
            PlantingCycle::findOrFail($data['cycle_id'])->update(['estimated_harvest_at' => '2026-11-02']);

            return Http::response(['status' => true, 'registered' => ['6281234567890'], 'not_registered' => []]);
        });
        (new SendWhatsAppNotification(new HarvestReminderNotification($data)))->handle(app(NotificationService::class), app(\App\Services\Notifications\FonnteClient::class));
        Http::assertSentCount(1);
    }

    public static function accessChanges(): array
    {
        return [['membership'], ['assignment'], ['inactive'], ['role']];
    }

    #[DataProvider('accessChanges')]
    public function test_access_changes_during_registration_check_are_not_hidden_by_permission_snapshots(string $change): void
    {
        $data = $this->reminderData();
        Http::fake(function ($request) use ($change) {
            $this->assertSame('https://api.fonnte.com/validate', $request->url());
            match ($change) {
                'membership' => $this->team->members()->detach($this->officer),
                'assignment' => $this->officer->forceFill(['assigned_farm_id' => null])->save(),
                'inactive' => $this->officer->forceFill(['is_active' => false])->save(),
                'role' => $this->officer->forceFill(['role_id' => \App\Models\Role::firstOrCreate(['name' => 'admin'])->id])->save(),
            };

            return Http::response(['status' => true, 'registered' => ['6281234567890'], 'not_registered' => []]);
        });
        (new SendWhatsAppNotification(new HarvestReminderNotification($data)))->handle(app(NotificationService::class), app(\App\Services\Notifications\FonnteClient::class));
        Http::assertSentCount(1);
    }

    public function test_too_little_time_for_validation_and_send_skips_all_provider_requests(): void
    {
        $data = $this->reminderData();
        $this->travelTo(CarbonImmutable::parse('2026-10-30 23:59:35', 'Asia/Jakarta'));
        (new SendWhatsAppNotification(new HarvestReminderNotification($data)))->handle(app(NotificationService::class), app(\App\Services\Notifications\FonnteClient::class));
        Http::assertNothingSent();
    }

    public function test_inbox_filters_by_type_so_template_title_changes_keep_history_visible(): void
    {
        $notification = new \App\Models\Notification;
        $notification->forceFill(['user_id' => $this->admin->id, 'type' => 'daily_report',
            'title' => 'Judul rekap yang diperbarui', 'message' => 'Kegiatan kebun hari ini.'])->save();
        $this->admin->notifications()->create(['title' => 'Notifikasi lama lainnya']);
        $this->actingAs($this->admin)->getJson('/notifications/unread')->assertOk()->assertJson(['count' => 1]);
        $this->actingAs($this->owner)->getJson('/notifications/unread')->assertOk()->assertJson(['count' => 0]);
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
