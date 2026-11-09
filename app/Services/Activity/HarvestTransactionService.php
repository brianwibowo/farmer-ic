<?php

namespace App\Services\Activity;

use App\Models\ActivityType;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Read-only operational ledger for harvest reports. */
class HarvestTransactionService
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
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(User $actor, Team $team, array $filters): LengthAwarePaginator
    {
        return $this->base($actor, $team, $filters)
            ->with([
                'farm' => fn ($query) => $query->visibleTo($actor, $team)->select('id', 'name'),
                'plantingCycle' => fn ($query) => $query->visibleTo($actor, $team)
                    ->select('id', 'farm_id', 'block_id', 'batch_number', 'subblock_label', 'variety_id', 'planted_at', 'planted_area_ha'),
                'plantingCycle.block' => fn ($query) => $query->visibleTo($actor, $team)->select('id', 'farm_id', 'code', 'name'),
                'plantingCycle.variety' => fn ($query) => $query->visibleTo($actor, $team)->select('id', 'commodity_id', 'name'),
                'plantingCycle.variety.commodity' => fn ($query) => $query->visibleTo($actor, $team)->select('id', 'name'),
                'performer' => fn ($query) => $query->visibleTo($actor, $team)->select('id', 'name'),
                'harvestDetail' => fn ($query) => $query->visibleTo($actor, $team),
                'dailyActivityLog' => fn ($query) => $query->visibleTo($actor, $team)->select('id', 'farm_id', 'work_date'),
                'photos' => fn ($query) => $query->visibleTo($actor, $team),
            ])
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->appends($filters)
            ->through(fn (FarmActivity $activity): array => $this->row($activity));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{reports: int, area_ha: string, weight_ton: string, workers: int, farms: int}
     */
    public function summary(User $actor, Team $team, array $filters): array
    {
        $row = $this->base($actor, $team, $filters)
            ->reorder()
            ->leftJoin('harvest_details', 'harvest_details.activity_id', '=', 'farm_activities.id')
            ->selectRaw('COUNT(DISTINCT farm_activities.id) AS reports')
            ->selectRaw('COALESCE(SUM(harvest_details.harvested_area_ha), 0) AS area_ha')
            ->selectRaw('COALESCE(SUM(harvest_details.gross_weight_ton), 0) AS weight_ton')
            ->selectRaw('COALESCE(SUM(farm_activities.worker_count), 0) AS workers')
            ->selectRaw('COUNT(DISTINCT farm_activities.farm_id) AS farms')
            ->first();

        return [
            'reports' => (int) data_get($row, 'reports', 0),
            'area_ha' => number_format((float) data_get($row, 'area_ha', 0), 4, '.', ''),
            'weight_ton' => number_format((float) data_get($row, 'weight_ton', 0), 3, '.', ''),
            'workers' => (int) data_get($row, 'workers', 0),
            'farms' => (int) data_get($row, 'farms', 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<FarmActivity>
     */
    private function base(User $actor, Team $team, array $filters): Builder
    {
        $typeIds = ActivityType::visibleTo($actor, $team)->where('code', 'harvest')->select('id');

        return FarmActivity::visibleTo($actor, $team)
            ->whereIn('farm_id', Farm::visibleTo($actor, $team)->select('id'))
            ->whereIn('activity_type_id', $typeIds)
            ->when(($filters['from_date'] ?? '') !== '', fn (Builder $query) => $query->whereDate('activity_date', '>=', $filters['from_date']))
            ->when(($filters['to_date'] ?? '') !== '', fn (Builder $query) => $query->whereDate('activity_date', '<=', $filters['to_date']))
            ->when(($filters['farm_id'] ?? '') !== '', fn (Builder $query) => $query->where('farm_id', $filters['farm_id']))
            ->when(($filters['block_id'] ?? '') !== '', fn (Builder $query) => $query->where('block_id', $filters['block_id']))
            ->when(($filters['variety_id'] ?? '') !== '', fn (Builder $query) => $query->whereHas(
                'plantingCycle',
                fn (Builder $cycle) => $cycle->where('variety_id', $filters['variety_id']),
            ))
            ->when(($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim((string) $filters['search'])).'%';
                $query->where(function (Builder $query) use ($pattern): void {
                    $query->where('description', 'ilike', $pattern)
                        ->orWhereHas('farm', fn (Builder $farm) => $farm->where('name', 'ilike', $pattern))
                        ->orWhereHas('plantingCycle', fn (Builder $cycle) => $cycle->where('subblock_label', 'ilike', $pattern))
                        ->orWhereHas('plantingCycle.block', fn (Builder $block) => $block
                            ->where('code', 'ilike', $pattern)->orWhere('name', 'ilike', $pattern))
                        ->orWhereHas('plantingCycle.variety', fn (Builder $variety) => $variety->where('name', 'ilike', $pattern))
                        ->orWhereHas('harvestDetail', fn (Builder $detail) => $detail
                            ->where('color', 'ilike', $pattern)
                            ->orWhere('ripeness', 'ilike', $pattern)
                            ->orWhere('cleanliness', 'ilike', $pattern))
                        ->orWhereHas('performer', fn (Builder $performer) => $performer->where('name', 'ilike', $pattern));
                });
            })
            ->orderByDesc('activity_date')
            ->orderByDesc('id');
    }

    /** @return array<string, mixed> */
    private function row(FarmActivity $activity): array
    {
        $cycle = $activity->plantingCycle;
        $block = $cycle?->block;
        $variety = $cycle?->variety;
        $detail = $activity->harvestDetail;
        $log = $activity->dailyActivityLog;

        return [
            'id' => $activity->id,
            'activity_date' => $activity->activity_date->setTimezone('Asia/Jakarta')->toDateString(),
            'farm' => $activity->farm?->only(['id', 'name']),
            'block' => $block?->only(['id', 'code', 'name']),
            'cycle' => $cycle === null ? null : [
                'id' => $cycle->id,
                'batch_number' => $cycle->getAttribute('batch_number') === null ? null : (int) $cycle->getAttribute('batch_number'),
                'subblock_label' => $cycle->getAttribute('subblock_label'),
                'planted_at' => $cycle->planted_at?->toDateString(),
                'planted_area_ha' => (string) ($cycle->getAttribute('planted_area_ha') ?? '0.0000'),
            ],
            'variety' => $variety?->only(['id', 'name']),
            'commodity' => $variety?->commodity?->only(['id', 'name']),
            'harvested_area_ha' => (string) ($detail?->getAttribute('harvested_area_ha') ?? '0.0000'),
            'gross_weight_ton' => (string) ($detail?->getAttribute('gross_weight_ton') ?? '0.000'),
            'moisture_percentage' => $detail?->getAttribute('moisture_percentage'),
            'color' => $detail?->getAttribute('color'),
            'ripeness' => $detail?->getAttribute('ripeness'),
            'cleanliness' => $detail?->getAttribute('cleanliness'),
            'worker_count' => $activity->getAttribute('worker_count') === null ? null : (int) $activity->getAttribute('worker_count'),
            'performer_name' => $activity->performer?->name,
            'notes' => $activity->description,
            'source' => $activity->getAttribute('planting_cycle_task_id') === null ? 'Panen mandiri' : 'Tugas SOP',
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
}
