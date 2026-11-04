<?php

namespace Tests\Feature\Farms;

use App\Enums\TeamRole;
use App\Models\Farm;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FarmTenantSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private Team $teamA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->teamA = $this->joinTeam('Tenant Kebun A');
        $this->superadmin->switchTeam($this->teamA);
        $this->actingAs($this->superadmin);
    }

    public function test_switching_tenants_updates_list_pagination_all_three_summary_values_and_location_options(): void
    {
        $teamB = $this->joinTeam('Tenant Kebun B');
        $emptyTeam = $this->joinTeam('Tenant Tanpa Kebun');
        $first = $this->farm($this->teamA, 'Kebun A Utara', '2.0000', 'Bogor', 'Jawa Barat');
        $second = $this->farm($this->teamA, 'Kebun A Selatan', '3.5000', 'Sukabumi', 'Jawa Barat');
        $foreign = $this->farm($teamB, 'Kebun B', '7.2500', 'Malang', 'Jawa Timur');
        $this->farm(null, 'Kebun Tanpa Tenant', '99.0000', 'Denpasar', 'Bali');

        $this->get('/admin/farm-management?per_page=1&sort=total_area_ha')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('team.id', $this->teamA->id)->where('currentTeam.id', $this->teamA->id)
            ->where('farms.total', 2)->has('farms.data', 1)->where('farms.data.0.id', $first->id)
            ->where('summary.count', 2)->where('summary.area_ha', '5.5000')->where('summary.cities', 2)
            ->where('locationOptions.cities', ['Bogor', 'Sukabumi'])->where('locationOptions.provinces', ['Jawa Barat']));
        $this->get('/admin/farm-management?per_page=1&sort=total_area_ha&page=2')->assertInertia(fn (Assert $page) => $page
            ->where('farms.total', 2)->has('farms.data', 1)->where('farms.data.0.id', $second->id));

        $this->from('/admin/farm-management')->post(route('teams.switch', $teamB))->assertRedirect('/admin/farm-management');
        $this->assertSame($teamB->id, $this->superadmin->fresh()->current_team_id);
        $this->get('/admin/farm-management')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('team.id', $teamB->id)->where('currentTeam.id', $teamB->id)
            ->where('farms.total', 1)->has('farms.data', 1)->where('farms.data.0.id', $foreign->id)
            ->where('summary.count', 1)->where('summary.area_ha', '7.2500')->where('summary.cities', 1)
            ->where('locationOptions.cities', ['Malang'])->where('locationOptions.provinces', ['Jawa Timur']));

        foreach (['search=Kebun%20A', 'city=Bogor', 'province=Jawa%20Barat', 'team_id='.$this->teamA->id] as $filter) {
            $this->get('/admin/farm-management?'.$filter)->assertInertia(fn (Assert $page) => $page
                ->where('farms.total', str_starts_with($filter, 'team_id=') ? 1 : 0)
                ->where('summary.count', 1)->where('summary.area_ha', '7.2500')->where('summary.cities', 1)
                ->where('locationOptions.cities', ['Malang'])->where('locationOptions.provinces', ['Jawa Timur']));
        }

        $this->post(route('teams.switch', $emptyTeam))->assertRedirect();
        $this->get('/admin/farm-management')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('team.id', $emptyTeam->id)->where('farms.total', 0)->has('farms.data', 0)
            ->where('summary.count', 0)->where('summary.area_ha', '0')->where('summary.cities', 0)
            ->where('locationOptions.cities', [])->where('locationOptions.provinces', []));
    }

    public function test_selected_tenant_filter_preserves_superadmin_global_access_without_membership(): void
    {
        $foreignTeam = Team::factory()->create(['name' => 'Tenant Global', 'is_personal' => false]);
        $foreign = $this->farm($foreignTeam, 'Kebun Global', '4.0000', 'Medan', 'Sumatera Utara');
        $this->assertFalse($this->superadmin->belongsToTeam($foreignTeam));
        $this->assertTrue(Gate::forUser($this->superadmin)->allows('access.global'));
        $this->assertTrue(Gate::forUser($this->superadmin)->allows('view', $foreign));
        $this->assertTrue(Gate::forUser($this->superadmin)->allows('update', $foreign));
        $this->assertTrue(Farm::visibleTo($this->superadmin)->whereKey($foreign->id)->exists());
        $this->assertTrue(Farm::visibleTo($this->superadmin, $this->teamA)->whereKey($foreign->id)->exists());

        $this->get('/admin/farm-management')->assertInertia(fn (Assert $page) => $page->where('farms.total', 0));
        $this->get('/admin/farm-management/'.$foreign->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('farm.id', $foreign->id));
        $this->patch('/admin/farm-management/'.$foreign->id, ['name' => 'Kebun Global Diperbarui'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Kebun Global Diperbarui', $foreign->fresh()->name);
        $this->assertSame($this->teamA->id, $this->superadmin->fresh()->current_team_id);
        $this->post(route('teams.switch', $foreignTeam))->assertForbidden();
    }

    private function joinTeam(string $name): Team
    {
        $team = Team::factory()->create(['name' => $name, 'is_personal' => false]);
        $team->members()->attach($this->superadmin, ['role' => TeamRole::Member->value]);

        return $team;
    }

    private function farm(?Team $team, string $name, string $area, string $city, string $province): Farm
    {
        return app(FarmService::class)->create([
            'name' => $name, 'total_area_ha' => $area, 'land_tenure' => 'owned', 'city' => $city, 'province' => $province,
        ], $this->superadmin, $team);
    }
}
