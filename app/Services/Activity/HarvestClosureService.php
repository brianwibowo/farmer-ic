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

            $openMaintenance = PlantingCycleTask::visibleTo($actor, $team)->where('planting_cycle_id', $cycle->id)
                ->where('activity_category', 'maintenance')->whereNotIn('status', ['completed', 'skipped'])->exists();
            if ($openMaintenance) {
                throw ValidationException::withMessages(['harvest' => 'Selesaikan atau tandai tidak dikerjakan seluruh tugas SOP perawatan sebelum menutup panen.']);
            }

            $tasks = PlantingCycleTask::visibleTo($actor, $team)->where('planting_cycle_id', $cycle->id)
                ->where('activity_category', 'harvest')->whereIn('status', ['pending', 'in_progress'])
                ->orderBy('id')->lockForUpdate()->get();
            if ($tasks->isNotEmpty()) {
                $reportedTaskIds = FarmActivity::visibleTo($actor, $team)
                    ->whereIn('planting_cycle_task_id', $tasks->modelKeys())
                    ->whereHas('activityType', fn ($q) => $q->visibleTo($actor, $team)->where('code', 'harvest'))
                    ->whereHas('harvestDetail', fn ($q) => $q->visibleTo($actor, $team))
                    ->distinct()
                    ->pluck('planting_cycle_task_id')
                    ->all();
                if (count($reportedTaskIds) !== $tasks->count()) {
                    throw ValidationException::withMessages([
                        'harvest' => 'Setiap tugas SOP panen yang belum ditandai tidak dikerjakan wajib memiliki laporan panen sebelum penutupan.',
                    ]);
                }
            }

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
