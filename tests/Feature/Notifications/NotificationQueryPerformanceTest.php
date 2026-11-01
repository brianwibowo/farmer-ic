<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendWhatsAppNotification;
use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\Notification;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Notifications\DailyReportNotification;
use App\Services\Notifications\FonnteClient;
use App\Services\Notifications\NotificationInboxService;
use App\Services\Notifications\NotificationService;
use App\Support\RolePermissions;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/** Integrated PostgreSQL query-growth and authorization regression tests. */
class NotificationQueryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-30 20:00:00', 'Asia/Jakarta'));
        config(['inertia.ssr.enabled' => false, 'operational_notifications.enabled' => true,
            'operational_notifications.connection' => 'database']);
        Http::preventStrayRequests();
        Bus::fake();
    }

    private function actor(Team $team, string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $team->members()->attach($user, ['role' => 'member']);

        return $user;
    }

    private function farm(Team $team): Farm
    {
        $farm = new Farm(['name' => 'Kebun Uji Query', 'total_area_ha' => 1000]);
        $farm->forceFill(['team_id' => $team->id, 'created_by' => $team->members()->firstOrFail()->id])->save();

        return $farm;
    }

    private function activity(Farm $farm, User $officer): void
    {
        $type = ActivityType::firstOrCreate(['code' => 'block_mapping'], ['name' => 'Pemetaan Blok']);
        $activity = FarmActivity::create(['farm_id' => $farm->id, 'activity_type_id' => $type->id,
            'performed_by' => $officer->id, 'activity_date' => now(), 'description' => 'Memeriksa ukuran blok kebun.']);
        $activity->blockMappingDetail()->create(['block_name' => 'Blok A', 'length_m' => 10, 'width_m' => 10]);
    }

    private function cycle(Farm $farm, int $days): PlantingCycle
    {
        $commodity = Commodity::firstOrCreate(['name' => 'Jagung Uji Query']);
        $variety = CropVariety::firstOrCreate(['commodity_id' => $commodity->id, 'name' => 'Bisi Uji Query']);

        return PlantingCycle::create(['farm_id' => $farm->id, 'variety_id' => $variety->id,
            'planted_at' => '2026-09-01', 'estimated_harvest_at' => now()->addDays($days)->toDateString(),
            'planted_area_ha' => 1, 'status' => 'active']);
    }

    private function notice(User $user, Team $team): void
    {
        $notice = new Notification;
        $notice->forceFill(['user_id' => $user->id, 'team_id' => $team->id, 'type' => 'daily_report',
            'title' => 'Report Harian Kebun dari Petugas', 'message' => "Tenant: Kebun Uji Query\nJumlah kebun: 1", 'created_at' => now()])->save();
    }

    private function queryCount(Closure $work): int
    {
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        try {
            $work();

            return count($connection->getQueryLog());
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
    }

    public static function inboxRoles(): array
    {
        return [['owner'], ['admin'], ['petugas']];
    }

    #[DataProvider('inboxRoles')]
    public function test_http_inbox_query_count_does_not_grow_with_cards(string $role): void
    {
        $team = Team::factory()->create(['is_personal' => false]);
        $user = $this->actor($team, $role);
        $this->notice($user, $team);
        $this->actingAs($user->fresh())->get('/notifications')->assertOk();
        $one = $this->queryCount(fn () => $this->actingAs($user->fresh())->get('/notifications')->assertOk());
        for ($i = 0; $i < 14; $i++) {
            $this->notice($user, $team);
        }
        $fifteen = $this->queryCount(fn () => $this->actingAs($user->fresh())->get('/notifications')->assertOk());
        $this->assertSame($one, $fifteen, 'Inbox query count must not scale per card.');
    }

    public function test_daily_queries_do_not_grow_per_farm_and_all_farms_are_preserved(): void
    {
        $team = Team::factory()->create(['is_personal' => false]);
        $this->actor($team, 'owner');
        $this->actor($team, 'admin');
        $officer = $this->actor($team, 'petugas');
        $farm = $this->farm($team);
        $this->activity($farm, $officer);
        $service = app(NotificationService::class);
        $one = $this->queryCount(fn () => $this->assertSame(2, $service->dailyReports()));
        for ($i = 0; $i < 7; $i++) {
            $this->activity($this->farm($team), $officer);
        }
        Bus::fake();
        $eight = $this->queryCount(fn () => $this->assertSame(2, $service->dailyReports()));
        $this->assertSame($one, $eight, 'Daily queries must not scale per farm.');
        foreach (Bus::dispatched(SendWhatsAppNotification::class) as $job) {
            $this->assertCount(8, $job->notification->data['farms']);
            foreach ($job->notification->data['farms'] as $report) {
                $this->assertCount(1, $report['activities']);
                $this->assertSame($officer->name, $report['activities'][0]['performer_name']);
            }
        }
    }

    public function test_harvest_queries_do_not_grow_per_cycle_and_all_three_margins_are_kept(): void
    {
        $team = Team::factory()->create(['is_personal' => false]);
        $officer = $this->actor($team, 'petugas');
        $farm = $this->farm($team);
        $officer->forceFill(['assigned_farm_id' => $farm->id])->save();
        $this->cycle($farm, 7);
        $service = app(NotificationService::class);
        $one = $this->queryCount(fn () => $this->assertSame(1, $service->harvestReminders()));
        for ($i = 0; $i < 29; $i++) {
            $this->cycle($farm, [7, 3, 0][$i % 3]);
        }
        Bus::fake();
        $thirty = $this->queryCount(fn () => $this->assertSame(30, $service->harvestReminders()));
        $this->assertSame($one, $thirty, 'Harvest queries must not scale per cycle.');
        $this->assertEqualsCanonicalizing([0, 3, 7], Bus::dispatched(SendWhatsAppNotification::class)
            ->map(fn ($job) => $job->notification->data['days_before'])->unique()->values()->all());
    }

    public function test_tenant_options_have_constant_round_trips_and_exclude_foreign_tenants(): void
    {
        $team = Team::factory()->create(['is_personal' => false]);
        $owner = $this->actor($team, 'owner')->load('role');
        $inbox = app(NotificationInboxService::class);
        $one = $this->queryCount(fn () => $this->assertCount(1, $inbox->tenantOptions($owner)));
        for ($i = 0; $i < 7; $i++) {
            Team::factory()->create(['is_personal' => false])->members()->attach($owner, ['role' => 'member']);
        }
        $foreign = Team::factory()->create(['is_personal' => false]);
        $eight = $this->queryCount(fn () => $this->assertCount(8, $inbox->tenantOptions($owner)));
        $this->assertSame($one, $eight);
        $this->assertNotContains($foreign->id, array_column($inbox->tenantOptions($owner), 'id'));
    }

    public function test_permission_snapshot_does_not_survive_exception_or_membership_revocation(): void
    {
        $team = Team::factory()->create(['is_personal' => false]);
        $owner = $this->actor($team, 'owner')->load(['role', 'teams']);
        $permissions = app(RolePermissions::class);
        try {
            $permissions->withMembershipSnapshot($owner, function () use ($permissions, $owner, $team): void {
                $this->assertTrue($permissions->withinTeam($owner, $team));
                throw new RuntimeException('End read operation');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('End read operation', $exception->getMessage());
        }
        $team->members()->detach($owner);
        $this->assertFalse($permissions->withinTeam($owner, $team));
        $permissions->withMembershipSnapshot($owner, function () use ($owner, $team, $permissions): void {
            $this->assertFalse($permissions->withinTeam($owner, $team));
            $this->assertFalse(Gate::forUser($owner)->allows('notifications.dailyReport', $team));
        });
    }

    public function test_new_snapshot_ignores_stale_loaded_teams_and_denies_admin_with_two_tenants(): void
    {
        $team = Team::factory()->create(['is_personal' => false]);
        $admin = $this->actor($team, 'admin')->load(['role', 'teams']);
        $permissions = app(RolePermissions::class);
        $this->assertTrue($permissions->withMembershipSnapshot($admin, fn () => $permissions->withinTeam($admin, $team)));
        Team::factory()->create(['is_personal' => false])->members()->attach($admin, ['role' => 'member']);
        $this->assertFalse($permissions->withMembershipSnapshot($admin, fn () => $permissions->withinTeam($admin, $team)));
        $this->actingAs($admin)->get('/notifications')->assertForbidden();
    }

    public function test_queue_validation_reloads_memberships_after_a_previous_successful_validation(): void
    {
        $team = Team::factory()->create(['is_personal' => false]);
        $owner = $this->actor($team, 'owner');
        $farm = $this->farm($team);
        $data = ['kind' => 'daily_report', 'user_id' => $owner->id, 'team_id' => $team->id,
            'event_date' => now()->toDateString(), 'farms' => [['id' => $farm->id]]];
        $service = app(NotificationService::class);
        $one = $this->queryCount(fn () => $this->assertNotNull($service->validRecipient($data)));
        for ($i = 0; $i < 7; $i++) {
            $data['farms'][] = ['id' => $this->farm($team)->id];
        }
        $eight = $this->queryCount(fn () => $this->assertNotNull($service->validRecipient($data)));
        $this->assertSame($one, $eight, 'Queue snapshot checks must not query each farm.');
        $team->members()->detach($owner);
        $this->assertNull($service->validRecipient($data));
    }

    public function test_disabled_whatsapp_job_makes_no_database_or_provider_requests(): void
    {
        config(['operational_notifications.fonnte.enabled' => false]);
        $team = Team::factory()->create(['is_personal' => false]);
        $owner = $this->actor($team, 'owner');
        $farm = $this->farm($team);
        $notification = new DailyReportNotification(['kind' => 'daily_report', 'user_id' => $owner->id,
            'team_id' => $team->id, 'event_date' => now()->toDateString(), 'farms' => [['id' => $farm->id]]]);
        $queries = $this->queryCount(fn () => (new SendWhatsAppNotification($notification))
            ->handle(app(NotificationService::class), app(FonnteClient::class)));
        $this->assertSame(0, $queries);
        Http::assertNothingSent();
    }
}
