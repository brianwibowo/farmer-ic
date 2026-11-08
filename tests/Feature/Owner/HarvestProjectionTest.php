<?php

namespace Tests\Feature\Owner;

use App\Enums\TeamRole;
use App\Http\Controllers\Owner\OwnerController;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** HTTP/service integration against the guarded, dedicated PostgreSQL test database. */
class HarvestProjectionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'owner']);
        $this->team = Team::factory()->create(['is_personal' => false]);
        $this->team->members()->attach($this->owner, ['role' => TeamRole::Member->value]);
        $this->owner->switchTeam($this->team);
        $this->actingAs($this->owner);
    }

    public function test_canonical_projection_route_uses_owner_controller_and_returns_projection_data(): void
    {
        $route = app('router')->getRoutes()->getByName('owner.projection');
        $this->assertNotNull($route);
        $this->assertSame('proyeksi', $route->uri());
        $this->assertSame(OwnerController::class.'@projection', $route->getActionName());

        $farm = $this->farm('Kebun Proyeksi', area: '1.5000');
        $this->cycle($farm, 'Jagung', '0.2500', '99.000');
        $this->get(route('owner.projection'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/Projection')
            ->where('projection.period', 'cycle')
            ->has('projection.cards', 3)
            ->has('projection.rows', 1)
            ->where('projection.cards.0.projection_ton', 60)
            ->where('projection.rows.0.farm_id', $farm->id)
            ->where('projection.rows.0.projection_ton', 60));
        $this->get('/kebun/proyeksi')->assertNotFound();
    }

    public function test_cycle_uses_total_farm_hectares_once_per_crop_regardless_of_batch_count(): void
    {
        $farm = $this->farm('Kebun Bogor', area: '10.1250');
        $cycle = $this->cycle($farm, 'Jagung', '2.0000', '17.250');
        $this->cycle($farm, 'Jagung', '1.0000', '2.750');
        $this->cycle($farm, 'Rumput', '1.0000', '100.000');
        foreach (['planned', 'harvested', 'cancelled'] as $status) {
            $this->cycle($farm, 'Sorgum', '9.0000', '999.000', $status);
        }
        $this->get(route('owner.projection'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/Projection')->where('projection.period', 'cycle')
            ->where('projection.cards.0.projection_ton', 405)
            ->where('projection.cards.1.projection_ton', 1012.5)
            ->where('projection.cards.2.projection_ton', 0)
            ->has('projection.rows', 2)
            ->where('projection.rows.0.farm_name', 'Kebun Bogor')
            ->where('projection.rows.0.crop_name', 'Jagung')
            ->where('projection.rows.0.projection_ton', 405)
            ->where('projection.rows.1.crop_name', 'Rumput')
            ->where('projection.rows.1.projection_ton', 1012.5));
        $cycle->update(['planted_area_ha' => '0.5000', 'estimated_weight_ton' => '30.000']);
        $this->get(route('owner.projection'))->assertInertia(fn (Assert $page) => $page
            ->where('projection.cards.0.projection_ton', 405)
            ->where('projection.rows.0.projection_ton', 405));

        $farm->update(['total_area_ha' => '12.3750']);
        $this->get(route('owner.projection'))->assertInertia(fn (Assert $page) => $page
            ->where('projection.cards.0.projection_ton', 495)
            ->where('projection.cards.1.projection_ton', 1237.5)
            ->where('projection.rows.0.projection_ton', 495)
            ->where('projection.rows.1.projection_ton', 1237.5));
    }

    public function test_annual_calculator_multiplies_total_area_cycle_projection_by_crop_frequency(): void
    {
        $farm = $this->farm('Kebun Cariu', area: '8.5000');
        $this->cycle($farm, 'Jagung', '1.0000', '42.000');
        $this->cycle($farm, 'Rumput', '2.0000', '8.000');
        $this->cycle($farm, 'Sorgum', '0.5000', '9.000');
        $this->get(route('owner.projection'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('projection.cards.0.projection_ton', 340)
            ->where('projection.cards.1.projection_ton', 850)
            ->where('projection.cards.2.projection_ton', 382.5)
            ->where('projection.rows.0.projection_ton', 340)
            ->where('projection.rows.1.projection_ton', 850)
            ->where('projection.rows.2.projection_ton', 382.5));
        $this->get(route('owner.projection', ['period' => 'year']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('projection.period', 'year')
            ->where('projection.cards.0.projection_ton', 1020)
            ->where('projection.cards.1.projection_ton', 4250)
            ->where('projection.cards.2.projection_ton', 1147.5)
            ->where('projection.rows.0.projection_ton', 1020)
            ->where('projection.rows.1.projection_ton', 4250)
            ->where('projection.rows.2.projection_ton', 1147.5));
    }

    public function test_null_or_zero_estimates_do_not_affect_area_calculator_and_unsupported_crops_remain_explicit(): void
    {
        $farm = $this->farm('Kebun Data Parsial', area: '5.3750');
        $this->cycle($farm, 'Jagung', '1.0000', null);
        $this->cycle($farm, 'Jagung', '1.0000', '0.000');
        $this->cycle($farm, 'Rumput', '1.0000', null);
        $this->cycle($farm, 'Kedelai', '1.0000', '12.000');
        $this->get(route('owner.projection'))->assertInertia(fn (Assert $page) => $page
            ->where('projection.cards.0.projection_ton', 215)->where('projection.cards.0.missing_count', 0)
            ->where('projection.cards.1.projection_ton', 537.5)->where('projection.cards.1.missing_count', 0)
            ->where('projection.rows.0.projection_ton', 215)
            ->where('projection.rows.1.crop_name', 'Kedelai')->where('projection.rows.1.projection_ton', null)
            ->where('projection.rows.2.projection_ton', 537.5));
        $this->get(route('owner.projection', ['period' => 'year']))->assertInertia(fn (Assert $page) => $page
            ->where('projection.cards.0.projection_ton', 645)->where('projection.cards.0.missing_count', 0)
            ->where('projection.cards.1.projection_ton', 2687.5)->where('projection.cards.1.missing_count', 0)
            ->where('projection.rows.0.projection_ton', 645)
            ->where('projection.rows.1.projection_ton', null)
            ->where('projection.rows.2.projection_ton', 2687.5));
    }

    public function test_rows_cards_and_filter_never_include_another_tenant(): void
    {
        $farm = $this->farm('Kebun Terlihat', area: '3.2500');
        $this->cycle($farm, 'Jagung', '1.0000', '40.000');
        $foreignTeam = Team::factory()->create(['is_personal' => false]);
        $foreign = $this->farm('Kebun Rahasia', $foreignTeam, '999.0000');
        $this->cycle($foreign, 'Jagung', '999.0000', '999.000');
        $this->get(route('owner.projection', ['team_id' => $foreignTeam->id]))->assertInertia(fn (Assert $page) => $page
            ->has('projection.rows', 1)->where('projection.farm_count', 1)
            ->where('projection.cards.0.projection_ton', 130)
            ->where('projection.rows.0.projection_ton', 130));
        $this->get(route('owner.projection', ['period' => 'year', 'team_id' => $foreignTeam->id]))->assertInertia(fn (Assert $page) => $page
            ->has('projection.rows', 1)->where('projection.farm_count', 1)
            ->where('projection.cards.0.projection_ton', 390)
            ->where('projection.rows.0.projection_ton', 390));
        $this->get(route('owner.projection', ['farm_id' => $foreign->id]))->assertForbidden();
        $this->team->members()->detach($this->owner);
        $this->get(route('owner.projection'))->assertForbidden();
    }

    public function test_farm_filter_scopes_cards_and_rows_and_second_team_requires_switching(): void
    {
        $farm = $this->farm('Kebun A', area: '2.5000');
        $this->cycle($farm, 'Jagung', '1.0000', '40.000');
        $this->cycle($this->farm('Kebun B', area: '5.2500'), 'Jagung', '2.0000', '80.000');
        $this->get(route('owner.projection'))->assertInertia(fn (Assert $page) => $page
            ->has('projection.rows', 2)->where('projection.cards.0.projection_ton', 310)
            ->where('projection.rows.0.projection_ton', 100)
            ->where('projection.rows.1.projection_ton', 210));
        $this->get(route('owner.projection', ['farm_id' => $farm->id]))->assertInertia(fn (Assert $page) => $page
            ->has('projection.rows', 1)->where('projection.cards.0.projection_ton', 100)
            ->where('projection.rows.0.projection_ton', 100));
        $this->get(route('owner.projection', ['period' => 'year', 'farm_id' => $farm->id]))->assertInertia(fn (Assert $page) => $page
            ->has('projection.rows', 1)->where('projection.cards.0.projection_ton', 300)
            ->where('projection.rows.0.projection_ton', 300));
        $secondTeam = Team::factory()->create(['is_personal' => false]);
        $secondTeam->members()->attach($this->owner, ['role' => TeamRole::Member->value]);
        $secondFarm = $this->farm('Kebun Tenant Kedua', $secondTeam);
        $this->get(route('owner.projection', ['farm_id' => $secondFarm->id]))->assertNotFound();
        $this->owner->refresh()->switchTeam($secondTeam);
        $this->get(route('owner.projection'))->assertInertia(fn (Assert $page) => $page
            ->has('projection.rows', 1)->where('projection.rows.0.farm_name', 'Kebun Tenant Kedua'));
    }

    public function test_scopes_on_related_records_are_applied_before_totals_and_serialization(): void
    {
        $this->cycle($this->farm('Kebun Terbatas'), 'Jagung', '1.0000', '40.000');
        $roles = app(RolePermissions::class)->roles();
        unset($roles['owner']['crop_varieties.view']);
        config(['access.roles' => $roles]);
        $this->get(route('owner.projection'))->assertInertia(fn (Assert $page) => $page
            ->where('projection.cards.0.projection_ton', 0)
            ->where('projection.rows.0.crop_name', null)->where('projection.unidentified_batches', 1));
        unset($roles['owner']['farms.view']);
        config(['access.roles' => $roles]);
        $this->get(route('owner.projection'))->assertForbidden();
    }

    public function test_empty_dataset_and_farm_without_batches_do_not_fabricate_projections(): void
    {
        $this->get(route('owner.projection'))->assertInertia(fn (Assert $page) => $page
            ->has('projection.rows', 0)->where('projection.farm_count', 0));
        $this->farm('Kebun Baru');
        $this->get(route('owner.projection'))->assertInertia(fn (Assert $page) => $page
            ->where('projection.rows.0.projection_ton', null)->where('projection.rows.0.crop_name', null));
    }

    public function test_route_rejects_non_owners_guests_and_invalid_filters(): void
    {
        foreach (['admin', 'petugas'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->team->members()->attach($actor, ['role' => TeamRole::Member->value]);
            $actor->switchTeam($this->team);
            $this->actingAs($actor)->get(route('owner.projection'))->assertForbidden();
        }
        $this->actingAs($this->owner)->get(route('owner.projection', ['period' => 'month', 'farm_id' => 'invalid']))
            ->assertSessionHasErrors(['period', 'farm_id']);
        auth()->forgetGuards();
        $this->get(route('owner.projection'))->assertRedirect('/login');
    }

    private function farm(string $name, ?Team $team = null, string $area = '100.0000'): Farm
    {
        $farm = new Farm(['name' => $name, 'total_area_ha' => $area]);
        $farm->forceFill(['team_id' => ($team ?? $this->team)->id, 'created_by' => $this->owner->id])->save();

        return $farm;
    }

    private function cycle(Farm $farm, string $crop, string $area, ?string $weight, string $status = 'active'): PlantingCycle
    {
        $commodity = Commodity::firstOrCreate(['name' => $crop]);
        $variety = CropVariety::firstOrCreate(['commodity_id' => $commodity->id, 'name' => $crop.' unggul']);

        return PlantingCycle::create([
            'farm_id' => $farm->id, 'variety_id' => $variety->id, 'planted_area_ha' => $area,
            'planted_at' => '2026-09-01',
            'estimated_weight_ton' => $weight, 'status' => $status,
        ]);
    }
}
