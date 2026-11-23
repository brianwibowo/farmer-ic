<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\ActivityType;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\Team;
use App\Models\User;
use App\Services\AdminDashboardService;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Integrated PostgreSQL coverage on the guarded, dedicated test database. */
class DashboardActivityPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(12, 0));
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->team = Team::factory()->create(['is_personal' => false]);
        $this->team->members()->attach($this->admin, ['role' => TeamRole::Member->value]);
        $this->admin->switchTeam($this->team);
        $this->actingAs($this->admin);
    }

    private function farm(?Team $team = null): Farm
    {
        $farm = new Farm(['name' => 'Lahan laporan', 'total_area_ha' => 10]);
        $farm->forceFill(['team_id' => ($team ?? $this->team)->id, 'created_by' => $this->admin->id])->save();

        return $farm;
    }

    private function officer(Farm $farm, string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'role' => 'petugas', 'assigned_farm_id' => $farm->id]);
        $farm->team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $user->switchTeam($farm->team);

        return $user;
    }

    private function activity(Farm $farm, User $officer, int $minutes, string $description = 'Penyiraman tanaman'): FarmActivity
    {
        $type = ActivityType::firstOrCreate(['code' => 'maintenance'], ['name' => 'Perawatan']);

        return FarmActivity::create([
            'farm_id' => $farm->id, 'activity_type_id' => $type->id, 'performed_by' => $officer->id,
            'activity_date' => now()->subMinutes($minutes), 'description' => $description, 'status' => 'completed',
        ]);
    }

    public function test_pagination_reaches_beyond_thirty_reports_and_ties_have_stable_order(): void
    {
        $farm = $this->farm();
        $officer = $this->officer($farm, 'Dimas Lapangan');
        $ids = [];
        for ($index = 0; $index < 37; $index++) {
            $ids[] = $this->activity($farm, $officer, $index)->id;
        }
        $foreign = $this->farm(Team::factory()->create(['is_personal' => false]));
        $this->activity($foreign, $this->officer($foreign, 'Petugas tenant lain'), 0);
        $this->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.activities', 10)->where('dashboard.activities.0.id', $ids[0])
            ->where('dashboard.activityPagination.total', 37)->where('dashboard.activityPagination.scope_total', 37)
            ->where('dashboard.activityPagination.last_page', 4)->where('dashboard.activityPagination.from', 1)
            ->where('dashboard.activityPagination.to', 10));
        $this->get('/admin/dashboard?activity_page=4')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.activities', 7)->where('dashboard.activities.0.id', $ids[30])
            ->where('dashboard.activities.6.id', $ids[36])->where('dashboard.activityPagination.current_page', 4)
            ->where('dashboard.activityPagination.from', 31)->where('dashboard.activityPagination.to', 37)
            ->where('dashboard.trend.total', 37)->where('dashboard.officers.0.activityCount', 37));
        $this->get('/admin/dashboard?activity_per_page=20&activity_page=2')->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.activities', 17)->where('dashboard.activityPagination.per_page', 20)
            ->where('dashboard.activityPagination.last_page', 2));
        FarmActivity::where('farm_id', $farm->id)->update(['activity_date' => now()]);
        rsort($ids, SORT_STRING);
        $this->get('/admin/dashboard?activity_page=2')->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.activities.0.id', $ids[10])->where('dashboard.activities.9.id', $ids[19]));
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_search_and_officer_filters_query_the_full_scope_without_changing_dashboard_totals(): void
    {
        $farm = $this->farm();
        $first = $this->officer($farm, 'Dimas Lapangan');
        $second = $this->officer($farm, 'Nadia Lapangan');
        for ($index = 0; $index < 32; $index++) {
            $this->activity($farm, $first, $index);
        }
        $match = $this->activity($farm, $second, 40, 'Pemeriksaan drainase utama');
        $this->activity($farm, $second, 41, 'Pembersihan alat');
        $foreign = $this->farm(Team::factory()->create(['is_personal' => false]));
        $hidden = $this->officer($foreign, 'Petugas rahasia');
        $this->activity($foreign, $hidden, 0, 'Pemeriksaan drainase utama');
        $query = http_build_query(['farm_id' => $farm->id, 'period' => 30, 'activity_search' => 'DRAINASE', 'activity_officer_id' => $second->id]);
        $this->get('/admin/dashboard?'.$query)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.activities', 1)->where('dashboard.activities.0.id', $match->id)
            ->where('dashboard.activityPagination.total', 1)->where('dashboard.activityPagination.scope_total', 34)
            ->where('dashboard.activityFilters.officer_id', $second->id)->where('dashboard.activityFilters.search', 'DRAINASE')
            ->where('dashboard.trend.total', 34)->where('dashboard.metrics.0.value', 1));
        $this->get('/admin/dashboard?activity_search=Nadia')->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.activities', 2)->where('dashboard.activityPagination.total', 2));
        $this->get('/admin/dashboard?activity_search=Perawatan')->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.activityPagination.total', 34));
        $this->get('/admin/dashboard?activity_search=%25')->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.activityPagination.total', 0));
        $this->get('/admin/dashboard?activity_officer_id='.$hidden->id.'&team_id='.$foreign->team_id)
            ->assertInertia(fn (Assert $page) => $page->has('dashboard.activities', 0)->where('dashboard.activityPagination.total', 0));
        $this->get('/admin/dashboard?farm_id='.$foreign->id)->assertForbidden();
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_farm_own_scope_and_read_grants_protect_paginator_totals(): void
    {
        $farm = $this->farm();
        $other = $this->farm();
        $officer = $this->officer($farm, 'Dimas Lapangan');
        $this->activity($farm, $officer, 0);
        $this->activity($other, $this->officer($other, 'Nadia Lapangan'), 0);
        $this->get('/admin/dashboard?farm_id='.$farm->id)->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.activityPagination.total', 1)->where('dashboard.activityPagination.scope_total', 1));
        $this->actingAs($officer)->get('/admin/dashboard')->assertForbidden();
        Gate::forUser($officer)->authorize('viewAny', [Farm::class, $this->team]);
        $summary = app(AdminDashboardService::class)->summary($officer, $this->team, paginateActivities: true);
        $this->assertSame(1, $summary['activityPagination']['total']);
        $this->assertSame(1, $summary['activityPagination']['scope_total']);
        $roles = app(RolePermissions::class)->roles();
        unset($roles['admin']['farm_activities.view']);
        config(['access.roles' => $roles]);
        $this->actingAs($this->admin)->get('/admin/dashboard')->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.activities', 0)->where('dashboard.activityPagination.total', 0)
            ->where('dashboard.activityPagination.scope_total', 0));
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_empty_feed_and_invalid_pagination_inputs_have_defined_responses(): void
    {
        $this->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.activities', 0)->where('dashboard.activityPagination.total', 0)
            ->where('dashboard.activityPagination.last_page', 1)->where('dashboard.activityPagination.from', null));
        $this->get('/admin/dashboard?'.http_build_query([
            'activity_page' => 0, 'activity_per_page' => 999, 'activity_officer_id' => 'invalid', 'activity_search' => str_repeat('x', 201),
        ]))->assertSessionHasErrors(['activity_page', 'activity_per_page', 'activity_officer_id', 'activity_search']);
    }
}
