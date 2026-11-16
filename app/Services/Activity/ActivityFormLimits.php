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
        $team = $cycle->farm->team;
        $cycle = PlantingCycle::visibleTo($actor, $team)->whereKey($cycle->id)->firstOrFail();

        return max(0, (LandArea::squareMetres($cycle->getAttribute('reserved_area_ha')) ?? 0)
            - (LandArea::squareMetres($cycle->planted_area_ha ?? 0) ?? 0));
    }

    public function voluntaryCycleArea(PlantingCycle $cycle): int
    {
        return max(0, LandArea::squareMetres($cycle->getAttribute('reserved_area_ha')) ?? 0);
    }

    public function voluntaryBlockArea(FarmBlock $block): int
    {
        return max(0, LandArea::squareMetres($block->area_ha) ?? 0);
    }
}
