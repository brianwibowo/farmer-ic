<?php

namespace App\Services\Activity;

use App\Models\ActivityType;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;

/** Maintains persisted stages from authorized, persisted activity reports. */
class PlantingCycleStageService
{
    private const CODES = [
        'land_cleaning' => 'land_clearing',
        'loosening' => 'soil_preparation', 'beds' => 'soil_preparation', 'liming' => 'soil_preparation',
        'base_fertilization' => 'basic_fertilizer', 'planting' => 'planting',
        'irrigation' => 'maintenance', 'weeding' => 'maintenance', 'follow_up_fertilization' => 'maintenance',
        'growth_check' => 'maintenance', 'pest_control' => 'maintenance', 'harvest' => 'harvest',
    ];

    private const BLOCK_CODES = ['land_cleaning', 'loosening', 'beds', 'liming', 'base_fertilization'];

    public static function tracks(string $activityCode): bool
    {
        return isset(self::CODES[$activityCode]);
    }

    /** Caller holds the farm lock and authorizes the activity write. */
    public function record(User $actor, Team $team, FarmActivity $activity, ActivityType $type): void
    {
        $stage = self::CODES[$type->code] ?? null;
        if ($stage === null) {
            return;
        }
        $cycles = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $activity->farm_id);
        if (in_array($type->code, self::BLOCK_CODES, true)) {
            if ($activity->getAttribute('block_id') === null) {
                return;
            }
            $cycles->where('block_id', $activity->getAttribute('block_id'));
        } elseif ($activity->planting_cycle_id !== null) {
            $cycles->whereKey($activity->planting_cycle_id);
        } else {
            return;
        }
        foreach ($cycles->orderBy('id')->lockForUpdate()->get() as $cycle) {
            $this->advance($actor, $team, $cycle, $stage, $activity->activity_date);
        }
    }

    /** Reuse block reports when a subblock is created after preparation. Caller holds the farm lock. */
    public function initialize(User $actor, Team $team, PlantingCycle $cycle): void
    {
        $reports = FarmActivity::visibleTo($actor, $team)->where('farm_id', $cycle->farm_id)
            ->where('block_id', $cycle->block_id)
            ->whereHas('activityType', fn ($q) => $q->visibleTo($actor, $team)->whereIn('code', self::BLOCK_CODES))
            ->with(['activityType' => fn ($q) => $q->visibleTo($actor, $team)])->orderBy('activity_date')->orderBy('id')->get();
        foreach ($reports as $report) {
            $this->advance($actor, $team, $cycle, self::CODES[$report->activityType->code], $report->activity_date);
        }
    }

    private function advance(User $actor, Team $team, PlantingCycle $cycle, string $code, CarbonImmutable $at): void
    {
        $stages = PlantingCycleStage::visibleTo($actor, $team)->where('planting_cycle_id', $cycle->id)
            ->orderBy('position')->lockForUpdate()->get();
        $target = $stages->firstWhere('code', $code);
        if ($target === null || $stages->count() !== count(PlantingCycleStage::CODES)) {
            throw new \LogicException('Tahap subblok tidak lengkap atau tidak dapat diakses.');
        }
        $laterStarted = $stages->contains(fn ($stage) => $stage->position > $target->position && in_array($stage->status, ['in_progress', 'completed'], true));
        // A later stage finishes all previous stages, including those without reports.
        foreach ($stages as $stage) {
            if ($stage->position < $target->position && $stage->status !== 'completed') {
                $stage->forceFill(['status' => 'completed'])->save();
            }
        }
        $target->forceFill([
            'status' => $laterStarted || $target->status === 'completed' ? 'completed' : 'in_progress',
            'started_at' => $target->started_at === null || $at->lt($target->started_at) ? $at : $target->started_at,
            'completed_at' => $target->completed_at === null || $at->gt($target->completed_at) ? $at : $target->completed_at,
        ])->save();
    }
}
