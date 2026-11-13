<?php

namespace Tests\Feature\Notifications;

use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
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

    public function test_text_history_remains_readable_without_structured_content(): void
    {
        $team = Team::factory()->create(['is_personal' => false]);
        $owner = $this->actor('owner', $team);
        $notice = $this->notice($owner, $team);
        $this->actingAs($owner)->get('/notifications')->assertInertia(
            fn (Assert $page) => $page->where('notifications.data.0.content', null)
                ->where('notifications.data.0.id', $notice->id)
                ->where('notifications.data.0.message', fn ($message) => str_contains($message, 'Catatan kebun.')),
        );
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
    public function test_status_tabs_scope_personal_history_for_every_role(string $role): void
    {
        $team = Team::factory()->create(['is_personal' => false]);
        $actor = $this->actor($role, $team);
        $this->notice($actor, $team, read: true, at: '2025-01-01 12:00:00+07');
        $this->notice($actor, $team);
        $this->notice($this->actor('admin', $team), $team);
        $this->actingAs($actor)->get('/notifications?status=all&date_from=invalid&team_id=999')
            ->assertInertia(fn (Assert $page) => $page->where('notifications.total', 2)
                ->where('unreadCount', 1)->where('status', 'all')->missing('filters')->missing('tenantOptions'));
        $this->get('/notifications')->assertInertia(fn (Assert $page) => $page->where('status', 'unread')->where('notifications.total', 1));
        $this->get('/notifications?status=read')->assertInertia(fn (Assert $page) => $page->where('status', 'read')->where('notifications.total', 1)->where('notifications.data.0.is_read', true));
        $this->getJson('/notifications?status=invalid')->assertUnprocessable();
    }

    public function test_read_keeps_the_card_in_the_feed_and_foreign_recipients_are_denied(): void
    {
        $team = Team::factory()->create(['is_personal' => false]);
        $actor = $this->actor('admin', $team);
        $notice = $this->notice($actor, $team);
        $foreign = $this->notice($this->actor('admin', $team), $team);
        $this->actingAs($actor)->patch('/notifications/'.$notice->id.'/read', ['status' => 'all'])->assertRedirect('/notifications?status=all');
        $this->get('/notifications?status=all')->assertInertia(fn (Assert $page) => $page
            ->where('notifications.total', 1)->where('notifications.data.0.is_read', true)->where('unreadCount', 0));
        $this->patch('/notifications/'.$foreign->id.'/read')->assertForbidden();
    }

    public function test_feed_paginates_all_dates_and_validates_page(): void
    {
        $team = Team::factory()->create(['is_personal' => false]);
        $actor = $this->actor('owner', $team);
        for ($i = 0; $i < 17; $i++) {
            $this->notice($actor, $team, read: true);
        }
        $this->actingAs($actor)->get('/notifications?status=all&page=2')->assertInertia(fn (Assert $page) => $page
            ->where('notifications.total', 17)->has('notifications.data', 2)->where('notifications.current_page', 2));
        $this->getJson('/notifications?page=0')->assertUnprocessable();
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
