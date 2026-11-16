<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository as CacheStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FarmGeocodingService
{
    public function __construct(private readonly AdministrativeRegionService $regions) {}

    /** @return list<array{label: string, latitude: float, longitude: float, approximate: bool, matches_region: bool, can_auto_select: bool, search_stage: string}> */
    public function search(string $address, string $districtCode, User $actor, Team $team): array
    {
        $region = $this->regions->district($districtCode, $actor, $team);
        if (! $region) {
            throw ValidationException::withMessages(['district_code' => 'Pilih kecamatan dari daftar wilayah terlebih dahulu.']);
        }

        $queries = $this->queries($address, $region);
        $endpoint = rtrim(config('services.nominatim.url'), '/').'/search';
        $cache = Cache::store(config('services.nominatim.cache_store'));
        // A new strategy must not reuse cached misses from the old single-query search.
        $key = 'farm-geocoding:v3:'.hash('sha256', $endpoint.'|'.$districtCode.'|'.Str::lower(json_encode($queries)));
        if (($cached = $cache->get($key)) !== null) {
            return $cached;
        }

        // Covers up to three six-second requests and two cooldowns across all workers.
        $lock = $cache->lock('farm-geocoding:nominatim:lock', 40);
        if (! $lock->get()) {
            throw $this->busy();
        }
        try {
            if (($cached = $cache->get($key)) !== null) {
                return $cached;
            }
            $requested = false;
            $results = [];
            foreach ($queries as $stage => $query) {
                $rows = $this->lookup($query, $endpoint, $cache, $requested);
                $results = $this->normalize($rows, $region, $stage);
                if ($results !== []) {
                    break;
                }
            }
            $cache->put($key, $results, $results === [] ? 300 : 86400 * 7);

            return $results;
        } finally {
            $lock->release();
        }
    }

    /** @return array<string, string> */
    private function queries(string $address, array $region): array
    {
        $place = Str::squish(explode(',', $address)[0]);
        $place = preg_replace('/^(?:jl\.?|jln\.?|jalan)\s+/iu', '', $place);
        $queries = [
            'address' => $this->withContext($address, [$region['district'], $region['city'], $region['province'], 'Indonesia']),
            'place' => mb_strlen($place) >= 3 ? $this->withContext($place, [$region['city'], $region['province'], 'Indonesia']) : '',
            'region' => implode(', ', [$region['district'], $region['city'], $region['province'], 'Indonesia']),
        ];
        $seen = [];

        return array_filter($queries, function (string $query) use (&$seen): bool {
            $key = Str::lower($query);
            if ($query === '' || isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        });
    }

    private function withContext(string $address, array $context): string
    {
        $parts = [Str::squish($address)];
        $existing = array_map(fn (string $part) => Str::lower(trim($part)), explode(',', $address));
        foreach ($context as $name) {
            if (! in_array(Str::lower($name), $existing, true)) {
                $parts[] = $name;
            }
        }

        return implode(', ', $parts);
    }

    /** Fetch each distinct query at most once; empty results advance the strategy, errors do not. */
    private function lookup(string $query, string $endpoint, CacheStore $cache, bool &$requested): array
    {
        $key = 'farm-geocoding:query:v2:'.hash('sha256', $endpoint.'|'.Str::lower($query));
        if (($cached = $cache->get($key)) !== null) {
            return $cached;
        }
        $remaining = (float) $cache->get('farm-geocoding:nominatim:next', 0) - microtime(true);
        if ($remaining > 0) {
            if (! $requested) {
                throw $this->busy();
            }
            Sleep::usleep((int) ceil($remaining * 1000000));
        }
        $requested = true;
        $cache->put('farm-geocoding:nominatim:next', microtime(true) + 1.1, 60);
        try {
            $response = Http::acceptJson()
                ->withUserAgent(config('services.nominatim.user_agent'))
                ->connectTimeout(2)->timeout(6)
                ->get($endpoint, [
                    'q' => $query, 'format' => 'jsonv2', 'countrycodes' => 'id',
                    'addressdetails' => 1, 'limit' => 5, 'accept-language' => 'id,en',
                ]);
        } catch (ConnectionException) {
            throw new HttpException(503, 'Pencarian lokasi belum dapat terhubung. Coba lagi atau pilih pin secara manual.');
        } finally {
            $cache->put('farm-geocoding:nominatim:next', microtime(true) + 1.1, 60);
        }
        if ($response->status() === 429) {
            $cache->put('farm-geocoding:nominatim:next', microtime(true) + 60, 120);
            throw $this->busy(60);
        }
        $rows = $response->json();
        if (! $response->successful() || ! is_array($rows) || ! array_is_list($rows)) {
            throw new HttpException(503, 'Layanan pencarian lokasi sedang tidak tersedia. Coba lagi atau pilih pin secara manual.');
        }
        $cache->put($key, $rows, $rows === [] ? 300 : 86400 * 7);

        return $rows;
    }

    private function busy(int $seconds = 2): HttpException
    {
        return new HttpException(429, 'Pencarian lokasi sedang sibuk. Tunggu sebentar lalu pilih Cari ulang dari alamat, atau tentukan pin secara manual.', headers: ['Retry-After' => (string) $seconds]);
    }

    private function normalize(array $rows, array $region, string $stage): array
    {
        $results = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_numeric($row['lat'] ?? null) || ! is_numeric($row['lon'] ?? null)
                || ! is_string($row['display_name'] ?? null) || trim($row['display_name']) === '') {
                continue;
            }
            $lat = (float) $row['lat'];
            $lon = (float) $row['lon'];
            $address = $row['address'] ?? [];
            if (! is_finite($lat) || ! is_finite($lon) || abs($lat) > 90 || abs($lon) > 180
                || ! is_array($address) || ($address['country_code'] ?? '') !== 'id') {
                continue;
            }
            $provinceMatches = $this->matches($address, ['state'], $region['province']);
            $cityMatches = $this->matches($address, ['county', 'city', 'municipality', 'state_district'], $region['city']);
            $districtMatches = $this->matches($address, ['city_district', 'district', 'suburb', 'town', 'municipality', 'county'], $region['district']);
            // County can name the kabupaten, so its presence alone is not district evidence.
            $hasDistrict = collect(['city_district', 'district', 'suburb', 'town', 'municipality'])
                ->contains(fn (string $key) => is_string($address[$key] ?? null) && trim($address[$key]) !== '');
            $matches = $provinceMatches && $cityMatches && $districtMatches;
            $canAutoSelect = $provinceMatches && $cityMatches && ($districtMatches || ! $hasDistrict);
            $specific = in_array($row['addresstype'] ?? '', ['house', 'building', 'road', 'amenity'], true)
                || in_array($row['category'] ?? '', ['building', 'highway'], true);
            $results[round($lat, 7).','.round($lon, 7)] = [
                'label' => $row['display_name'], 'latitude' => round($lat, 7), 'longitude' => round($lon, 7),
                'approximate' => $stage === 'region' || ! $specific || ($canAutoSelect && ! $matches),
                'matches_region' => $matches, 'can_auto_select' => $canAutoSelect,
                'search_stage' => $stage,
            ];
        }

        return array_values($results);
    }

    private function matches(array $address, array $keys, string $expected): bool
    {
        $normalize = fn (string $value): string => trim(preg_replace('/\b(kabupaten|kab|kota|kecamatan|kec|provinsi)\b\.?/u', '', Str::lower(Str::squish($value))));
        foreach ($keys as $key) {
            if (is_string($address[$key] ?? null) && $normalize($address[$key]) === $normalize($expected)) {
                return true;
            }
        }

        return false;
    }
}
