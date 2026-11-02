<?php

namespace Tests\Feature\Notifications;

use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use App\Policies\NotificationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-31 10:00:00', 'Asia/Jakarta'));
        config(['inertia.ssr.enabled' => false]);
    }

    private function actor(string $role, Team $team): User
    {
        $user = User::factory()->create(['role' => $role]);
        $team->members()->attach($user, ['role' => 'member']);

        return $user;
    }

    private function notice(User $user, ?Team $team, bool $read = false, string $at = '2026-10-31 12:00:00+07'): Notification
    {
        $notification = new Notification;
        $notification->forceFill([
            'user_id' => $user->id, 'team_id' => $team?->id, 'type' => 'daily_report',
            'title' => 'Report Harian Kebun dari Petugas', 'message' => 'Tenant: '.($team?->name ?? 'Histori lama')."\nJumlah kebun: 1\nCatatan kebun.",
            'is_read' => $read, 'created_at' => CarbonImmutable::parse($at),
        ])->save();

        return $notification;
    }

    public static function roles(): array
    {
        return [['owner'], ['admin'], ['petugas'], ['superadmin']];
    }

    #[DataProvider('roles')]
    public function test_default_lists_own_unread_notifications_across_all_jakarta_dates(string $role): void
    {
        $team = Team::factory()->create(['is_personal' => false]);
        $user = $this->actor($role, $team);
        $this->notice($user, $team, at: '2026-10-31 00:00:00+07');
        $this->notice($user, $team, at: '2026-10-31 23:59:59+07');
        $this->notice($user, $team, at: '2026-10-30 23:59:59+07');
        $this->notice($user, $team, at: '2026-11-01 00:00:00+07');
        $this->notice($user, $team, read: true);
        $this->notice($this->actor('admin', $team), $team);
        $this->actingAs($user)->get('/notifications')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'unread')->where('filters.date_from', '2026-10-31')->where('filters.date_to', '2026-10-31')
            ->where('notifications.total', 4)->has('notifications.data', 4)->where('unreadCount', 4)
            ->where('notifications.data.0.date_key', '2026-11-01')->where('notifications.data.0.date_label', '1 November 2026')
            ->where('canFilterTenant', $role === 'owner')->has('tenantOptions', $role === 'owner' ? 1 : 0));
        $this->assertSame($role === 'owner', app(NotificationPolicy::class)->showsTenantContext($user));
    }

    public function test_all_tab_combines_tenant_and_jakarta_date_range_before_pagination(): void
    {
        $first = Team::factory()->create(['name' => 'Tenant Pertama', 'is_personal' => false]);
        $second = Team::factory()->create(['name' => 'Tenant Kedua', 'is_personal' => false]);
        $foreign = Team::factory()->create(['name' => 'Tenant Lain', 'is_personal' => false]);
        $owner = $this->actor('owner', $first);
        $second->members()->attach($owner, ['role' => 'member']);
        for ($i = 0; $i < 17; $i++) {
            $this->notice($owner, $first);
        }
        $match = $this->notice($owner, $second, read: true, at: '2026-10-30 23:59:59+07');
        $this->notice($owner, $second);
        $this->notice($owner, null);
        $this->notice($this->actor('owner', $foreign), $foreign, read: true);
        $query = http_build_query(['status' => 'all', 'date_from' => '2026-10-30', 'date_to' => '2026-10-30', 'team_id' => $second->id]);
        $this->actingAs($owner)->get('/notifications?'.$query)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('notifications.total', 1)->where('notifications.data.0.id', $match->id)
            ->where('notifications.data.0.tenant_name', 'Tenant Kedua')->has('tenantOptions', 2));
        $query = http_build_query(['status' => 'all', 'date_from' => '2026-10-31', 'date_to' => '2026-10-31', 'team_id' => $first->id, 'page' => 2]);
        $this->get('/notifications?'.$query)->assertInertia(fn (Assert $page) => $page
            ->where('notifications.total', 17)->where('notifications.current_page', 2)->has('notifications.data', 2));
        $this->get('/notifications')->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'unread')->where('filters.team_id', null)->where('filters.date_from', '2026-10-31'));
    }

    public function test_unread_ignores_date_and_authorized_tenant_filters_but_all_uses_inclusive_jakarta_days(): void
    {
        $team = Team::factory()->create(['is_personal' => false]);
        $second = Team::factory()->create(['is_personal' => false]);
        $owner = $this->actor('owner', $team);
        $second->members()->attach($owner, ['role' => 'member']);
        $this->notice($owner, $team, at: '2026-10-30 23:59:59+07');
        $start = $this->notice($owner, $team, at: '2026-10-31 00:00:00+07');
        $this->notice($owner, $team, read: true, at: '2026-10-31 23:59:59+07');
        $this->notice($owner, $second, at: '2026-11-01 00:00:00+07');
        $this->actingAs($owner)->get('/notifications?status=unread&team_id='.$team->id)
            ->assertInertia(fn (Assert $page) => $page->where('notifications.total', 3));
        $this->get('/notifications?status=all&team_id='.$team->id)
            ->assertInertia(fn (Assert $page) => $page->where('notifications.total', 2)
                ->where('notifications.data.1.id', $start->id)->where('notifications.data.1.date_key', '2026-10-31'));
    }

    public function test_tenant_filter_rejects_unassociated_personal_and_non_owner_contexts(): void
    {
        $team = Team::factory()->create(['is_personal' => false]);
        $foreign = Team::factory()->create(['is_personal' => false]);
        $personal = Team::factory()->create(['is_personal' => true]);
        $owner = $this->actor('owner', $team);
        $personal->members()->attach($owner, ['role' => 'member']);
        $this->actingAs($owner)->get('/notifications?team_id='.$foreign->id)->assertForbidden();
        $this->get('/notifications?team_id='.$personal->id)->assertForbidden();
        foreach (['admin', 'petugas', 'superadmin'] as $role) {
            $this->actingAs($this->actor($role, $team))->get('/notifications?team_id='.$team->id)->assertForbidden();
        }
    }

    public function test_admin_gets_no_tenant_text_even_from_stored_legacy_messages(): void
    {
        $team = Team::factory()->create(['name' => 'Nama Tenant Rahasia', 'is_personal' => false]);
        $admin = $this->actor('admin', $team);
        $notice = $this->notice($admin, $team);
        $notice->update(['message' => "Report Harian Kebun dari Petugas — 31 Oktober 2026\nTenant: Nama Tenant Rahasia\nJumlah kebun: 1\nCatatan kebun."]);
        $this->actingAs($admin)->get('/notifications')->assertInertia(fn (Assert $page) => $page
            ->where('notifications.data.0.tenant_name', null)
            ->where('notifications.data.0.message', "Report Harian Kebun dari Petugas — 31 Oktober 2026\nJumlah kebun: 1\nCatatan kebun.")
            ->where('canFilterTenant', false)->has('tenantOptions', 0));
        $this->assertStringContainsString('Nama Tenant Rahasia', $notice->fresh()->message);
    }

    public function test_read_action_preserves_the_active_result_and_removes_it_from_unread_filter(): void
    {
        $team = Team::factory()->create(['is_personal' => false]);
        $user = $this->actor('admin', $team);
        $notice = $this->notice($user, $team);
        $filters = ['status' => 'unread', 'date_from' => '2026-10-31', 'date_to' => '2026-10-31'];
        $this->actingAs($user)->followingRedirects()->patch('/notifications/'.$notice->id.'/read', $filters)
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('notifications.total', 0)->where('unreadCount', 0));
        $this->assertTrue($notice->fresh()->is_read);
        $other = $this->notice($this->actor('admin', $team), $team);
        $this->patch('/notifications/'.$other->id.'/read', $filters)->assertForbidden();
        $this->assertFalse($other->fresh()->is_read);
    }

    public function test_invalid_dates_and_unknown_status_are_rejected(): void
    {
        $team = Team::factory()->create(['is_personal' => false]);
        $user = $this->actor('admin', $team);
        $this->actingAs($user)->getJson('/notifications?date_from=2026-11-01&date_to=2026-10-31')->assertUnprocessable()->assertJsonValidationErrors('date_to');
        $this->getJson('/notifications?date_from=2026-02-31')->assertUnprocessable()->assertJsonValidationErrors('date_from');
        $this->getJson('/notifications?status=read')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson('/notifications?status=invalid')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_migration_backfills_only_exact_report_id_matches_and_preserves_content(): void
    {
        $first = Team::factory()->create(['name' => 'Nama Sama', 'is_personal' => false]);
        $second = Team::factory()->create(['name' => 'Nama Sama', 'is_personal' => false]);
        $owner = $this->actor('owner', $first);
        $second->members()->attach($owner, ['role' => 'member']);
        $hash = hash('sha256', json_encode(['daily_report', $owner->id, $second->id, '2026-10-31'], JSON_THROW_ON_ERROR));
        $id = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-5'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
        DB::table('notifications')->insert(['id' => $id, 'user_id' => $owner->id, 'type' => 'daily_report', 'message' => 'Tenant: Nama Sama', 'created_at' => '2026-10-31T00:00:00+07:00', 'is_read' => true]);
        $unmatched = $this->notice($owner, null);
        $migration = require database_path('migrations/2026_10_31_000003_add_team_id_to_notifications.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseHas('notifications', ['id' => $id, 'team_id' => $second->id, 'is_read' => true, 'message' => 'Tenant: Nama Sama']);
        $this->assertNull($unmatched->fresh()->team_id);
    }
}
