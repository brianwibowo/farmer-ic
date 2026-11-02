<?php

namespace App\Services;

use App\Models\AdministrativeRegion;
use App\Models\Team;
use App\Models\User;

class AdministrativeRegionService
{
    /** @return list<array{code: string, name: string, parent_code: string|null}> */
    public function options(int $level, ?string $parentCode, User $actor, Team $team): array
    {
        $query = AdministrativeRegion::visibleTo($actor, $team)->where('level', $level);
        if ($level === 1) {
            $query->whereNull('parent_code');
        } else {
            $query->where('parent_code', $parentCode)->whereIn('parent_code',
                AdministrativeRegion::visibleTo($actor, $team)->where('level', $level - 1)->select('code'));
        }

        $options = $query->orderBy('name')->orderBy('code')->get(['code', 'name', 'parent_code'])
            ->map(fn (AdministrativeRegion $row): array => ['code' => (string) $row->code,
                'name' => (string) $row->name, 'parent_code' => $row->parent_code === null ? null : (string) $row->parent_code])
            ->values()->all();

        return array_values($options);
    }

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
