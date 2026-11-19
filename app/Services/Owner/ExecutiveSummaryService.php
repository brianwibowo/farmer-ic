<?php

namespace App\Services\Owner;

use App\Models\Commodity;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\FarmLegality;
use App\Models\HarvestDetail;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;

class ExecutiveSummaryService
{
    /** All records and relationship labels are scoped before aggregation. */
    public function summary(User $actor, Team $team, array $input): array
    {
        $now = CarbonImmutable::now('Asia/Jakarta');
        $year = (int) ($input['year'] ?? $now->year);
        $filters = array_merge(['year' => $year, 'quarter' => 'all', 'partner' => '', 'region' => '', 'farm' => '', 'commodity' => ''], $input);
        $filters['year'] = $year;
        foreach (['partner', 'region', 'farm', 'commodity'] as $key) {
            $filters[$key] = $filters[$key] ?? '';
        }
        $farms = Farm::visibleTo($actor, $team)->where('team_id', $team->id)->orderBy('name')->orderBy('id')->get();
        $commodities = Commodity::visibleTo($actor, $team)->orderBy('name')->get()->keyBy('id');
        $blocks = FarmBlock::visibleTo($actor, $team)->whereIn('farm_id', $farms->modelKeys())->get();
        $legalities = FarmLegality::visibleTo($actor, $team)->whereIn('farm_id', $farms->modelKeys())->get()->keyBy('farm_id');
        $cycles = PlantingCycle::visibleTo($actor, $team)->whereIn('farm_id', $farms->modelKeys())
            ->whereIn('status', ['planned', 'active', 'harvested'])
            ->with([
                'variety' => fn ($q) => $q->visibleTo($actor, $team)
                    ->with(['commodity' => fn ($q) => $q->visibleTo($actor, $team)]),
            ])->get();
        $activities = FarmActivity::visibleTo($actor, $team)->whereIn('farm_id', $farms->modelKeys())
            ->where('activity_date', '<=', $now)->where('status', 'completed')->get()->keyBy('id');
        $harvests = HarvestDetail::visibleTo($actor, $team)->whereIn('activity_id', $activities->modelKeys())->get();
        $cycleById = $cycles->keyBy('id');
        $harvests = $harvests->filter(function ($detail) use ($activities, $cycleById) {
            $activity = $activities->get($detail->activity_id);
            $cycle = $cycleById->get($activity->planting_cycle_id);

            return ! $activity->planting_cycle_id || ($cycle && $cycle->farm_id === $activity->farm_id);
        });
        $years = collect([$now->year, $year])
            ->merge($cycles->flatMap(fn ($cycle) => [$cycle->planted_at?->year, $cycle->estimated_harvest_at?->year]))
            ->merge($harvests->map(fn ($detail) => $activities[$detail->activity_id]->activity_date->setTimezone('Asia/Jakarta')->year))
            ->filter()->unique()->sortDesc()->values();
        $periodStart = CarbonImmutable::create($year, 1, 1, 0, 0, 0, 'Asia/Jakarta');
        $periodEnd = $periodStart->addYear();
        if ($filters['quarter'] !== 'all') {
            $quarter = (int) substr($filters['quarter'], 1);
            $periodStart = $periodStart->addMonths(($quarter - 1) * 3);
            $periodEnd = $periodStart->addMonths(3);
        }
        $farmOptions = $farms->map(function ($farm) use ($legalities, $cycles, $commodities, $harvests, $activities, $blocks) {
            $party = trim((string) ($farm->land_tenure_party ?: $legalities->get($farm->id)?->institution));
            $cropIds = $cycles->toBase()->where('farm_id', $farm->id)->map(fn ($cycle) => $cycle->variety?->commodity?->id)
                ->merge($blocks->where('farm_id', $farm->id)->pluck('commodity_id')->filter(fn ($id) => $commodities->has($id)))
                // Older local schemas may still store the default on farms; current schemas store it on blocks.
                ->push($commodities->has($farm->getAttribute('commodity_id')) ? $farm->getAttribute('commodity_id') : null)->filter()->unique()->values();
            if (
                $cycles->where('farm_id', $farm->id)->contains(fn ($cycle) => $cycle->variety?->commodity === null)
                || $harvests->contains(fn ($detail) => $activities[$detail->activity_id]->farm_id === $farm->id
                    && ! $activities[$detail->activity_id]->planting_cycle_id && $cropIds->count() !== 1)
            ) {
                $cropIds->push('');
            }

            return [
                'id' => $farm->id,
                'name' => $farm->name,
                'city' => $farm->city ?? '',
                'region' => $farm->province ?? '',
                'partnerId' => $party ?: '__unrecorded__',
                'partnerName' => $party ?: 'Belum tercatat',
                'landCategory' => $farm->land_tenure ?? 'unknown',
                'areaHa' => (float) $farm->total_area_ha,
                'commodityIds' => $cropIds->all(),
                'latitude' => $farm->latitude === null ? null : (float) $farm->latitude,
                'longitude' => $farm->longitude === null ? null : (float) $farm->longitude,
                'detail_url' => route('owner.farms.show', $farm, false),
            ];
        });
        $selectedFarms = $farmOptions->filter(fn ($farm) => (! $filters['farm'] || $farm['id'] === $filters['farm'])
            && (! $filters['partner'] || $farm['partnerId'] === $filters['partner'])
            && (! $filters['region'] || $farm['region'] === $filters['region'])
            && (! $filters['commodity'] || in_array($filters['commodity'], $farm['commodityIds'], true)));
        $rows = [];
        foreach ($selectedFarms as $farm) {
            $farmCycles = $cycles->where('farm_id', $farm['id']);
            $farmHarvests = $harvests->filter(fn ($detail) => $activities[$detail->activity_id]->farm_id === $farm['id']);
            $cropIds = $farm['commodityIds'] ?: [''];
            foreach ($cropIds as $cropId) {
                if ($filters['commodity'] && $cropId !== $filters['commodity']) {
                    continue;
                }
                $crop = $commodities->get($cropId);
                $group = $farmCycles->filter(fn ($cycle) => ($cycle->variety?->commodity?->id ?? '') === $cropId);
                // Unidentified cycles remain explicit rather than being attributed to the farm's default crop.
                if ($cropId === '') {
                    $group = $farmCycles->filter(fn ($cycle) => $cycle->variety?->commodity === null);
                }
                $projected = $group->filter(fn ($cycle) => $cycle->estimated_harvest_at !== null
                    && $cycle->estimated_harvest_at->toDateString() >= $periodStart->toDateString()
                    && $cycle->estimated_harvest_at->toDateString() < $periodEnd->toDateString());
                $missing = $projected->whereNull('estimated_weight_ton')->count()
                    + $group->filter(fn ($cycle) => $cycle->estimated_harvest_at === null && ($cycle->planted_at?->year === $year || $cycle->status === 'planned'))->count();
                $actual = $farmHarvests->filter(function ($detail) use ($activities, $cycleById, $farm, $cropId, $periodStart, $periodEnd) {
                    $activity = $activities[$detail->activity_id];
                    $cycle = $cycleById->get($activity->planting_cycle_id);
                    $activityCrop = $cycle ? ($cycle->variety?->commodity?->id ?? '') : (count($farm['commodityIds']) === 1 ? $farm['commodityIds'][0] : '');

                    return $activityCrop === $cropId
                        && $activity->activity_date->greaterThanOrEqualTo($periodStart)
                        && $activity->activity_date->lessThan($periodEnd);
                });
                $planted = $group->filter(fn ($cycle) => $cycle->status === 'active' && $cycle->planted_at !== null && $cycle->planted_at->toDateString() <= $now->toDateString())->sum('planted_area_ha');
                $rows[] = [
                    'id' => $farm['id'].':'.$cropId,
                    'farmId' => $farm['id'],
                    'year' => $year,
                    'quarter' => $filters['quarter'],
                    'farm' => $farm,
                    'partner' => ['id' => $farm['partnerId'], 'name' => $farm['partnerName']],
                    'commodity' => ['id' => $cropId, 'name' => $crop?->name ?? 'Belum teridentifikasi', 'pricing' => null],
                    'plantedHa' => (float) $planted,
                    'harvestedTon' => (float) $actual->sum('gross_weight_ton'),
                    'projectedTon' => $missing ? null : (float) $projected->sum('estimated_weight_ton'),
                    'projectionMissing' => $missing,
                    'stockTon' => null,
                    'monthlyDeliveredTon' => 0,
                    'status' => $planted > 0 ? 'Tanam aktif' : 'Tidak ada tanam aktif',
                ];
            }
        }

        return [
            'updatedAt' => $now->toIso8601String(),
            'snapshotAt' => $now->toDateString(),
            'defaultYear' => $now->year,
            'referenceMonth' => $now->month,
            'filters' => $filters,
            'years' => $years->all(),
            'farms' => $farmOptions->values()->all(),
            'partners' => $farmOptions->map(fn ($farm) => ['id' => $farm['partnerId'], 'name' => $farm['partnerName']])->unique('id')->values()->all(),
            'commodities' => $commodities->only($farmOptions->flatMap(fn ($farm) => $farm['commodityIds'])->all())->map(fn ($crop) => ['id' => $crop->id, 'name' => $crop->name])->values()->all(),
            'clients' => [],
            'rows' => $rows,
        ];
    }
}
