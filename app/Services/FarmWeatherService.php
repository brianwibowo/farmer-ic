<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class FarmWeatherService
{
    /** @return array<string, mixed> */
    public function forecast(User $actor, Team $team, Farm $farm): array
    {
        // Scope before reading coordinates or cached weather, including for Superadmin.
        $farm = Farm::query()->visibleTo($actor, $team)->where('team_id', $team->id)->findOrFail($farm->id);
        $result = ['farmId' => (string) $farm->id, 'status' => 'missing_coordinates', 'forecast' => null];
        if ($farm->latitude === null || $farm->longitude === null
            || abs((float) $farm->latitude) > 90 || abs((float) $farm->longitude) > 180) {
            return $result;
        }

        // Coordinates are part of the key so editing a farm cannot reuse its previous location.
        $key = 'farm-weather:v1:'.hash('sha256', $farm->id.':'.$farm->latitude.':'.$farm->longitude);
        $forecast = Cache::get($key);
        if (is_array($forecast) && CarbonImmutable::parse($forecast['fetchedAt'])->addMinutes(15)->isFuture()) {
            return [...$result, 'status' => 'ready', 'forecast' => $forecast];
        }

        if (! Cache::has($key.':failed')) {
            try {
                $settings = config('services.open_meteo');
                $payload = Http::acceptJson()->connectTimeout(3)->timeout(8)
                    ->get($settings['url'], array_filter([
                        'latitude' => (float) $farm->latitude,
                        'longitude' => (float) $farm->longitude,
                        'current' => 'temperature_2m,relative_humidity_2m,is_day,weather_code,wind_speed_10m',
                        'hourly' => 'temperature_2m,precipitation_probability,weather_code,is_day',
                        'forecast_hours' => 7,
                        'timezone' => 'auto',
                        'timeformat' => 'unixtime',
                        'wind_speed_unit' => 'kmh',
                        'apikey' => $settings['key'],
                    ], fn ($value) => $value !== null && $value !== ''))
                    ->throw()->json();

                $forecast = $this->normalize($payload);
                // Derived provider data only; historical weather_records remain untouched.
                Cache::put($key, $forecast, now()->addHours(6));
                Cache::forget($key.':failed');

                return [...$result, 'status' => 'ready', 'forecast' => $forecast];
            } catch (Throwable $exception) {
                Log::warning('Open-Meteo forecast unavailable.', ['exception' => $exception::class]);
                // Avoid repeatedly hitting an unavailable provider on manual retries.
                Cache::put($key.':failed', true, 60);
            }
        }

        return [...$result, 'status' => $forecast === null ? 'unavailable' : 'stale', 'forecast' => $forecast];
    }

    /** @return array<string, mixed> */
    private function normalize(mixed $payload): array
    {
        $data = Validator::make(is_array($payload) ? $payload : [], [
            'timezone' => ['required', 'timezone:all'],
            'current.time' => ['required', 'integer'],
            'current.temperature_2m' => ['required', 'numeric', 'between:-100,70'],
            'current.relative_humidity_2m' => ['required', 'numeric', 'between:0,100'],
            'current.wind_speed_10m' => ['required', 'numeric', 'between:0,500'],
            'current.weather_code' => ['required', 'integer', 'between:0,99'],
            'current.is_day' => ['required', 'integer', 'in:0,1'],
            'hourly.time' => ['required', 'array', 'min:4'],
            'hourly.time.*' => ['required', 'integer'],
            'hourly.temperature_2m' => ['required', 'array'],
            'hourly.temperature_2m.*' => ['nullable', 'numeric', 'between:-100,70'],
            'hourly.precipitation_probability' => ['required', 'array'],
            'hourly.precipitation_probability.*' => ['nullable', 'numeric', 'between:0,100'],
            'hourly.weather_code' => ['required', 'array'],
            'hourly.weather_code.*' => ['nullable', 'integer', 'between:0,99'],
            'hourly.is_day' => ['required', 'array'],
            'hourly.is_day.*' => ['nullable', 'integer', 'in:0,1'],
        ])->validate();

        $current = $data['current'];
        $hourly = $data['hourly'];
        $hours = [];
        $rainProbability = null;
        foreach ($hourly['time'] as $index => $time) {
            $rain = $hourly['precipitation_probability'][$index] ?? null;
            if ($time <= $current['time'] && $time + 3600 > $current['time']) {
                $rainProbability = $rain === null ? null : (float) $rain;
            }
            if ($time <= $current['time'] || count($hours) >= 3) {
                continue;
            }
            $temperature = $hourly['temperature_2m'][$index] ?? null;
            $code = $hourly['weather_code'][$index] ?? null;
            $isDay = $hourly['is_day'][$index] ?? null;
            $hours[] = [
                'time' => CarbonImmutable::createFromTimestampUTC($time)->toISOString(),
                'temperature' => $temperature === null ? null : (float) $temperature,
                'rainProbability' => $rain === null ? null : (float) $rain,
                'code' => $code === null ? null : (int) $code,
                'isDay' => $isDay === null ? null : (bool) $isDay,
            ];
        }

        return [
            'timezone' => $data['timezone'],
            'fetchedAt' => CarbonImmutable::now()->toISOString(),
            'observedAt' => CarbonImmutable::createFromTimestampUTC($current['time'])->toISOString(),
            'temperature' => (float) $current['temperature_2m'],
            'humidity' => (float) $current['relative_humidity_2m'],
            'windSpeed' => (float) $current['wind_speed_10m'],
            'code' => (int) $current['weather_code'],
            'isDay' => (bool) $current['is_day'],
            'rainProbability' => $rainProbability,
            'hours' => $hours,
        ];
    }
}
