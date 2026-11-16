<?php

namespace Tests\Feature\Farms;

use App\Models\Farm;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmService;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as CacheStore;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Mockery;
use Tests\TestCase;

// Runs against the integrated, dedicated PostgreSQL test schema.
class FarmGeocodingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.nominatim.cache_store' => 'array']);
        Cache::store('array')->flush();
        Http::preventStrayRequests();
        Sleep::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->currentTeam->update(['is_personal' => false]);
        DB::table('administrative_regions')->insert([
            ['code' => '32', 'name' => 'Jawa Barat', 'level' => 1, 'parent_code' => null],
            ['code' => '32.01', 'name' => 'Kabupaten Bogor', 'level' => 2, 'parent_code' => '32'],
            ['code' => '32.01.09', 'name' => 'Sukamakmur', 'level' => 3, 'parent_code' => '32.01'],
        ]);
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Sleep::fake(false);
        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['address' => 'Jalan Raya Sukamakmur', 'district_code' => '32.01.09'], $overrides);
    }

    private function nominatimResult(array $overrides = []): array
    {
        return array_replace([
            'lat' => '-6.5791234', 'lon' => '107.0023456',
            'display_name' => 'Jalan Raya Sukamakmur, Sukamakmur, Kabupaten Bogor, Jawa Barat, Indonesia',
            'category' => 'highway', 'addresstype' => 'road',
            'address' => ['country_code' => 'id', 'state' => 'Jawa Barat', 'county' => 'Bogor', 'city_district' => 'Kecamatan Sukamakmur'],
        ], $overrides);
    }

    public function test_search_uses_trusted_region_and_caches_results_without_persisting_a_farm(): void
    {
        Http::fake(['*' => Http::response([$this->nominatimResult()])]);
        $url = '/admin/farm-management/geocode';
        $this->postJson($url, $this->payload())->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.matches_region', true)->assertJsonPath('data.0.approximate', false)
            ->assertJsonPath('data.0.latitude', -6.5791234)->assertJsonPath('data.0.longitude', 107.0023456);
        $this->postJson($url, $this->payload(['address' => '  Jalan Raya Sukamakmur  ']))->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['countrycodes'] === 'id'
            && $request['q'] === 'Jalan Raya Sukamakmur, Sukamakmur, Kabupaten Bogor, Jawa Barat, Indonesia'
            && $request['addressdetails'] === 1 && $request['limit'] === 5
            && str_contains($request->header('User-Agent')[0], config('app.name')));
        $this->assertDatabaseCount('farms', 0);
    }

    public function test_limits_are_global_and_cached_results_remain_available(): void
    {
        Http::fake(['*' => Http::response([$this->nominatimResult()])]);
        $this->postJson('/admin/farm-management/geocode', $this->payload())->assertOk();
        $other = User::factory()->create(['role' => 'admin']);
        $other->currentTeam->update(['is_personal' => false]);
        $this->actingAs($other)->postJson('/admin/farm-management/geocode', $this->payload(['address' => 'Jalan Desa Sukamakmur']))
            ->assertStatus(429)->assertHeader('Retry-After', '2');
        $this->postJson('/admin/farm-management/geocode', $this->payload())->assertOk();
        Http::assertSentCount(1);
    }

    public function test_lock_blocks_concurrent_requests_and_upstream_429_starts_a_cooldown(): void
    {
        Http::fake(['*' => Http::response([], 429)]);
        $lock = Cache::store('array')->lock('farm-geocoding:nominatim:lock', 20);
        $lock->get();
        $this->postJson('/admin/farm-management/geocode', $this->payload())->assertStatus(429);
        Http::assertNothingSent();
        $lock->release();
        $this->postJson('/admin/farm-management/geocode', $this->payload())->assertStatus(429)->assertHeader('Retry-After', '60');
        $this->postJson('/admin/farm-management/geocode', $this->payload())->assertStatus(429);
        Http::assertSentCount(1);
    }

    public function test_a_cache_without_lock_support_stops_before_contacting_nominatim(): void
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('get')->once()->andReturnNull();
        Cache::partialMock()->shouldReceive('store')->with('array')
            ->once()->andReturn(new Repository($store));

        $this->postJson('/admin/farm-management/geocode', $this->payload())->assertStatus(503);

        Http::assertNothingSent();
    }

    public function test_results_cached_by_another_worker_are_read_again_after_locking(): void
    {
        $result = [
            'label' => 'Jalan Raya Sukamakmur', 'latitude' => -6.5791234, 'longitude' => 107.0023456,
            'approximate' => false, 'matches_region' => true, 'can_auto_select' => true, 'search_stage' => 'address',
        ];
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('get')->once()->andReturnTrue();
        $lock->shouldReceive('release')->once()->andReturnTrue();
        $store = Mockery::mock(Store::class, LockProvider::class);
        $store->shouldReceive('lock')->with('farm-geocoding:nominatim:lock', 40)->once()->andReturn($lock);
        $cache = Mockery::mock(CacheStore::class);
        $cache->shouldReceive('get')->with(Mockery::pattern('/^farm-geocoding:v3:/'))
            ->twice()->andReturn(null, [$result]);
        $cache->shouldReceive('getStore')->once()->andReturn($store);
        Cache::partialMock()->shouldReceive('store')->with('array')->once()->andReturn($cache);

        $this->postJson('/admin/farm-management/geocode', $this->payload())->assertOk()
            ->assertExactJson(['data' => [$result]]);

        Http::assertNothingSent();
    }

    public function test_guests_owners_workers_and_missing_membership_cannot_search(): void
    {
        foreach (['owner', 'petugas'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->postJson('/admin/farm-management/geocode', $this->payload())->assertForbidden();
        }
        $this->admin->teams()->detach();
        $this->actingAs($this->admin->fresh())->postJson('/admin/farm-management/geocode', $this->payload())->assertForbidden();
        auth()->logout();
        $this->postJson('/admin/farm-management/geocode', $this->payload())->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_unknown_regions_and_forged_context_are_rejected_before_external_calls(): void
    {
        foreach ([['district_code' => '99.99.99'], ['district_code' => '32.01'], ['address' => ''], ['team_id' => 99], ['farm_id' => 'forged'], ['url' => 'https://example.com']] as $invalid) {
            $this->postJson('/admin/farm-management/geocode', $this->payload($invalid))->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_edit_checks_the_persisted_farm_and_superadmin_can_search(): void
    {
        $foreignTeam = Team::query()->create(['name' => 'Tenant Lain', 'is_personal' => false]);
        $farm = app(FarmService::class)->create(['name' => 'Kebun Lain', 'total_area_ha' => '1'], $this->admin, $foreignTeam);
        $url = '/admin/farm-management/'.$farm->id.'/geocode';
        $this->postJson($url, $this->payload())->assertForbidden();
        Http::assertNothingSent();
        Http::fake(['*' => Http::response([$this->nominatimResult()])]);
        $this->actingAs(User::factory()->create(['role' => 'superadmin']))->postJson($url, $this->payload())->assertOk();
    }

    public function test_coarse_and_mismatched_locations_are_labeled_and_bad_coordinates_are_discarded(): void
    {
        Http::fake(['*' => Http::response([
            $this->nominatimResult(['category' => 'boundary', 'addresstype' => 'district']),
            $this->nominatimResult(['lat' => '-6.6', 'address' => ['country_code' => 'id', 'state' => 'Bali']]),
            $this->nominatimResult(['lat' => 'invalid']), $this->nominatimResult(['lon' => '181']),
            $this->nominatimResult(['address' => ['country_code' => 'my']]),
        ])]);
        $this->postJson('/admin/farm-management/geocode', $this->payload())->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.approximate', true)->assertJsonPath('data.0.matches_region', true)
            ->assertJsonPath('data.1.matches_region', false);
    }

    public function test_empty_results_are_cached_and_failures_do_not_erase_saved_coordinates(): void
    {
        Http::fakeSequence()->push([])->push([])->push([])
            ->push(['error' => 'unavailable'], 503)->push('invalid json')->pushResponse(Http::failedConnection());
        $url = '/admin/farm-management/geocode';
        $this->postJson($url, $this->payload())->assertOk()->assertJsonCount(0, 'data');
        $this->postJson($url, $this->payload())->assertOk();
        Http::assertSentCount(3);
        $farm = app(FarmService::class)->create(['name' => 'Kebun Pin', 'total_area_ha' => '1', 'latitude' => '-6.5791234', 'longitude' => '107.0023456'], $this->admin, $this->admin->currentTeam);
        foreach (['Jalan Satu', 'Jalan Dua', 'Jalan Tiga'] as $address) {
            Cache::store('array')->forget('farm-geocoding:nominatim:next');
            $this->postJson('/admin/farm-management/'.$farm->id.'/geocode', $this->payload(['address' => $address]))->assertStatus(503);
        }
        $this->assertSame('-6.5791234', $farm->fresh()->latitude);
    }

    public function test_full_address_then_place_search_finds_the_screenshot_location_without_duplicating_regions(): void
    {
        DB::table('administrative_regions')->insert([
            ['code' => '32.04', 'name' => 'Kabupaten Bandung', 'level' => 2, 'parent_code' => '32'],
            ['code' => '32.04.06', 'name' => 'Pangalengan', 'level' => 3, 'parent_code' => '32.04'],
        ]);
        Http::fakeSequence()->push([])->push([$this->nominatimResult([
            'lat' => '-7.2020441', 'lon' => '107.5498098', 'category' => 'water', 'addresstype' => 'reservoir',
            'display_name' => 'Situ Cileunca, Pulosari, Kabupaten Bandung, Jawa Barat, Indonesia',
            'address' => ['country_code' => 'id', 'state' => 'Jawa Barat', 'county' => 'Kabupaten Bandung', 'village' => 'Pulosari'],
        ])]);
        $this->postJson('/admin/farm-management/geocode', [
            'address' => 'Jl. Situ Cileunca, Desa Warnasari, Pangalengan, Kabupaten Bandung', 'district_code' => '32.04.06',
        ])->assertOk()->assertJsonPath('data.0.search_stage', 'place')
            ->assertJsonPath('data.0.approximate', true)->assertJsonPath('data.0.matches_region', false)
            ->assertJsonPath('data.0.can_auto_select', true)
            ->assertJsonPath('data.0.latitude', -7.2020441);
        $queries = Http::recorded()->map(fn ($pair) => $pair[0]['q'])->all();
        $this->assertSame([
            'Jl. Situ Cileunca, Desa Warnasari, Pangalengan, Kabupaten Bandung, Jawa Barat, Indonesia',
            'Situ Cileunca, Kabupaten Bandung, Jawa Barat, Indonesia',
        ], $queries);
        Sleep::assertSleptTimes(1);
    }

    public function test_missing_district_can_be_auto_selected_but_explicit_conflicts_and_missing_city_or_province_cannot(): void
    {
        $base = ['country_code' => 'id', 'state' => 'Jawa Barat', 'county' => 'Kabupaten Bogor'];
        Http::fake(['*' => Http::response([
            $this->nominatimResult(['lat' => '-6.51', 'address' => $base]),
            $this->nominatimResult(['lat' => '-6.52', 'address' => [...$base, 'city_district' => 'Ciawi']]),
            $this->nominatimResult(['lat' => '-6.53', 'address' => [...$base, 'county' => 'Kabupaten Bandung']]),
            $this->nominatimResult(['lat' => '-6.54', 'address' => [...$base, 'state' => 'Jawa Tengah']]),
            $this->nominatimResult(['lat' => '-6.55', 'address' => ['country_code' => 'id', 'county' => 'Kabupaten Bogor']]),
            $this->nominatimResult(['lat' => '-6.56', 'address' => ['country_code' => 'id', 'state' => 'Jawa Barat']]),
        ])]);
        $response = $this->postJson('/admin/farm-management/geocode', $this->payload())->assertOk();
        $response->assertJsonPath('data.0.matches_region', false)->assertJsonPath('data.0.can_auto_select', true)
            ->assertJsonPath('data.0.approximate', true);
        foreach (range(1, 5) as $index) {
            $response->assertJsonPath("data.{$index}.can_auto_select", false);
        }
    }

    public function test_empty_address_and_place_queries_fall_back_to_a_labeled_region_with_rate_limited_requests(): void
    {
        // Even an unexpectedly specific upstream type must remain approximate at this stage.
        Http::fakeSequence()->push([])->push([])->push([$this->nominatimResult()]);
        $this->postJson('/admin/farm-management/geocode', $this->payload())->assertOk()
            ->assertJsonPath('data.0.search_stage', 'region')->assertJsonPath('data.0.approximate', true)
            ->assertJsonPath('data.0.matches_region', true);
        Http::assertSentCount(3);
        $this->assertSame([
            'Jalan Raya Sukamakmur, Sukamakmur, Kabupaten Bogor, Jawa Barat, Indonesia',
            'Raya Sukamakmur, Kabupaten Bogor, Jawa Barat, Indonesia',
            'Sukamakmur, Kabupaten Bogor, Jawa Barat, Indonesia',
        ], Http::recorded()->map(fn ($pair) => $pair[0]['q'])->all());
        Sleep::assertSlept(fn ($duration) => $duration->totalMicroseconds >= 1000000, 2);
        $this->postJson('/admin/farm-management/geocode', $this->payload())->assertOk();
        Http::assertSentCount(3);
    }

    public function test_cached_region_results_can_be_reused_after_a_different_address_misses(): void
    {
        Http::fakeSequence()->push([])->push([])->push([$this->nominatimResult()])->push([])->push([]);
        $this->postJson('/admin/farm-management/geocode', $this->payload())->assertOk();
        Cache::store('array')->forget('farm-geocoding:nominatim:next');
        $this->postJson('/admin/farm-management/geocode', $this->payload(['address' => 'Jalan Kebun Utara']))->assertOk()
            ->assertJsonPath('data.0.search_stage', 'region');
        Http::assertSentCount(5);
    }

    public function test_an_upstream_failure_during_fallback_stops_searching_and_old_cached_misses_are_ignored(): void
    {
        $query = 'Jalan Raya Sukamakmur, Sukamakmur, Kabupaten Bogor, Jawa Barat, Indonesia';
        $oldKey = 'farm-geocoding:v1:'.hash('sha256', rtrim(config('services.nominatim.url'), '/').'/search|'.mb_strtolower($query));
        Cache::store('array')->put($oldKey, [], 300);
        Http::fakeSequence()->push([])->push([], 503);
        $this->postJson('/admin/farm-management/geocode', $this->payload())->assertStatus(503);
        Http::assertSentCount(2);
    }

    public function test_identical_searches_are_not_sent_again_at_later_stages(): void
    {
        Http::fake(['*' => Http::response([])]);
        $this->postJson('/admin/farm-management/geocode', $this->payload(['address' => 'Sukamakmur']))
            ->assertOk()->assertJsonCount(0, 'data');
        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    public function test_selected_and_manually_corrected_coordinates_persist_through_the_existing_form(): void
    {
        $data = [...$this->payload(), 'name' => 'Kebun Sukamakmur', 'land_tenure' => 'owned', 'total_area_ha' => '2',
            'district' => 'Sukamakmur', 'city' => 'Kabupaten Bogor', 'province' => 'Jawa Barat',
            'latitude' => '-6.5791234', 'longitude' => '107.0023456'];
        $this->post('/admin/farm-management', $data)->assertSessionHasNoErrors()->assertRedirect();
        $farm = Farm::query()->firstOrFail();
        $this->patch('/admin/farm-management/'.$farm->id, ['latitude' => '-6.5792000', 'longitude' => '107.0024000'])->assertSessionHasNoErrors();
        $this->assertSame('-6.5792000', $farm->fresh()->latitude);
        $this->assertSame('107.0024000', $farm->fresh()->longitude);
        $this->patch('/admin/farm-management/'.$farm->id, ['latitude' => null, 'longitude' => null])->assertSessionHasNoErrors();
        $this->assertNull($farm->fresh()->latitude);
    }
}
