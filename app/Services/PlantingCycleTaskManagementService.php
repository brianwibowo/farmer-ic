<?php

namespace App\Services;

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
        ]);
    }

    public function cancel(PlantingCycleTask $task, User $actor, string $reason): PlantingCycleTask
    {
        return $this->updateActionable($task, [
            'status' => 'cancelled',
            'cancel_reason' => trim($reason),
            'completed_at' => null,
        ]);
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
