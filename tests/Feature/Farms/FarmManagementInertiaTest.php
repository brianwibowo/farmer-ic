<?php

namespace Tests\Feature\Farms;

use App\Enums\TeamRole;
use App\Models\Farm;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FarmManagementInertiaTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->team = Team::query()->create(['name' => 'Tim Farm Management', 'is_personal' => false]);
        $this->admin = $this->actor('admin', $this->team);
        $this->actingAs($this->admin);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        auth()->logout();
        $this->get('/admin/farm-management')->assertRedirect('/login');
        $this->get('/admin/farm-management/create')->assertRedirect('/login');
    }

    public function test_admin_can_create_edit_read_and_delete_through_inertia(): void
    {
        $this->get('/admin/farm-management/create')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farms/Form')->where('farm', null)->where('team.id', $this->team->id));
        $this->post('/admin/farm-management', $this->payload())->assertRedirect();
        $farm = Farm::query()->firstOrFail();
        $this->assertSame($this->team->id, $farm->team_id);
        $this->assertSame($this->admin->id, $farm->created_by);
        $this->get('/admin/farm-management/'.$farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farms/Show')->where('farm.name', 'Kebun Pertiwi')->where('can.update', true));
        $this->get('/admin/farm-management/'.$farm->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farms/Form')->where('farm.id', $farm->id));
        $this->put('/admin/farm-management/'.$farm->id, $this->payload(['name' => 'Kebun Pertiwi Utara']))
            ->assertRedirect('/admin/farm-management/'.$farm->id);
        $this->assertSame('Kebun Pertiwi Utara', $farm->fresh()->name);
        $this->delete('/admin/farm-management/'.$farm->id)->assertRedirect('/admin/farm-management');
        $this->assertDatabaseMissing('farms', ['id' => $farm->id]);
    }

    public function test_lists_summary_filters_and_pagination_are_scoped_to_the_team(): void
    {
        $this->farm(['name' => 'Jagung 100%', 'total_area_ha' => '5']);
        $this->farm(['name' => 'Kebun Selatan', 'total_area_ha' => '8']);
        $otherTeam = Team::query()->create(['name' => 'Tim lain']);
        app(FarmService::class)->create($this->payload(['name' => 'Rahasia', 'total_area_ha' => '100']), $this->admin, $otherTeam);
        $this->get('/admin/farm-management?per_page=1')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farms/Index')->has('farms.data', 1)->where('farms.total', 2)
            ->where('summary.count', 2)->where('summary.area_ha', '13.0000')->where('summary.cities', 1));
        $this->get('/admin/farm-management?search=%25')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('farms.total', 1)->where('farms.data.0.name', 'Jagung 100%'));
        $this->get('/admin/farm-management?sort=total_area_ha&direction=desc')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('farms.data.0.name', 'Kebun Selatan'));
    }

    public function test_owner_and_petugas_cannot_enter_admin_even_with_farm_read_permission(): void
    {
        $farm = $this->farm();
        $second = $this->farm(['name' => 'Kebun kedua']);
        $owner = $this->actor('owner', $this->team);
        $this->assertTrue(Gate::forUser($owner)->allows('view', $farm));
        $this->actingAs($owner)->get('/admin/farm-management')->assertForbidden();
        $this->get('/admin/farm-management/'.$farm->id)->assertForbidden();
        $this->get('/admin/farm-management/create')->assertForbidden();
        $this->post('/admin/farm-management', $this->payload())->assertForbidden();
        $this->get('/admin/farm-management/'.$farm->id.'/edit')->assertForbidden();
        $this->put('/admin/farm-management/'.$farm->id, $this->payload())->assertForbidden();
        $this->delete('/admin/farm-management/'.$farm->id)->assertForbidden();
        $worker = $this->actor('petugas', $this->team);
        $worker->forceFill(['assigned_farm_id' => $farm->id])->save();
        $this->assertTrue(Gate::forUser($worker)->allows('view', $farm));
        $this->assertFalse(Gate::forUser($worker)->allows('view', $second));
        $this->assertSame(1, app(FarmService::class)->paginate($worker, $this->team, [])->total());
        $this->actingAs($worker)->get('/admin/farm-management')->assertForbidden();
        $this->get('/admin/farm-management/'.$farm->id)->assertForbidden();
        $this->get('/admin/farm-management/'.$second->id)->assertForbidden();
        $this->post('/admin/farm-management', $this->payload())->assertForbidden();
    }

    public function test_cross_team_records_and_forged_tenant_or_creator_are_rejected(): void
    {
        $otherTeam = Team::query()->create(['name' => 'Tim privat']);
        $other = app(FarmService::class)->create($this->payload(), $this->admin, $otherTeam);
        $this->get('/admin/farm-management/'.$other->id)->assertForbidden();
        $this->get('/admin/farm-management/'.$other->id.'/edit')->assertForbidden();
        $this->put('/admin/farm-management/'.$other->id, $this->payload())->assertForbidden();
        $this->delete('/admin/farm-management/'.$other->id)->assertForbidden();
        $this->post('/admin/farm-management', $this->payload(['team_id' => $otherTeam->id, 'created_by' => $this->admin->id]))
            ->assertSessionHasErrors(['team_id', 'created_by']);
        $foreignOwner = $this->actor('owner', $otherTeam);
        $this->post('/admin/farm-management', $this->payload(['owner_id' => $foreignOwner->id]))->assertSessionHasErrors('owner_id');
        $this->actor('owner', $this->team);
        $this->get('/admin/farm-management/create')->assertInertia(fn (Assert $page) => $page
            // Admin user visibility intentionally excludes Owner and Superadmin records.
            ->has('owners', 0));
    }

    public function test_web_validation_uses_session_errors_and_preserves_records(): void
    {
        $this->from('/admin/farm-management/create')->post('/admin/farm-management', $this->payload([
            'total_area_ha' => 0, 'latitude' => '-6.25',
        ]))->assertRedirect('/admin/farm-management/create')->assertSessionHasErrors(['total_area_ha', 'latitude']);
        $this->assertDatabaseCount('farms', 0);
        $farm = $this->farm();
        $commodity = (string) Str::uuid();
        $variety = (string) Str::uuid();
        DB::table('commodities')->insert(['id' => $commodity, 'name' => 'Jagung']);
        DB::table('crop_varieties')->insert(['id' => $variety, 'name' => 'Bisi', 'commodity_id' => $commodity]);
        DB::table('planting_cycles')->insert([
            'id' => (string) Str::uuid(), 'farm_id' => $farm->id, 'variety_id' => $variety,
            'planted_at' => '2026-10-01', 'planted_area_ha' => 3, 'status' => 'active', 'created_at' => now(),
        ]);
        $this->from('/admin/farm-management/'.$farm->id.'/edit')->put('/admin/farm-management/'.$farm->id, $this->payload(['total_area_ha' => 2]))
            ->assertSessionHasErrors('total_area_ha');
        $this->assertSame('12.5000', $farm->fresh()->total_area_ha);
        $this->from('/admin/farm-management')->delete('/admin/farm-management/'.$farm->id)->assertRedirect('/admin/farm-management')->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('farms', ['id' => $farm->id]);
    }

    public function test_superadmin_can_manage_farms_and_a_missing_current_team_is_rejected(): void
    {
        $developer = $this->actor('superadmin', $this->team);
        $this->actingAs($developer)->get('/admin/farm-management')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('can.create', true)->where('filters.sort', 'name'));
        $this->post('/admin/farm-management', $this->payload())->assertRedirect();
        $this->admin->forceFill(['current_team_id' => null])->save();
        $this->actingAs($this->admin)->get('/admin/farm-management')->assertForbidden();
    }

    public function test_existing_daily_log_without_activities_also_blocks_deletion(): void
    {
        $farm = $this->farm();
        DB::table('daily_activity_logs')->insert([
            'id' => (string) Str::uuid(), 'farm_id' => $farm->id, 'recorded_by' => $this->admin->id,
            'work_date' => '2026-10-28', 'start_time' => '08:00', 'end_time' => '16:00', 'worker_count' => 2, 'created_at' => now(),
        ]);
        $this->from('/admin/farm-management')->delete('/admin/farm-management/'.$farm->id)->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('farms', ['id' => $farm->id]);
    }

    private function actor(string $role, Team $team): User
    {
        $record = Role::query()->firstOrCreate(['name' => $role]);
        $user = new User(['name' => ucfirst($role), 'email' => Str::uuid().'@example.test', 'password' => 'password']);
        $user->forceFill(['role_id' => $record->id, 'current_team_id' => $team->id, 'email_verified_at' => now()])->save();
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function farm(array $overrides = []): Farm
    {
        return app(FarmService::class)->create($this->payload($overrides), $this->admin, $this->team);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace(['name' => 'Kebun Pertiwi', 'total_area_ha' => '12.5000', 'city' => 'Bogor', 'province' => 'Jawa Barat'], $overrides);
    }
}
