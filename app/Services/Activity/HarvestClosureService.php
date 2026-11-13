<?php

namespace App\Services\Activity;

use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HarvestClosureService
{
    /** Caller authorizes configure on the persisted cycle. */
    public function close(User $actor, Team $team, PlantingCycle $cycle): void
    {
        DB::transaction(function () use ($actor, $team, $cycle): void {
            Farm::visibleTo($actor, $team)->whereKey($cycle->farm_id)->lockForUpdate()->firstOrFail();
            $cycle = PlantingCycle::visibleTo($actor, $team)->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            if ($cycle->status !== 'active') {
                throw ValidationException::withMessages(['harvest' => 'Hanya subblok aktif yang dapat ditutup panennya.']);
            }

            $stage = PlantingCycleStage::visibleTo($actor, $team)->where('planting_cycle_id', $cycle->id)
                ->where('code', 'harvest')->lockForUpdate()->first();
            $hasReport = FarmActivity::visibleTo($actor, $team)->where('planting_cycle_id', $cycle->id)
                ->whereHas('activityType', fn ($q) => $q->visibleTo($actor, $team)->where('code', 'harvest'))
                ->whereHas('harvestDetail', fn ($q) => $q->visibleTo($actor, $team))->exists();
            if ($stage === null || $stage->status !== 'in_progress' || ! $hasReport) {
                throw ValidationException::withMessages(['harvest' => 'Catat laporan panen sebelum menutup subblok.']);
            }

            $tasks = PlantingCycleTask::visibleTo($actor, $team)->where('planting_cycle_id', $cycle->id)
                ->where('activity_category', 'harvest')->whereIn('status', ['pending', 'in_progress'])
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($tasks as $task) {
                $task->forceFill([
                    'status' => 'completed', 'completed_at' => now(), 'completed_by' => $actor->id,
                    'completion_note' => null, 'skip_reason' => null,
                ])->save();
            }

            // Stage dates remain the first and last report times, not the closing time.
            $stage->forceFill(['status' => 'completed'])->save();
            $cycle->forceFill(['status' => 'harvested'])->save();
        });
    }
}
