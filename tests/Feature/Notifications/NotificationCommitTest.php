<?php

namespace Tests\Feature\Notifications;

use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestDatabase;
use Tests\TestCase;

/** No enclosing transaction: exercises real database queue and commit callbacks. */
class NotificationCommitTest extends TestCase
{
    protected function tearDown(): void
    {
        while (DB::connection()->transactionLevel() > 0) {
            DB::rollBack();
        }
        TestDatabase::resetConnection('pgsql_testing');
        parent::tearDown();
    }

    private function prepareReminder(): void
    {
        $user = User::factory()->create(['role' => 'petugas', 'phone_number' => '6281234567890']);
        $team = Team::factory()->create(['is_personal' => false]);
        $team->members()->attach($user, ['role' => 'member']);
        $farm = new Farm(['name' => 'Kebun Commit Notifikasi', 'total_area_ha' => '2.0000']);
        $farm->forceFill(['created_by' => $user->id, 'team_id' => $team->id])->save();
        $user->forceFill(['assigned_farm_id' => $farm->id, 'current_team_id' => $team->id])->save();
        $commodity = Commodity::create(['name' => 'Jagung Commit']);
        $variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Bisi Commit']);
        PlantingCycle::create(['farm_id' => $farm->id, 'variety_id' => $variety->id,
            'planted_at' => '2026-09-01', 'estimated_harvest_at' => '2026-11-06', 'planted_area_ha' => '1.0000', 'status' => 'active']);
        $this->assertSame(1, app(NotificationService::class)->harvestReminders());
    }

    private function prepareDailyReport(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $team = Team::factory()->create(['name' => 'Tenant Rekap Harian', 'is_personal' => false]);
        $team->members()->attach($admin, ['role' => 'member']);
        foreach (['Kebun Bogor', 'Kebun Cianjur', 'Kebun Sukabumi'] as $name) {
            $farm = new Farm(['name' => $name, 'total_area_ha' => '2.0000']);
            $farm->forceFill(['created_by' => $admin->id, 'team_id' => $team->id])->save();
        }
        $this->assertSame(1, app(NotificationService::class)->dailyReports());
    }

    public function test_tenant_daily_snapshot_rolls_back_or_commits_and_is_rendered_by_the_real_worker(): void
    {
        Http::preventStrayRequests();
        config(['operational_notifications.enabled' => true, 'operational_notifications.connection' => 'database',
            'operational_notifications.fonnte.enabled' => false]);
        $this->travelTo(CarbonImmutable::parse('2026-10-30 20:00:00', 'Asia/Jakarta'));
        DB::beginTransaction();
        $this->prepareDailyReport();
        $this->assertDatabaseCount('jobs', 0);
        DB::rollBack();
        $this->assertDatabaseCount('jobs', 0);
        DB::transaction(fn () => $this->prepareDailyReport());
        $this->assertDatabaseCount('jobs', 2);
        $this->assertDatabaseCount('notifications', 0);
        Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'notifications', '--once' => true, '--sleep' => 0]);
        $this->assertDatabaseCount('notifications', 1);
        $message = DB::table('notifications')->value('message');
        $this->assertStringNotContainsString('Tenant Rekap Harian', $message);
        $this->assertStringContainsString('1. Kebun: Kebun Bogor', $message);
        $this->assertStringContainsString('2. Kebun: Kebun Cianjur', $message);
        $this->assertStringContainsString('3. Kebun: Kebun Sukabumi', $message);
        $this->assertSame(3, substr_count($message, 'Petugas belum mengisi aktivitas hari ini'));
        Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'whatsapp', '--once' => true, '--sleep' => 0]);
        $this->assertDatabaseCount('jobs', 0);
        Http::assertNothingSent();
    }

    public function test_rollback_dispatches_nothing_commit_dispatches_both_channels_and_real_worker_stores_in_app(): void
    {
        Http::preventStrayRequests();
        config(['operational_notifications.enabled' => true, 'operational_notifications.connection' => 'database',
            'operational_notifications.fonnte.enabled' => false]);
        $this->travelTo(CarbonImmutable::parse('2026-10-30 06:00:00', 'Asia/Jakarta'));
        DB::beginTransaction();
        $this->prepareReminder();
        $this->assertDatabaseCount('jobs', 0);
        DB::rollBack();
        $this->assertDatabaseCount('jobs', 0);
        DB::beginTransaction();
        $this->prepareReminder();
        $this->assertDatabaseCount('jobs', 0);
        DB::commit();
        $this->assertDatabaseCount('jobs', 2);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertSame(0, Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'notifications', '--once' => true, '--sleep' => 0]));
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(0, Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'whatsapp', '--once' => true, '--sleep' => 0]));
        $this->assertDatabaseCount('jobs', 0);
        Http::assertNothingSent();
    }

    public function test_real_worker_records_permanent_provider_failure_without_leaking_secrets_and_in_app_still_runs(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'token invalid'])]);
        config(['operational_notifications.enabled' => true, 'operational_notifications.connection' => 'database',
            'operational_notifications.fonnte.enabled' => true, 'operational_notifications.fonnte.token' => 'fake-private-token']);
        $this->travelTo(CarbonImmutable::parse('2026-10-30 06:00:00', 'Asia/Jakarta'));
        DB::transaction(fn () => $this->prepareReminder());
        $this->assertSame(0, Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'whatsapp', '--once' => true, '--sleep' => 0]));
        $this->assertDatabaseCount('failed_jobs', 1);
        $exception = DB::table('failed_jobs')->value('exception');
        $this->assertStringContainsString('provider_rejected', $exception);
        $this->assertStringNotContainsString('fake-private-token', $exception);
        $this->assertStringNotContainsString('6281234567890', $exception);
        $this->assertSame(0, Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'notifications', '--once' => true, '--sleep' => 0]));
        $this->assertDatabaseCount('notifications', 1);
        Http::assertSentCount(1);
    }

    public function test_database_queue_release_is_not_available_until_backoff_then_can_be_accepted(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.fonnte.com/validate' => Http::sequence()
                ->push(['status' => false, 'reason' => 'device disconnected'])
                ->push(['status' => true, 'registered' => ['6281234567890'], 'not_registered' => []]),
            'api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['456'], 'process' => 'pending']),
        ]);
        config(['operational_notifications.enabled' => true, 'operational_notifications.connection' => 'database',
            'operational_notifications.fonnte.enabled' => true, 'operational_notifications.fonnte.token' => 'fake-token']);
        $this->travelTo(CarbonImmutable::parse('2026-10-30 06:00:00', 'Asia/Jakarta'));
        DB::transaction(fn () => $this->prepareReminder());
        $payload = DB::table('jobs')->where('queue', 'whatsapp')->value('payload');
        $this->assertStringNotContainsString('6281234567890', $payload);
        $this->assertStringNotContainsString('fake-token', $payload);
        $options = ['connection' => 'database', '--queue' => 'whatsapp', '--once' => true, '--sleep' => 0];
        Artisan::call('queue:work', $options);
        $this->assertDatabaseHas('jobs', ['queue' => 'whatsapp', 'attempts' => 1]);
        $this->travel(59)->seconds();
        Artisan::call('queue:work', $options);
        Http::assertSentCount(1);
        $this->travel(1)->seconds();
        Artisan::call('queue:work', $options);
        $this->assertDatabaseMissing('jobs', ['queue' => 'whatsapp']);
        $this->assertDatabaseCount('failed_jobs', 0);
        Http::assertSentCount(3);
    }

    public function test_send_rejection_retries_at_most_three_times_and_keeps_in_app_independent(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.fonnte.com/validate' => Http::response(['status' => true, 'registered' => ['6281234567890'], 'not_registered' => []]),
            'api.fonnte.com/send' => Http::response(['status' => false, 'reason' => 'device disconnected']),
        ]);
        config(['operational_notifications.enabled' => true, 'operational_notifications.connection' => 'database',
            'operational_notifications.fonnte.enabled' => true, 'operational_notifications.fonnte.token' => 'fake-token']);
        $this->travelTo(CarbonImmutable::parse('2026-10-30 06:00:00', 'Asia/Jakarta'));
        DB::transaction(fn () => $this->prepareReminder());
        $options = ['connection' => 'database', '--queue' => 'whatsapp', '--once' => true, '--sleep' => 0];
        Artisan::call('queue:work', $options);
        $this->travel(60)->seconds();
        Artisan::call('queue:work', $options);
        $this->travel(300)->seconds();
        Artisan::call('queue:work', $options);
        $this->assertDatabaseMissing('jobs', ['queue' => 'whatsapp']);
        $this->assertDatabaseCount('failed_jobs', 1);
        Http::assertSentCount(6);
        Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'notifications', '--once' => true, '--sleep' => 0]);
        $this->assertDatabaseCount('notifications', 1);
    }
}
