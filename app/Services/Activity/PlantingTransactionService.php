<?php

namespace App\Services\Activity;

use App\Models\ActivityType;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator as ArrayPaginator;

/** Read-only operational ledger for planting reports grouped by subblock. */
class PlantingTransactionService
{
    /** @return Collection<int, Farm> */
    public function farms(User $actor, Team $team): Collection
    {
        return Farm::visibleTo($actor, $team)->orderBy('name')->get(['id', 'name']);
    }

    /** @return Collection<int, FarmBlock> */
    public function blocks(User $actor, Team $team): Collection
    {
        return FarmBlock::visibleTo($actor, $team)->with('farm:id,name')
            ->orderBy('code')->orderBy('id')->get(['id', 'farm_id', 'code', 'name']);
    }

    /** @return Collection<int, CropVariety> */
    public function varieties(User $actor, Team $team): Collection
    {
        return CropVariety::visibleTo($actor, $team)->with('commodity:id,name')
            ->orderBy('name')->orderBy('id')->get(['id', 'commodity_id', 'name']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, non-empty-array<string, mixed>>
     */
    public function paginate(User $actor, Team $team, array $filters): LengthAwarePaginator
    {
        $paginator = $this->grouped($actor, $team, $filters)
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->appends($filters);

        $groups = $paginator->getCollection()
            ->map(fn (FarmActivity $row): array => $this->groupRow($row->getAttributes()))
            ->all();
        $records = $this->recordsForGroups($actor, $team, $filters, $groups);
        $groups = array_map(fn (array $group): array => [
            ...$group,
            'reports' => $records[$group['key']] ?? [],
        ], $groups);

        return new ArrayPaginator(
            $groups,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => $paginator->path(), 'query' => $filters],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{subblocks: int, reports: int, area_ha: string, seed_kg: string}
     */
    public function summary(User $actor, Team $team, array $filters): array
    {
        $row = $this->base($actor, $team, $filters)
            ->reorder()
            ->leftJoin('planting_details', 'planting_details.activity_id', '=', 'farm_activities.id')
            ->selectRaw('COUNT(DISTINCT COALESCE(farm_activities.planting_cycle_id, farm_activities.id)) AS subblocks')
            ->selectRaw('COUNT(DISTINCT farm_activities.id) AS reports')
            ->selectRaw('COALESCE(SUM(planting_details.area_ha), 0) AS area_ha')
            ->selectRaw('COALESCE(SUM(planting_details.quantity_kg), 0) AS seed_kg')
            ->first();

        return [
            'subblocks' => (int) data_get($row, 'subblocks', 0),
            'reports' => (int) data_get($row, 'reports', 0),
            'area_ha' => number_format((float) data_get($row, 'area_ha', 0), 4, '.', ''),
            'seed_kg' => number_format((float) data_get($row, 'seed_kg', 0), 3, '.', ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<FarmActivity>
     */
    private function grouped(User $actor, Team $team, array $filters): Builder
    {
        return $this->base($actor, $team, $filters)
            ->reorder()
            ->leftJoin('planting_details', 'planting_details.activity_id', '=', 'farm_activities.id')
            ->leftJoin('planting_cycles', 'planting_cycles.id', '=', 'farm_activities.planting_cycle_id')
            ->leftJoin('farms', 'farms.id', '=', 'farm_activities.farm_id')
            ->leftJoin('farm_blocks', 'farm_blocks.id', '=', 'farm_activities.block_id')
            ->leftJoin('crop_varieties', 'crop_varieties.id', '=', 'planting_cycles.variety_id')
            ->leftJoin('commodities', 'commodities.id', '=', 'crop_varieties.commodity_id')
            ->selectRaw('COALESCE(farm_activities.planting_cycle_id, farm_activities.id) AS group_id')
            ->selectRaw('MAX(farm_activities.planting_cycle_id::text) AS cycle_id')
            ->selectRaw('farm_activities.farm_id AS farm_id')
            ->selectRaw('farm_activities.block_id AS block_id')
            ->selectRaw('MAX(farms.name) AS farm_name')
            ->selectRaw('MAX(farm_blocks.code) AS block_code')
            ->selectRaw('MAX(farm_blocks.name) AS block_name')
            ->selectRaw('MAX(crop_varieties.id::text) AS variety_id')
            ->selectRaw('MAX(crop_varieties.name) AS variety_name')
            ->selectRaw('MAX(commodities.id::text) AS commodity_id')
            ->selectRaw('MAX(commodities.name) AS commodity_name')
            ->selectRaw('MAX(planting_cycles.batch_number) AS batch_number')
            ->selectRaw('MAX(planting_cycles.subblock_label) AS subblock_label')
            ->selectRaw('MAX(planting_cycles.planted_at) AS planted_at')
            ->selectRaw('MIN(farm_activities.activity_date) AS first_activity_date')
            ->selectRaw('MAX(farm_activities.activity_date) AS last_activity_date')
            ->selectRaw('COUNT(DISTINCT farm_activities.id) AS report_count')
            ->selectRaw('COALESCE(SUM(planting_details.area_ha), 0) AS area_ha')
            ->selectRaw('COALESCE(SUM(planting_details.quantity_kg), 0) AS seed_kg')
            ->groupByRaw('COALESCE(farm_activities.planting_cycle_id, farm_activities.id), farm_activities.farm_id, farm_activities.block_id')
            ->orderByRaw('MAX(farm_activities.activity_date) DESC')
            ->orderByRaw('MAX(farm_activities.created_at) DESC');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<string, list<array<string, mixed>>>
     */
    private function recordsForGroups(User $actor, Team $team, array $filters, array $groups): array
    {
        $cycleIds = [];
        $activityIds = [];
        foreach ($groups as $group) {
            if ($group['cycle_id'] !== null) {
                $cycleIds[] = $group['cycle_id'];
            } else {
                $activityIds[] = $group['key'];
            }
        }
        $cycleIds = array_values(array_unique($cycleIds));
        $activityIds = array_values(array_unique($activityIds));
        if ($cycleIds === [] && $activityIds === []) {
            return [];
        }

        $rows = $this->base($actor, $team, $filters)
            ->reorder()
            ->where(function (Builder $query) use ($cycleIds, $activityIds): void {
                if ($cycleIds !== []) {
                    $query->whereIn('farm_activities.planting_cycle_id', $cycleIds);
                }
                if ($activityIds !== []) {
                    $query->orWhere(fn (Builder $query) => $query
                        ->whereNull('farm_activities.planting_cycle_id')
                        ->whereIn('farm_activities.id', $activityIds));
                }
            })
            ->with([
                'performer' => fn ($query) => $query->visibleTo($actor, $team)->select('id', 'name'),
                'plantingDetail' => fn ($query) => $query->visibleTo($actor, $team),
                'dailyActivityLog' => fn ($query) => $query->visibleTo($actor, $team)->select('id', 'farm_id', 'work_date'),
                'photos' => fn ($query) => $query->visibleTo($actor, $team),
            ])
            ->orderBy('farm_activities.activity_date')
            ->orderBy('farm_activities.id')
            ->get();

        $records = [];
        foreach ($rows as $activity) {
            $key = (string) ($activity->planting_cycle_id ?? $activity->id);
            $records[$key][] = $this->recordRow($activity);
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<FarmActivity>
     */
    private function base(User $actor, Team $team, array $filters): Builder
    {
        $typeIds = ActivityType::visibleTo($actor, $team)->where('code', 'planting')->select('id');

        return FarmActivity::visibleTo($actor, $team)
            ->whereIn('farm_activities.farm_id', Farm::visibleTo($actor, $team)->select('id'))
            ->whereIn('farm_activities.activity_type_id', $typeIds)
            ->when(($filters['from_date'] ?? '') !== '', fn (Builder $query) => $query->whereDate('farm_activities.activity_date', '>=', $filters['from_date']))
            ->when(($filters['to_date'] ?? '') !== '', fn (Builder $query) => $query->whereDate('farm_activities.activity_date', '<=', $filters['to_date']))
            ->when(($filters['farm_id'] ?? '') !== '', fn (Builder $query) => $query->where('farm_activities.farm_id', $filters['farm_id']))
            ->when(($filters['block_id'] ?? '') !== '', fn (Builder $query) => $query->where('farm_activities.block_id', $filters['block_id']))
            ->when(($filters['variety_id'] ?? '') !== '', fn (Builder $query) => $query->whereHas(
                'plantingCycle',
                fn (Builder $cycle) => $cycle->where('variety_id', $filters['variety_id']),
            ))
            ->when(($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim((string) $filters['search'])).'%';
                $query->where(function (Builder $query) use ($pattern): void {
                    $query->where('farm_activities.description', 'ilike', $pattern)
                        ->orWhereHas('farm', fn (Builder $farm) => $farm->where('name', 'ilike', $pattern))
                        ->orWhereHas('plantingCycle', fn (Builder $cycle) => $cycle->where('subblock_label', 'ilike', $pattern))
                        ->orWhereHas('plantingCycle.block', fn (Builder $block) => $block
                            ->where('code', 'ilike', $pattern)->orWhere('name', 'ilike', $pattern))
                        ->orWhereHas('plantingCycle.variety', fn (Builder $variety) => $variety->where('name', 'ilike', $pattern))
                        ->orWhereHas('plantingDetail', fn (Builder $detail) => $detail->where('seed_name', 'ilike', $pattern))
                        ->orWhereHas('performer', fn (Builder $performer) => $performer->where('name', 'ilike', $pattern));
                });
            })
            ->orderByDesc('farm_activities.activity_date')
            ->orderByDesc('farm_activities.id');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function groupRow(array $attributes): array
    {
        $cycleId = $this->stringOrNull($attributes['cycle_id'] ?? null);
        $farmId = $this->stringOrNull($attributes['farm_id'] ?? null);
        $blockId = $this->stringOrNull($attributes['block_id'] ?? null);
        $varietyId = $this->stringOrNull($attributes['variety_id'] ?? null);
        $commodityId = $this->stringOrNull($attributes['commodity_id'] ?? null);

        return [
            'key' => (string) ($attributes['group_id'] ?? ''),
            'cycle_id' => $cycleId,
            'first_activity_date' => $this->jakartaDate($attributes['first_activity_date'] ?? null),
            'last_activity_date' => $this->jakartaDate($attributes['last_activity_date'] ?? null),
            'planted_at' => $this->jakartaDate($attributes['planted_at'] ?? null),
            'farm' => $farmId === null ? null : [
                'id' => $farmId,
                'name' => (string) ($attributes['farm_name'] ?? ''),
            ],
            'block' => $blockId === null ? null : [
                'id' => $blockId,
                'code' => (string) ($attributes['block_code'] ?? ''),
                'name' => (string) ($attributes['block_name'] ?? ''),
            ],
            'cycle' => $cycleId === null ? null : [
                'id' => $cycleId,
                'batch_number' => isset($attributes['batch_number']) ? (int) $attributes['batch_number'] : null,
                'subblock_label' => $attributes['subblock_label'] ?? null,
                'planted_at' => $this->jakartaDate($attributes['planted_at'] ?? null),
            ],
            'variety' => $varietyId === null ? null : [
                'id' => $varietyId,
                'name' => (string) ($attributes['variety_name'] ?? ''),
            ],
            'commodity' => $commodityId === null ? null : [
                'id' => $commodityId,
                'name' => (string) ($attributes['commodity_name'] ?? ''),
            ],
            'area_ha' => number_format((float) ($attributes['area_ha'] ?? 0), 4, '.', ''),
            'seed_kg' => number_format((float) ($attributes['seed_kg'] ?? 0), 3, '.', ''),
            'report_count' => (int) ($attributes['report_count'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function recordRow(FarmActivity $activity): array
    {
        $detail = $activity->plantingDetail;
        $log = $activity->dailyActivityLog;

        return [
            'id' => $activity->id,
            'activity_date' => $this->jakartaDate($activity->activity_date),
            'area_ha' => (string) ($detail?->getAttribute('area_ha') ?? '0.0000'),
            'seed_name' => $detail?->getAttribute('seed_name'),
            'quantity_kg' => $detail?->getAttribute('quantity_kg'),
            'performer_name' => $activity->performer?->name,
            'notes' => $activity->description,
            'photos' => $activity->photos->map(fn ($photo): array => [
                'id' => $photo->id,
                'label' => $photo->label,
                'caption' => $photo->caption,
                'url' => route('daily-logs.photos', [
                    'farm' => $activity->farm_id,
                    'activity' => $activity->id,
                    'photo' => $photo->id,
                ]),
            ])->values()->all(),
            'log_url' => $log === null ? null : route('admin.logbook.show', [
                'farm' => $activity->farm_id,
                'dailyLog' => $log->id,
            ]),
        ];
    }

    private function jakartaDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value)->setTimezone('Asia/Jakarta')->toDateString();
    }

    private function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
