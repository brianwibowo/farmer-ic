<?php

namespace App\Services\Activity;

use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Maintains the cycle's estimate as a projection of its open harvest tasks. */
class HarvestEstimateService
{
    /** Caller authorizes the use case and holds the farm and cycle locks. */
    public function synchronize(User $actor, Team $team, PlantingCycle $cycle, string $errorField = 'status'): void
    {
        if (! in_array($cycle->status, ['planned', 'active'], true)) {
            return;
        }

        $estimate = $cycle->planted_at === null ? null
            : $this->earliest($this->openTasks($actor, $team, $cycle), $cycle->planted_at, false, $errorField);
        if ($cycle->estimated_harvest_at?->toDateString() !== $estimate) {
            $cycle->forceFill(['estimated_harvest_at' => $estimate])->save();
        }
    }

    /**
     * Prepare an atomic planted_at update when the old estimate would violate
     * the date constraint. Never discard an unrelated legacy estimate.
     * Caller holds the farm and cycle locks and will reschedule pending tasks.
     */
    public function forPlantingDate(User $actor, Team $team, PlantingCycle $cycle, CarbonImmutable $anchor): ?string
    {
        $current = $cycle->estimated_harvest_at?->toDateString();
        if ($cycle->planted_at?->toDateString() === $anchor->toDateString()) {
            return $current;
        }

        $tasks = $this->openTasks($actor, $team, $cycle);
        $next = $this->earliest($tasks, $anchor, true, 'work_date');
        if ($current === null || $current >= $anchor->toDateString()) {
            return $current;
        }

        $previous = $this->earliest($tasks, null, false, 'work_date');
        if ($previous !== $current || $next === null) {
            throw ValidationException::withMessages([
                'work_date' => 'Estimasi panen tersimpan lebih awal dari tanggal tanam baru. Minta Admin meninjau jadwal panen terlebih dahulu.',
            ]);
        }

        return $next;
    }

    /** @return Collection<int, PlantingCycleTask> */
    private function openTasks(User $actor, Team $team, PlantingCycle $cycle): Collection
    {
        return PlantingCycleTask::visibleTo($actor, $team)
            ->where('planting_cycle_id', $cycle->id)
            ->where('activity_category', 'harvest')
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('id')->lockForUpdate()->get();
    }

    /** @param Collection<int, PlantingCycleTask> $tasks */
    private function earliest(Collection $tasks, ?CarbonImmutable $anchor, bool $projectPending, string $errorField): ?string
    {
        $earliest = null;
        foreach ($tasks as $task) {
            $date = $projectPending && $task->status === 'pending' && $task->rescheduled_date === null
                ? $anchor?->addDays($task->offset_days)
                : $task->effectiveScheduledDate();
            if ($date === null) {
                continue;
            }
            $value = $date->toDateString();
            if ($anchor !== null && $value < $anchor->toDateString()) {
                throw ValidationException::withMessages([
                    $errorField => 'Jadwal panen tidak boleh sebelum tanggal tanam aktual. Minta Admin meninjau jadwal tugas panen.',
                ]);
            }
            if ($earliest === null || $value < $earliest) {
                $earliest = $value;
            }
        }

        return $earliest;
    }
}
