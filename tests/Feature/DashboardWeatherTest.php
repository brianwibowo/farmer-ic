<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Farm;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Integration tests on the guarded PostgreSQL test database; provider requests are faked. */
class DashboardWeatherTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Team $team;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(5, 15));
        Http::preventStrayRequests();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->team = Team::factory()->create(['is_personal' => false]);
        $this->team->members()->attach($this->admin, ['role' => TeamRole::Member->value]);
        $this->admin->switchTeam($this->team);
        $this->farm = new Farm(['name' => 'Kebun Cileuksa', 'total_area_ha' => 12.5, 'latitude' => -6.59, 'longitude' => 106.95]);
        $this->farm->forceFill(['team_id' => $this->team->id, 'created_by' => $this->admin->id])->save();
        $this->actingAs($this->admin);
    }

    public function test_weather_uses_persisted_coordinates_and_returns_current_and_three_future_hours(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->payload())]);

        $this->getJson($this->endpoint().'?latitude=10&longitude=20&team_id=999')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.farmId', $this->farm->id)->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.forecast.temperature', 29)->assertJsonPath('data.forecast.humidity', 82)
            ->assertJsonPath('data.forecast.timezone', 'Asia/Jakarta')
            ->assertJsonPath('data.forecast.rainProbability', 40)
            ->assertJsonCount(3, 'data.forecast.hours')
            ->assertJsonPath('data.forecast.hours.0.temperature', 30)
            ->assertJsonPath('data.forecast.hours.0.time', now()->startOfHour()->addHour()->toISOString());

        Http::assertSent(fn (Request $request): bool => $request['latitude'] === -6.59
            && $request['longitude'] === 106.95 && $request['timezone'] === 'auto'
            && $request['timeformat'] === 'unixtime' && $request['forecast_hours'] === 7);
    }

    public function test_cache_is_reused_for_fifteen_minutes_then_refreshed(): void
    {
        Http::fake(fn () => Http::response($this->payload()));
        $this->getJson($this->endpoint())->assertJsonPath('data.status', 'ready');
        $this->travel(14)->minutes();
        $this->getJson($this->endpoint())->assertJsonPath('data.status', 'ready');
        Http::assertSentCount(1);
        $this->travel(1)->minutes();
        $this->getJson($this->endpoint())->assertJsonPath('data.status', 'ready');
        Http::assertSentCount(2);
    }

    public function test_changing_coordinates_invalidates_cached_forecast(): void
    {
        Http::fake(fn () => Http::response($this->payload()));
        $this->getJson($this->endpoint())->assertOk();
        $this->farm->update(['latitude' => -7.1]);
        $this->getJson($this->endpoint())->assertOk();
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request['latitude'] === -7.1);
    }

    public function test_outage_marks_cached_data_stale_and_limits_retries_then_expires_it(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->payload())]);
        $original = $this->getJson($this->endpoint())->json('data.forecast.fetchedAt');
        $this->travel(15)->minutes();
        Http::fake(fn () => throw new ConnectionException('Provider unavailable'));
        $this->getJson($this->endpoint())->assertOk()->assertJsonPath('data.status', 'stale')
            ->assertJsonPath('data.forecast.fetchedAt', $original);
        $this->getJson($this->endpoint())->assertJsonPath('data.status', 'stale');
        $this->travel(6)->hours();
        $this->getJson($this->endpoint())->assertOk()->assertJsonPath('data.status', 'unavailable')
            ->assertJsonPath('data.forecast', null);
    }

    public function test_missing_coordinates_do_not_contact_provider(): void
    {
        $this->farm->update(['latitude' => null]);
        $this->getJson($this->endpoint())->assertOk()->assertJsonPath('data.status', 'missing_coordinates')
            ->assertJsonPath('data.forecast', null);
        Http::assertNothingSent();
    }

    public function test_invalid_provider_response_is_unavailable_and_retry_is_backed_off(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response(['error' => true])]);
        $this->getJson($this->endpoint())->assertOk()->assertJsonPath('data.status', 'unavailable');
        $this->getJson($this->endpoint())->assertOk()->assertJsonPath('data.forecast', null);
        Http::assertSentCount(1);
    }

    public function test_missing_hourly_values_stay_null_instead_of_becoming_zero(): void
    {
        $payload = $this->payload();
        $payload['hourly']['temperature_2m'][1] = null;
        $payload['hourly']['precipitation_probability'][0] = null;
        $payload['hourly']['precipitation_probability'][1] = null;
        Http::fake(['api.open-meteo.com/*' => Http::response($payload)]);
        $this->getJson($this->endpoint())->assertOk()->assertJsonPath('data.forecast.hours.0.temperature', null)
            ->assertJsonPath('data.forecast.hours.0.rainProbability', null)
            ->assertJsonPath('data.forecast.rainProbability', null);
    }

    public function test_guest_cannot_access_weather(): void
    {
        auth()->logout();
        $this->getJson($this->endpoint())->assertUnauthorized();
        Http::assertNothingSent();
    }

    /** @return array<string, array{string, int}> */
    public static function roles(): array
    {
        return ['admin' => ['admin', 200], 'superadmin' => ['superadmin', 200], 'owner' => ['owner', 403], 'petugas' => ['petugas', 403]];
    }

    #[DataProvider('roles')]
    public function test_role_admission_matches_admin_dashboard(string $role, int $status): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->payload())]);
        $user = User::factory()->create(['role' => $role]);
        $this->team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $user->switchTeam($this->team);
        $this->actingAs($user)->getJson($this->endpoint())->assertStatus($status);
        if ($status === 403) {
            Http::assertNothingSent();
        }
    }

    public function test_foreign_farm_is_denied_even_when_its_cache_is_warm(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->payload())]);
        $this->getJson($this->endpoint())->assertOk();
        $other = Team::factory()->create(['is_personal' => false]);
        $this->farm->forceFill(['team_id' => $other->id])->save();
        $this->getJson($this->endpoint())->assertForbidden();
        Http::assertSentCount(1);
    }

    public function test_superadmin_weather_still_follows_active_tenant(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $other = Team::factory()->create(['is_personal' => false]);
        $superadmin->forceFill(['current_team_id' => $other->id])->save();
        $this->actingAs($superadmin)->getJson($this->endpoint())->assertNotFound();
        Http::assertNothingSent();
        $superadmin->forceFill(['current_team_id' => null])->save();
        $this->actingAs($superadmin->fresh())->getJson($this->endpoint())->assertForbidden();
    }

    public function test_missing_view_grant_denies_weather_before_cache_or_provider(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->payload())]);
        $this->getJson($this->endpoint())->assertOk();
        $grants = config('access.roles.admin');
        unset($grants['farms.view']);
        config(['access.roles.admin' => $grants]);
        $this->getJson($this->endpoint())->assertForbidden();
        Http::assertSentCount(1);
    }

    public function test_petugas_weather_uses_assigned_farm_and_ignores_supplied_coordinates(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->payload())]);
        $petugas = User::factory()->create(['role' => 'petugas']);
        $this->team->members()->attach($petugas, ['role' => TeamRole::Member->value]);
        $petugas->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $petugas->switchTeam($this->team);

        $this->actingAs($petugas)->getJson('/petugas/weather/'.$this->farm->id.'?latitude=10&longitude=20&team_id=999')
            ->assertOk()->assertJsonPath('data.status', 'ready')->assertJsonPath('data.forecast.temperature', 29);
        Http::assertSent(fn (Request $request): bool => $request['latitude'] === -6.59 && $request['longitude'] === 106.95);
    }

    public function test_petugas_cannot_read_unassigned_or_cross_team_weather_even_with_warm_cache(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->payload())]);
        $this->getJson($this->endpoint())->assertOk();
        $petugas = User::factory()->create(['role' => 'petugas']);
        $this->team->members()->attach($petugas, ['role' => TeamRole::Member->value]);
        $petugas->switchTeam($this->team);
        $url = '/petugas/weather/'.$this->farm->id;
        $this->actingAs($petugas)->getJson($url.'?farm_id='.$this->farm->id)->assertForbidden();

        $petugas->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->actingAs($petugas->fresh())->getJson($url)->assertOk();
        $otherTeam = Team::factory()->create(['is_personal' => false]);
        $this->farm->forceFill(['team_id' => $otherTeam->id])->save();
        $this->actingAs($petugas->fresh())->getJson($url)->assertForbidden();
        Http::assertSentCount(1);
    }

    public function test_petugas_weather_denies_missing_grant_and_other_portal_roles(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->payload())]);
        $url = '/petugas/weather/'.$this->farm->id;
        $this->getJson($url)->assertForbidden();
        $petugas = User::factory()->create(['role' => 'petugas']);
        $this->team->members()->attach($petugas, ['role' => TeamRole::Member->value]);
        $petugas->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $petugas->switchTeam($this->team);
        $grants = config('access.roles.petugas');
        unset($grants['farms.view']);
        config(['access.roles.petugas' => $grants]);
        $this->actingAs($petugas)->getJson($url)->assertForbidden();
        auth()->logout();
        $this->getJson($url)->assertUnauthorized();
        Http::assertNothingSent();
    }

    private function endpoint(): string
    {
        return '/admin/dashboard/weather/'.$this->farm->id;
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'timezone' => 'Asia/Jakarta',
            'current' => ['time' => now()->timestamp, 'temperature_2m' => 29, 'relative_humidity_2m' => 82,
                'wind_speed_10m' => 8, 'weather_code' => 3, 'is_day' => 1],
            'hourly' => [
                'time' => array_map(fn (int $hour): int => now()->startOfHour()->addHours($hour)->timestamp, range(0, 6)),
                'temperature_2m' => [29, 30, 31, 30, 29, 28, 27],
                'precipitation_probability' => [40, 50, 60, 70, 60, 50, 40],
                'weather_code' => [3, 3, 61, 61, 3, 3, 3],
                'is_day' => [1, 1, 1, 1, 1, 1, 1],
            ],
        ];
    }
}
