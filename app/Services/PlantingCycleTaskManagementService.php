<?php

namespace App\Services;

use App\Models\FarmActivity;
use App\Models\PlantingCycleTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlantingCycleTaskManagementService
{
    public function skip(PlantingCycleTask $task, User $actor, string $reason): PlantingCycleTask
    {
        return $this->updateActionable($task, [
            'status' => 'skipped',
            'skip_reason' => trim($reason),
            'completed_at' => null,
            'completed_by' => null,
            'completion_note' => null,
        ]);
    }

    public function complete(PlantingCycleTask $task, User $actor, ?string $note = null): PlantingCycleTask
    {
        return DB::transaction(function () use ($task, $actor, $note): PlantingCycleTask {
            $locked = PlantingCycleTask::query()->lockForUpdate()->findOrFail($task->id);

            if (! in_array($locked->status, ['pending', 'in_progress'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Tugas ini tidak dapat diselesaikan karena statusnya sudah '.$locked->status.'.',
                ]);
            }

            $cycle = $locked->plantingCycle;
            if ($cycle->planted_at === null) {
                throw ValidationException::withMessages([
                    'status' => 'Tugas belum dapat diselesaikan sebelum tanggal tanam aktual dicatat.',
                ]);
            }

            $team = $cycle->farm?->team;
            $hasReport = $team !== null
                && FarmActivity::visibleTo($actor, $team)->where('planting_cycle_task_id', $locked->id)->exists();
            if (! $hasReport) {
                throw ValidationException::withMessages([
                    'status' => 'Tambahkan minimal satu aktivitas sebelum menyelesaikan tugas.',
                ]);
            }

            $locked->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => $actor->id,
                'completion_note' => $note === null || trim($note) === '' ? null : trim($note),
                'skip_reason' => null,
            ])->save();

            return $locked->refresh();
        });
    }

    /** @param array{rescheduled_date: string, reschedule_reason: string} $data */
    public function reschedule(PlantingCycleTask $task, User $actor, array $data): PlantingCycleTask
    {
        $date = CarbonImmutable::parse($data['rescheduled_date']);

        return $this->updateActionable($task, [
            'rescheduled_date' => $date->toDateString(),
            'due_date' => $date->addDays($task->tolerance_days)->toDateString(),
            'reschedule_reason' => trim($data['reschedule_reason']),
            'rescheduled_by' => $actor->id,
            'rescheduled_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function updateActionable(PlantingCycleTask $task, array $attributes): PlantingCycleTask
    {
        return DB::transaction(function () use ($task, $attributes): PlantingCycleTask {
            $locked = PlantingCycleTask::query()->lockForUpdate()->findOrFail($task->id);

            if (! in_array($locked->status, ['pending', 'in_progress'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Tugas ini tidak dapat diubah karena statusnya sudah '.$locked->status.'.',
                ]);
            }

            $locked->forceFill($attributes)->save();

            return $locked->refresh();
        });
    }
}
