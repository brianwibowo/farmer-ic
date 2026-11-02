<?php

namespace App\Services;

use App\Models\AdministrativeRegion;
use App\Models\Team;
use App\Models\User;

class AdministrativeRegionService
{
    /** @return array{code: string, district: string, city: string, city_code: string, province: string, province_code: string}|null */
    public function district(string $code, User $actor, Team $team): ?array
    {
        $row = AdministrativeRegion::visibleTo($actor, $team)->where('administrative_regions.level', 3)
            ->where('administrative_regions.code', $code)
            ->join('administrative_regions as city', 'city.code', '=', 'administrative_regions.parent_code')
            ->join('administrative_regions as province', 'province.code', '=', 'city.parent_code')
            ->first(['administrative_regions.code', 'administrative_regions.name as district', 'city.code as city_code', 'city.name as city', 'province.code as province_code', 'province.name as province']);

        return $row === null ? null : $this->shape($row);
    }

    /** @return list<array<string, mixed>> */
    public function search(string $search, User $actor, Team $team): array
    {
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        $results = AdministrativeRegion::visibleTo($actor, $team)->where('administrative_regions.level', 3)
            ->join('administrative_regions as city', 'city.code', '=', 'administrative_regions.parent_code')
            ->join('administrative_regions as province', 'province.code', '=', 'city.parent_code')
            ->where(fn ($q) => $q->where('administrative_regions.name', 'ilike', $pattern)->orWhere('city.name', 'ilike', $pattern))
            ->orderBy('administrative_regions.name')->orderBy('city.name')->orderBy('administrative_regions.code')->limit(25)
            ->get(['administrative_regions.code', 'administrative_regions.name as district', 'city.code as city_code', 'city.name as city', 'province.code as province_code', 'province.name as province'])
            ->map(fn (AdministrativeRegion $row) => $this->shape($row))->values()->all();

        return array_values($results);
    }

    /** @return array{code: string, district: string, city: string, city_code: string, province: string, province_code: string} */
    private function shape(AdministrativeRegion $row): array
    {
        return ['code' => (string) $row->getAttribute('code'), 'district' => (string) $row->getAttribute('district'),
            'city' => (string) $row->getAttribute('city'), 'city_code' => (string) $row->getAttribute('city_code'),
            'province' => (string) $row->getAttribute('province'), 'province_code' => (string) $row->getAttribute('province_code')];
    }
}
