<?php

namespace App\Services\Owner;

use App\Models\Farm;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

class HarvestProjectionService
{
    private const RATES = [
        'jagung' => ['name' => 'Jagung', 'ton_per_ha' => 40, 'cycles_per_year' => 3],
        'rumput' => ['name' => 'Rumput', 'ton_per_ha' => 100, 'cycles_per_year' => 5],
        'sorgum' => ['name' => 'Sorgum', 'ton_per_ha' => 45, 'cycles_per_year' => 3],
    ];

    /** @return array<string, mixed> */
    public function summary(User $actor, Team $team, string $period = 'cycle', ?string $farmId = null): array
    {
        $farms = Farm::visibleTo($actor, $team)->where('team_id', $team->id)
            ->when($farmId !== null, fn ($query) => $query->whereKey($farmId))
            ->orderBy('name')->orderBy('id')->get(['id', 'name', 'total_area_ha']);
        $farmAreas = $farms->pluck('total_area_ha', 'id');
        $cycles = PlantingCycle::visibleTo($actor, $team)
            ->whereIn('farm_id', $farms->modelKeys())->where('status', 'active')
            ->with(['variety' => fn ($query) => $query->visibleTo($actor, $team)
                ->with(['commodity' => fn ($query) => $query->visibleTo($actor, $team)])])
            ->get();
        // Hidden crop relationships cannot contribute to a labelled card or row.
        $identified = $cycles->filter(fn (PlantingCycle $cycle) => $cycle->variety?->commodity !== null);
        $byFarm = $identified->groupBy('farm_id');
        $rows = [];
        foreach ($farms as $farm) {
            $groups = $byFarm->get($farm->id, collect())
                ->groupBy(fn (PlantingCycle $cycle) => $cycle->variety->commodity->id)
                ->sortBy(fn (Collection $group) => $group->first()->variety->commodity->name);
            foreach ($groups as $commodityId => $group) {
                $name = $group->first()->variety->commodity->name;
                $rows[] = [
                    'key' => $farm->id.':'.$commodityId,
                    'farm_id' => $farm->id,
                    'farm_name' => $farm->name,
                    'crop_name' => $name,
                    ...$this->projection($group, $farmAreas, $period, self::RATES[mb_strtolower(trim($name))] ?? null),
                ];
            }
            if ($groups->isEmpty()) {
                $rows[] = [
                    'key' => $farm->id, 'farm_id' => $farm->id, 'farm_name' => $farm->name,
                    'crop_name' => null, 'projection_ton' => null, 'missing_count' => 0, 'batch_count' => 0,
                ];
            }
        }

        return [
            'period' => $period,
            'farm_id' => $farmId,
            'farm_count' => $farms->count(),
            'cards' => collect(self::RATES)->map(fn (array $rate, string $key) => [
                'key' => $key, ...$rate,
                ...$this->projection($identified->filter(fn (PlantingCycle $cycle) => mb_strtolower(trim($cycle->variety->commodity->name)) === $key), $farmAreas, $period, $rate),
            ])->values()->all(),
            'rows' => $rows,
            'unidentified_batches' => $cycles->count() - $identified->count(),
        ];
    }

    /**
     * @param  Collection<int, PlantingCycle>  $cycles
     * @param  Collection<string, string>  $farmAreas
     * @param  array{name: string, ton_per_ha: int, cycles_per_year: int}|null  $rate
     * @return array{projection_ton: ?float, missing_count: int, batch_count: int}
     */
    private function projection(Collection $cycles, Collection $farmAreas, string $period, ?array $rate): array
    {
        // Whole-farm area contributes once per crop, even with multiple active
        // batches. Crop cards and farm rows use this exact same calculator.
        $values = $cycles->pluck('farm_id')->unique()->map(function (string $farmId) use ($farmAreas, $period, $rate): ?float {
            $area = $farmAreas->get($farmId);
            if ($area === null || $rate === null) {
                return null;
            }

            return (float) $area * $rate['ton_per_ha'] * ($period === 'year' ? $rate['cycles_per_year'] : 1);
        });
        $known = $values->filter(fn ($value) => $value !== null);

        return [
            'projection_ton' => $cycles->isEmpty() ? 0.0 : ($known->isEmpty() ? null : round($known->sum(), 3)),
            'missing_count' => $values->count() - $known->count(),
            'batch_count' => $cycles->count(),
        ];
    }
}
