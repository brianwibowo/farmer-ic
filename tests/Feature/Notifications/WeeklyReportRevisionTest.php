<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendWhatsAppNotification;
use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Channels\InAppChannel;
use App\Services\Notifications\FonnteClient;
use App\Services\Notifications\NotificationService;
use App\Services\Owner\HarvestProjectionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesNotificationTasks;
use Tests\TestCase;

/** Integrated PostgreSQL tests for weekly content, regional grouping and tenant filters. */
class WeeklyReportRevisionTest extends TestCase
{
    use CreatesNotificationTasks, RefreshDatabase;

    private Team $team;

    private User $owner;

    private User $admin;

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
        $this->owner = $this->actor('owner', $this->team);
        $this->admin = $this->actor('admin', $this->team);
        $this->officer = $this->actor('petugas', $this->team);
    }

    private function actor(string $role, Team $team): User
    {
        $user = User::factory()->create(['role' => $role, 'phone_number' => '6281234567890']);
        $team->members()->attach($user, ['role' => 'member']);

        return $user;
    }

    private function farm(string $name, string $city = 'Kabupaten Bogor', ?Team $team = null, int $area = 10): Farm
    {
        $farm = new Farm(['name' => $name, 'city' => $city, 'province' => 'Jawa Barat', 'total_area_ha' => $area]);
        $farm->forceFill(['team_id' => ($team ?? $this->team)->id, 'created_by' => $this->admin->id])->save();

        return $farm;
    }

    private function cycle(Farm $farm, ?FarmBlock $block = null): PlantingCycle
    {
        $task = $this->notificationTask($farm, 0, [], $block);
        $commodity = Commodity::firstOrCreate(['name' => 'Jagung']);
        $variety = CropVariety::firstOrCreate(['commodity_id' => $commodity->id, 'name' => 'Jagung Weekly']);
        $cycle = $task->plantingCycle;
        $cycle->update(['variety_id' => $variety->id, 'estimated_weight_ton' => 999]);

        return $cycle;
    }

    private function activity(PlantingCycle $cycle, string $code, ?string $at = null, ?User $performer = null, array $extra = []): FarmActivity
    {
        $name = ['harvest' => 'Panen', 'incident' => 'Insiden', 'irrigation' => 'Penyiraman'][$code];
        $type = ActivityType::firstOrCreate(['code' => $code], ['name' => $name]);
        $activity = new FarmActivity(['farm_id' => $cycle->farm_id, 'planting_cycle_id' => $cycle->id,
            'activity_type_id' => $type->id, 'performed_by' => ($performer ?? $this->officer)->id,
            'activity_date' => $at === null ? now() : CarbonImmutable::parse($at, 'Asia/Jakarta'), ...$extra]);
        $activity->forceFill(['block_id' => $cycle->block_id])->save();

        return $activity;
    }

    private function harvest(PlantingCycle $cycle, ?float $tons, string $at = '2026-11-12 09:00:00'): void
    {
        $this->activity($cycle, 'harvest', $at)->harvestDetail()->create(['gross_weight_ton' => $tons, 'harvested_area_ha' => '0.0100']);
    }

    public function test_owner_targets_match_dashboard_and_regions_merge_across_tenants(): void
    {
        $second = Team::factory()->create(['is_personal' => false]);
        $second->members()->attach($this->owner, ['role' => 'member']);
        $bogor = $this->farm('Kebun Satu', area: 10);
        $otherBogor = $this->farm('Kebun Dua', team: $second, area: 20);
        $cianjur = $this->farm('Kebun Tiga', 'Kabupaten Cianjur', area: 5);
        $first = $this->cycle($bogor);
        $this->cycle($bogor, FarmBlock::findOrFail($first->block_id)); // Same crop counted once per farm.
        $secondCycle = $this->cycle($otherBogor);
        $this->cycle($cianjur);
        $first->variety->commodity->update(['name' => 'JAGUNG']);
        $this->harvest($first, 1.5);
        $this->harvest($secondCycle, 2.5);
        $foreignTeam = Team::factory()->create(['is_personal' => false]);
        $foreign = $this->cycle($this->farm('Kebun Rahasia', team: $foreignTeam));
        $this->harvest($foreign, 99);
        app(NotificationService::class)->weeklyReports();
        $queued = Bus::dispatched(SendQueuedNotifications::class)
            ->first(fn ($job) => $job->notification->data['audience'] === 'owner')->notification;
        $this->assertCount(3, $queued->data['farms']);
        $this->assertNull($queued->data['team_id']);
        $this->assertEqualsCanonicalizing([$this->team->id, $second->id], $queued->data['team_ids']);
        $message = $queued->toInApp()['message'];
        $this->assertStringContainsString('Total seluruh kebun', $message);
        $this->assertStringContainsString('target per cycle: 1.400 ton; panen minggu ini: 4 ton', $message);
        $this->assertStringNotContainsString('Kebun Satu', $message);
        $this->assertStringNotContainsString('Kebun Rahasia', $message);
        $projection = app(HarvestProjectionService::class);
        $expected = collect($projection->summary($this->owner, $this->team)['cards'])->firstWhere('name', 'Jagung')['projection_ton']
            + collect($projection->summary($this->owner, $second)['cards'])->firstWhere('name', 'Jagung')['projection_ton'];
        $actual = collect($queued->data['farms'])->sum(fn ($farm) => collect($farm['crops'])->firstWhere('name', 'Jagung')['target_ton']);
        $this->assertEquals($expected, $actual);
        $jobs = Bus::dispatched(SendWhatsAppNotification::class)->filter(fn ($job) => $job->notification->data['audience'] === 'owner');
        $this->assertCount(2, $jobs);
        foreach ($jobs as $job) {
            $text = $job->notification->toWhatsAppText();
            $this->assertSame(1, substr_count($text, 'Kabupaten/kota:'));
            $this->assertStringNotContainsString('Total seluruh kebun', $text);
            $this->assertStringNotContainsString('Kebun Satu', $text);
            $this->assertStringNotContainsString('Tenant:', $text);
        }
        $bogorJob = $jobs->first(fn ($job) => $job->notification->data['farms'][0]['region_name'] === 'Kabupaten Bogor');
        $this->assertStringContainsString('target per cycle: 1.200 ton; panen minggu ini: 4 ton', $bogorJob->notification->toWhatsAppText());
        $cianjurJob = $jobs->first(fn ($job) => $job->notification->data['farms'][0]['region_name'] === 'Kabupaten Cianjur');
        $this->assertStringContainsString('target per cycle: 200 ton', $cianjurJob->notification->toWhatsAppText());
        $this->assertStringContainsString('Tidak ada panen yang terjadi minggu ini.', $cianjurJob->notification->toWhatsAppText());
    }

    public function test_admin_counts_all_incidents_and_obstacles_without_copying_long_reports(): void
    {
        $farm = $this->farm('Kebun Aktivitas');
        $cycle = $this->cycle($farm);
        $otherCycle = $this->cycle($farm, FarmBlock::findOrFail($cycle->block_id));
        foreach ([$cycle, $otherCycle] as $subblock) {
            $this->activity($subblock, 'irrigation', extra: ['obstacles' => str_repeat('Catatan panjang ', 30), 'obstacle_status' => 'reported']);
            $this->activity($subblock, 'incident')->incidentDetail()->create(['incident_category' => 'Cuaca', 'severity' => 'heavy', 'affected_area_ha' => '0.0100']);
        }
        $this->activity($cycle, 'incident')->incidentDetail()->create(['incident_category' => 'Alat rusak', 'severity' => 'light']);
        $this->activity($cycle, 'incident', '2026-11-08 23:59:59')->incidentDetail()->create(['incident_category' => 'Di luar periode', 'severity' => 'emergency']);
        $this->activity($cycle, 'incident', performer: $this->admin)->incidentDetail()->create(['incident_category' => 'Catatan Admin', 'severity' => 'light']);
        app(NotificationService::class)->weeklyReports();
        $notification = Bus::dispatched(SendWhatsAppNotification::class)
            ->first(fn ($job) => $job->notification->data['audience'] === 'admin')->notification;
        $this->assertSame(5, $notification->data['farms'][0]['activity_count']);
        $this->assertCount(1, $notification->data['farms'][0]['blocks']);
        $message = $notification->toInApp()['message'];
        $this->assertStringContainsString('Pengairan 2 kali', $message);
        $this->assertStringContainsString('Cuaca (Berat) 2 kejadian', $message);
        $this->assertStringContainsString('Alat rusak (Ringan) 1 kejadian', $message);
        $this->assertStringContainsString('Hambatan: 2 laporan.', $message);
        $this->assertStringNotContainsString(str_repeat('Catatan panjang ', 30), $message);
        $this->assertStringContainsString('Utama: Catatan panjang', $message);
        $this->assertStringNotContainsString('Di luar periode', $message);
        $this->assertStringNotContainsString('Catatan Admin', $message);
        $this->assertStringNotContainsString('target per cycle', $message);
        $this->assertStringNotContainsString('subblok', mb_strtolower($message));
        $content = $notification->toInApp()['content'];
        $this->assertSame('weekly_admin', $content['kind']);
        $this->assertSame(5, $content['farms'][0]['activity_count']);
        $this->assertSame(2, $content['farms'][0]['blocks'][0]['obstacle_count']);
        $this->assertSame([
            ['label' => 'Alat rusak (Ringan)', 'count' => 1],
            ['label' => 'Cuaca (Berat)', 'count' => 2],
        ], $content['farms'][0]['blocks'][0]['incidents']);
    }

    public function test_harvest_uses_actual_activity_date_with_inclusive_monday_and_sunday_cutoffs(): void
    {
        $cycle = $this->cycle($this->farm('Kebun Panen'));
        $this->harvest($cycle, 1, '2026-11-09 00:00:00');
        $this->harvest($cycle, 2, '2026-11-15 20:00:00');
        $this->harvest($cycle, 50, '2026-11-08 23:59:59');
        $this->harvest($cycle, 70, '2026-11-15 20:00:01');
        app(NotificationService::class)->weeklyReports();
        $notification = Bus::dispatched(SendWhatsAppNotification::class)
            ->first(fn ($job) => $job->notification->data['audience'] === 'owner')->notification;
        $this->assertStringContainsString('panen minggu ini: 3 ton', $notification->toWhatsAppText());
        $this->assertStringNotContainsString('999', $notification->toWhatsAppText());
    }

    public function test_owner_combined_report_is_findable_by_each_authorized_tenant_filter(): void
    {
        $second = Team::factory()->create(['is_personal' => false]);
        $second->members()->attach($this->owner, ['role' => 'member']);
        $this->farm('Kebun Pertama');
        $this->farm('Kebun Kedua', team: $second);
        app(NotificationService::class)->weeklyReports();
        $notification = Bus::dispatched(SendQueuedNotifications::class)
            ->first(fn ($job) => $job->notification->data['audience'] === 'owner')->notification;
        app(InAppChannel::class)->send($this->owner, $notification);
        foreach ([$this->team->id, $second->id] as $teamId) {
            $this->actingAs($this->owner)->get('/notifications?status=all&date_from=2026-11-15&date_to=2026-11-15&team_id='.$teamId)
                ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('notifications.total', 1)
                    ->where('notifications.data.0.tenant_name', null));
        }
        $foreign = Team::factory()->create(['is_personal' => false]);
        $this->get('/notifications?status=all&team_id='.$foreign->id)->assertForbidden();
    }

    public function test_rich_owner_content_is_persisted_and_remains_a_historical_snapshot(): void
    {
        $cycle = $this->cycle($this->farm('Kebun Snapshot'));
        $this->harvest($cycle, 1.5);
        app(NotificationService::class)->weeklyReports();
        $notification = Bus::dispatched(SendQueuedNotifications::class)
            ->first(fn ($job) => $job->notification->data['audience'] === 'owner')->notification;
        app(InAppChannel::class)->send($this->owner, $notification);
        $stored = $this->owner->notifications()->firstOrFail();
        $this->assertSame('weekly_owner', $stored->content['kind']);
        $this->assertSame(1, $stored->content['version']);
        $this->assertSame('2026-11-09', $stored->content['period']['from']);
        $jagung = collect($stored->content['totals']['commodities'])->firstWhere('name', 'Jagung');
        $this->assertEquals(1.5, $jagung['harvest_ton']);
        $this->assertEquals(400, $jagung['target_ton']);
        $this->assertArrayNotHasKey('farms', $stored->content);
        $this->assertArrayNotHasKey('user_id', $stored->content);
        $cycle->activities()->whereHas('harvestDetail')->firstOrFail()->harvestDetail()->update(['gross_weight_ton' => 8]);
        $this->actingAs($this->owner)->get('/notifications')
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('notifications.data.0.content.kind', 'weekly_owner')
                ->where('notifications.data.0.content.totals.commodities.0.harvest_ton', 1.5));
        $other = $this->actor('owner', $this->team);
        $this->actingAs($other)->get('/notifications')->assertInertia(
            fn (\Inertia\Testing\AssertableInertia $page) => $page->has('notifications.data', 0),
        );
    }

    public function test_revocation_after_fonnte_validation_blocks_combined_county_payload(): void
    {
        $second = Team::factory()->create(['is_personal' => false]);
        $second->members()->attach($this->owner, ['role' => 'member']);
        $this->farm('Kebun Pertama');
        $this->farm('Kebun Kedua', team: $second);
        app(NotificationService::class)->weeklyReports();
        $job = Bus::dispatched(SendWhatsAppNotification::class)->first(fn ($job) => $job->notification->data['audience'] === 'owner');
        Http::fake(function () use ($second) {
            $second->members()->detach($this->owner);

            return Http::response(['status' => true, 'registered' => ['6281234567890'], 'not_registered' => []]);
        });
        $job->handle(app(NotificationService::class), app(FonnteClient::class));
        Http::assertSentCount(1);
        app(InAppChannel::class)->send($this->owner, $job->notification);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_missing_harvest_weight_is_reported_as_missing_instead_of_no_harvest(): void
    {
        $this->harvest($this->cycle($this->farm('Kebun Data Panen')), null);
        app(NotificationService::class)->weeklyReports();
        $notification = Bus::dispatched(SendWhatsAppNotification::class)->first(fn ($job) => $job->notification->data['audience'] === 'owner')->notification;
        $this->assertStringContainsString('berat belum tercatat', $notification->toWhatsAppText());
        $this->assertStringNotContainsString('Tidak ada panen yang terjadi minggu ini.', $notification->toWhatsAppText());
    }

    public function test_equal_city_names_in_different_provinces_do_not_merge(): void
    {
        $this->farm('Kebun Provinsi Pertama', 'Nama Kabupaten Sama');
        $other = $this->farm('Kebun Provinsi Kedua', 'Nama Kabupaten Sama');
        $other->update(['province' => 'Provinsi Lain']);
        app(NotificationService::class)->weeklyReports();
        $jobs = Bus::dispatched(SendWhatsAppNotification::class)->filter(fn ($job) => $job->notification->data['audience'] === 'owner');
        $this->assertCount(2, $jobs);
    }

    public function test_coded_and_legacy_farms_use_the_same_regional_identity(): void
    {
        $legacy = new Farm(['city' => 'Kabupaten Bogor', 'province' => 'Jawa Barat']);
        $coded = new Farm(['city' => 'KABUPATEN  BOGOR', 'province' => 'JAWA BARAT']);
        $coded->forceFill(['city_code' => '32.01']);
        $weekly = app(\App\Services\Notifications\WeeklyReportService::class);
        $this->assertSame($weekly->region($legacy)['key'], $weekly->region($coded)['key']);
    }

    public function test_weekly_audience_and_required_data_grants_are_rechecked_at_delivery(): void
    {
        $this->farm('Kebun Hak Akses');
        app(NotificationService::class)->weeklyReports();
        $jobs = Bus::dispatched(SendWhatsAppNotification::class);
        $ownerJob = $jobs->first(fn ($job) => $job->notification->data['audience'] === 'owner');
        $adminJob = $jobs->first(fn ($job) => $job->notification->data['audience'] === 'admin');
        $service = app(NotificationService::class);
        $this->assertNotNull($service->validRecipient($ownerJob->notification->data));
        $this->assertNotNull($service->validRecipient($adminJob->notification->data));
        $grants = config('access.roles.owner');
        unset($grants['harvest_details.view']);
        config(['access.roles.owner' => $grants]);
        $this->assertNull($service->validRecipient($ownerJob->notification->data));
        $this->assertNotNull($service->validRecipient($adminJob->notification->data));
        $this->owner->forceFill(['role_id' => \App\Models\Role::firstWhere('name', 'admin')->id])->save();
        $this->assertNull($service->validRecipient($ownerJob->notification->data));
        $grants = config('access.roles.admin');
        $grants['incident_details.view'] = 'invalid';
        config(['access.roles.admin' => $grants]);
        $this->assertNull($service->validRecipient($adminJob->notification->data));
    }
}
