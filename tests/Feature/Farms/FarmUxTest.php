<?php

namespace Tests\Feature\Farms;

use App\Models\AdministrativeRegion;
use App\Models\Farm;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use App\Services\FarmService;
use Database\Seeders\AdministrativeRegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FarmUxTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->team = $this->admin->currentTeam;
        $this->team->update(['is_personal' => false]);
        DB::table('administrative_regions')->insert([
            ['code' => '32', 'name' => 'Jawa Barat', 'level' => 1, 'parent_code' => null],
            ['code' => '32.01', 'name' => 'Kabupaten Bogor', 'level' => 2, 'parent_code' => '32'],
            ['code' => '32.01.09', 'name' => 'Sukamakmur', 'level' => 3, 'parent_code' => '32.01'],
        ]);
        $this->actingAs($this->admin);
    }

    public function test_search_returns_parent_context_and_rejects_guest_and_non_admin_portal_actors(): void
    {
        $this->getJson('/admin/farm-regions?search=Suka')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', '32.01.09')->assertJsonPath('data.0.city', 'Kabupaten Bogor')->assertJsonPath('data.0.province', 'Jawa Barat');
        $this->getJson('/admin/farm-regions?search=%25%25')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/admin/farm-regions?search=x')->assertUnprocessable();
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->getJson('/admin/farm-regions?search=Suka')->assertForbidden();
        auth()->logout();
        $this->getJson('/admin/farm-regions?search=Suka')->assertUnauthorized();
    }

    public function test_dropdown_address_persists_and_forged_parent_or_unknown_code_cannot_be_saved(): void
    {
        $payload = ['name' => 'Kebun Wilayah', 'land_tenure' => 'owned', 'total_area_ha' => '12.5', 'district_code' => '32.01.09',
            'district' => 'Sukamakmur', 'city' => 'Kabupaten Bogor', 'province' => 'Jawa Barat'];
        $this->post('/admin/farm-management', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $farm = Farm::query()->firstOrFail();
        $this->assertSame('32.01.09', $farm->getAttribute('district_code'));
        $this->get('/admin/farm-management/'.$farm->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page->where('farm.region.city', 'Kabupaten Bogor'));
        foreach ([['province' => 'Bali'], ['city' => 'Kota Bogor'], ['district_code' => '99.99.99'], ['district_code' => null]] as $bad) {
            $this->put('/admin/farm-management/'.$farm->id, array_replace($payload, $bad))->assertSessionHasErrors('district_code');
            $this->assertSame('Jawa Barat', $farm->fresh()->province);
        }
        $this->delete('/admin/farm-management/'.$farm->id)->assertRedirect();
        $this->assertDatabaseHas('administrative_regions', ['code' => '32.01.09']);
    }

    public function test_region_choices_follow_province_city_and_district_without_cross_parent_results(): void
    {
        DB::table('administrative_regions')->insert([
            ['code' => '31', 'name' => 'DKI Jakarta', 'level' => 1, 'parent_code' => null],
            ['code' => '31.71', 'name' => 'Kota Jakarta Pusat', 'level' => 2, 'parent_code' => '31'],
            ['code' => '31.71.01', 'name' => 'Gambir', 'level' => 3, 'parent_code' => '31.71'],
        ]);
        $this->getJson('/admin/farm-regions?level=1')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', '31')->assertJsonPath('data.0.parent_code', null);
        $this->getJson('/admin/farm-regions?level=2&parent_code=32')->assertOk()->assertExactJson(['data' => [
            ['code' => '32.01', 'name' => 'Kabupaten Bogor', 'parent_code' => '32'],
        ]]);
        $this->getJson('/admin/farm-regions?level=3&parent_code=32.01')->assertOk()->assertExactJson(['data' => [
            ['code' => '32.01.09', 'name' => 'Sukamakmur', 'parent_code' => '32.01'],
        ]]);
        $this->getJson('/admin/farm-regions?level=3&parent_code=31.71')->assertOk()->assertJsonPath('data.0.name', 'Gambir');
        $this->getJson('/admin/farm-regions?level=2&parent_code=99')->assertOk()->assertJsonCount(0, 'data');
        foreach (['level=2', 'level=3&parent_code=32', 'level=2&parent_code=32.01', 'level=4', 'level[]=2'] as $invalid) {
            $this->getJson('/admin/farm-regions?'.$invalid)->assertUnprocessable();
        }
        $this->admin->teams()->detach();
        $this->getJson('/admin/farm-regions?level=1')->assertForbidden();
    }

    public function test_unmatched_legacy_address_is_preserved_when_unchanged_but_cannot_be_changed_in_dropdown_contract(): void
    {
        $farm = app(FarmService::class)->create(['name' => 'Kebun Legacy', 'land_tenure' => 'owned', 'total_area_ha' => '1', 'district' => 'Ejaan lama', 'city' => 'Bogor', 'province' => 'Jawa Barat'], $this->admin, $this->team);
        $data = ['name' => 'Kebun Legacy Baru', 'land_tenure' => 'owned', 'total_area_ha' => '1', 'district_code' => null,
            'district' => 'Ejaan lama', 'city' => 'Bogor', 'province' => 'Jawa Barat'];
        $this->put('/admin/farm-management/'.$farm->id, $data)->assertSessionHasNoErrors();
        $this->put('/admin/farm-management/'.$farm->id, array_replace($data, ['province' => 'Bali']))->assertSessionHasErrors('district_code');
        $this->assertSame('Jawa Barat', $farm->fresh()->province);
    }

    public function test_filter_options_and_capacity_and_deletion_preview_respect_the_farm_boundary(): void
    {
        $service = app(FarmService::class);
        $farm = $service->create(['name' => 'Kebun Utama', 'land_tenure' => 'owned', 'total_area_ha' => '12.5', 'city' => 'Bogor'], $this->admin, $this->team);
        $other = Team::query()->create(['name' => 'Tim Lain', 'is_personal' => false]);
        $foreign = $service->create(['name' => 'Kebun Privat', 'total_area_ha' => '50', 'city' => 'Kota Rahasia'], $this->admin, $other);
        $this->get('/admin/farm-management')->assertInertia(fn (Assert $page) => $page->where('locationOptions.cities', ['Bogor']));
        $this->getJson('/admin/farm-management/'.$foreign->id.'/deletion-check')->assertForbidden();
        $this->put('/admin/farm-management/'.$foreign->id, ['district_code' => '99.99.99'])->assertForbidden();
        $this->getJson('/admin/farm-management/'.$farm->id.'/deletion-check')->assertOk()->assertJsonPath('blockers', []);
        $block = app(FarmBlockService::class)->create($farm, $this->admin, $this->team, ['code' => 'B01', 'name' => 'Blok Utara', 'area_ha' => '10']);
        $this->get('/admin/farm-management/'.$farm->id.'/edit')->assertInertia(fn (Assert $page) => $page->where('farm.capacity.minimum_area_ha', '10.0000'));
        $this->getJson('/admin/farm-management/'.$farm->id.'/deletion-check')->assertJsonPath('blockers', ['blok kebun']);
        $this->get('/kebun/'.$farm->id.'/blocks/'.$block->id.'/edit')->assertInertia(fn (Assert $page) => $page->where('capacity.maximum_area_ha', '12.5000'));
    }

    public function test_reference_policy_is_read_only_for_application_roles_and_denies_missing_membership(): void
    {
        $region = AdministrativeRegion::query()->firstOrFail();
        foreach (['admin', 'owner', 'petugas'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->assertTrue(Gate::forUser($actor)->allows('view', $region));
            $this->assertFalse(Gate::forUser($actor)->allows('update', $region));
            $this->assertFalse(Gate::forUser($actor)->allows('create', [AdministrativeRegion::class, $actor->currentTeam]));
            $this->assertTrue(AdministrativeRegion::visibleTo($actor, $actor->currentTeam)->exists());
            $actor->teams()->detach();
            $this->assertFalse(Gate::forUser($actor->fresh())->allows('view', $region));
            $this->assertSame(0, AdministrativeRegion::visibleTo($actor->fresh(), $this->team)->count());
        }
    }

    public function test_logbook_offers_only_visible_active_blocks_and_persists_the_selected_reference(): void
    {
        $this->seed(\Database\Seeders\BlockMappingActivityTypeSeeder::class);
        $farm = app(FarmService::class)->create(['name' => 'Kebun Pemetaan', 'total_area_ha' => '2'], $this->admin, $this->team);
        $block = app(FarmBlockService::class)->create($farm, $this->admin, $this->team, ['code' => 'B01', 'name' => 'Blok Utara', 'area_ha' => '1']);
        app(FarmBlockService::class)->create($farm, $this->admin, $this->team, ['code' => 'B02', 'name' => 'Blok Nonaktif', 'area_ha' => '1', 'is_active' => false]);
        $this->get('/kebun/'.$farm->id.'/daily-logs')->assertOk()->assertInertia(fn (Assert $page) => $page->has('blocks', 1)->where('blocks.0.id', $block->id));
        $this->post('/kebun/'.$farm->id.'/daily-logs', [
            'work_date' => '2026-11-02', 'start_time' => '08:00', 'end_time' => '09:00', 'worker_count' => 2,
            'activities' => [['type' => 'block_mapping', 'obstacle_status' => 'none', 'block_mapping' => [
                'block_id' => $block->id, 'block_name' => $block->name, 'length_m' => '10', 'width_m' => '10',
            ]]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('block_mapping_details', ['block_id' => $block->id, 'block_name' => 'Blok Utara']);
    }

    public function test_full_snapshot_is_idempotent_and_has_complete_parent_links(): void
    {
        $this->seed(AdministrativeRegionSeeder::class);
        $this->seed(AdministrativeRegionSeeder::class);
        $this->assertDatabaseCount('administrative_regions', 7837);
        $this->assertSame(38, AdministrativeRegion::query()->where('level', 1)->count());
        $this->assertSame(514, AdministrativeRegion::query()->where('level', 2)->count());
        $this->assertSame(7285, AdministrativeRegion::query()->where('level', 3)->count());
        $this->getJson('/admin/farm-regions?search=Sukamakmur')->assertOk()->assertJsonCount(2, 'data');
    }
}
