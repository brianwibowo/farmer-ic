<?php

namespace App\Services\Activity;

use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\User;
use App\Support\LandArea;
use App\Support\TaskActivityFields;

/** Read-only snapshots; write services recheck limits under their existing locks. */
class ActivityFormLimits
{
    public function __construct(private readonly HarvestGuard $harvest) {}

    public function taskArea(User $actor, PlantingCycleTask $task): int
    {
        $team = $task->plantingCycle->farm->team;
        $cycle = PlantingCycle::visibleTo($actor, $team)->whereKey($task->planting_cycle_id)->firstOrFail();

        return TaskActivityFields::activityCode($task) === 'harvest'
            ? $this->harvest->remainingAreaM2($actor, $team, $cycle)
            : max(0, LandArea::squareMetres($cycle->planted_area_ha) ?? 0);
    }

    public function plantingArea(User $actor, PlantingCycle $cycle): int
    {
        $farm = $cycle->farm;
        $team = $farm->team;
        $cycle = PlantingCycle::visibleTo($actor, $team)->whereKey($cycle->id)->firstOrFail();
        $block = FarmBlock::visibleTo($actor, $team)->whereKey($cycle->block_id)->firstOrFail();
        $usage = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN block_id IS NULL AND status = 'active' THEN planted_area_ha ELSE 0 END), 0) AS legacy_active_ha")
            ->selectRaw("COALESCE(SUM(CASE WHEN block_id IS NOT NULL AND status IN ('planned', 'active') THEN planted_area_ha ELSE 0 END), 0) AS farm_batches_ha")
            ->selectRaw("COALESCE(SUM(CASE WHEN block_id = ? AND status IN ('planned', 'active') THEN planted_area_ha ELSE 0 END), 0) AS block_batches_ha", [$block->id])
            ->first();
        $farmRemaining = max(0, (LandArea::squareMetres($farm->total_area_ha) ?? 0)
            - (LandArea::squareMetres(data_get($usage, 'legacy_active_ha', 0)) ?? 0)
            - (LandArea::squareMetres(data_get($usage, 'farm_batches_ha', 0)) ?? 0));
        $blockRemaining = max(0, (LandArea::squareMetres($block->area_ha) ?? 0)
            - (LandArea::squareMetres(data_get($usage, 'block_batches_ha', 0)) ?? 0));

        return min($farmRemaining, $blockRemaining);
    }

    public function voluntaryCycleArea(PlantingCycle $cycle): int
    {
        return max(0, LandArea::squareMetres($cycle->planted_area_ha) ?? 0);
    }

    public function voluntaryBlockArea(FarmBlock $block): int
    {
        return max(0, LandArea::squareMetres($block->area_ha) ?? 0);
    }
}
