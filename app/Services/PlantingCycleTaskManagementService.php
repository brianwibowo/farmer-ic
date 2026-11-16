<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Models\User;
use App\Services\Activity\HarvestEstimateService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlantingCycleTaskManagementService
{
    public function __construct(private readonly HarvestEstimateService $estimates) {}

    public function skip(PlantingCycleTask $task, User $actor, string $reason): PlantingCycleTask
    {
        return $this->updateActionable($task, $actor, [
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
            $team = $task->plantingCycle?->farm?->team;
            if (! $team instanceof Team) {
                throw ValidationException::withMessages(['status' => 'Konteks kebun tugas tidak tersedia.']);
            }
            $locked = PlantingCycleTask::visibleTo($actor, $team)->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if ($locked->activity_category === 'harvest') {
                throw ValidationException::withMessages([
                    'status' => 'Tugas panen diselesaikan oleh Admin saat penutupan panen subblok.',
                ]);
            }

            if (! in_array($locked->status, ['pending', 'in_progress'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Tugas ini tidak dapat diselesaikan karena statusnya sudah '.$locked->status.'.',
                ]);
            }

            $cycle = PlantingCycle::visibleTo($actor, $team)->whereKey($locked->planting_cycle_id)->firstOrFail();
            if ($cycle->planted_at === null) {
                throw ValidationException::withMessages([
                    'status' => 'Tugas belum dapat diselesaikan sebelum tanggal tanam aktual dicatat.',
                ]);
            }

            $hasReport = FarmActivity::visibleTo($actor, $team)->where('planting_cycle_task_id', $locked->id)->exists();
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

        return $this->updateActionable($task, $actor, [
            'rescheduled_date' => $date->toDateString(),
            'reschedule_reason' => trim($data['reschedule_reason']),
            'rescheduled_by' => $actor->id,
            'rescheduled_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function updateActionable(PlantingCycleTask $task, User $actor, array $attributes): PlantingCycleTask
    {
        return DB::transaction(function () use ($task, $actor, $attributes): PlantingCycleTask {
            $parent = $task->plantingCycle;
            $team = $parent?->farm?->team;
            if (! $parent instanceof PlantingCycle || ! $team instanceof Team) {
                throw ValidationException::withMessages(['status' => 'Konteks kebun tugas tidak tersedia.']);
            }

            $cycle = null;
            if ($task->activity_category === 'harvest') {
                // Serialize with planting, task reports and harvest closure.
                $farm = Farm::visibleTo($actor, $team)->whereKey($parent->farm_id)->lockForUpdate()->firstOrFail();
                $cycle = PlantingCycle::visibleTo($actor, $team)->whereKey($parent->id)
                    ->where('farm_id', $farm->id)->lockForUpdate()->firstOrFail();
            }
            $locked = PlantingCycleTask::visibleTo($actor, $team)->whereKey($task->id)
                ->where('planting_cycle_id', $parent->id)->lockForUpdate()->firstOrFail();
            if (($locked->activity_category === 'harvest') !== ($cycle !== null)) {
                throw ValidationException::withMessages(['status' => 'Jenis tugas berubah. Muat ulang halaman dan coba lagi.']);
            }

            if (! in_array($locked->status, ['pending', 'in_progress'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Tugas ini tidak dapat diubah karena statusnya sudah '.$locked->status.'.',
                ]);
            }

            $previousDate = $locked->effectiveScheduledDate()?->toDateString();
            $rescheduling = isset($attributes['rescheduled_date']);
            if ($rescheduling) {
                $date = CarbonImmutable::parse($attributes['rescheduled_date']);
                if ($locked->scheduled_date === null) {
                    throw ValidationException::withMessages(['rescheduled_date' => 'Jadwal SOP belum tersedia. Sinkronkan tugas setelah tanggal tanam dicatat.']);
                }
                if ($cycle?->planted_at !== null && $date->toDateString() < $cycle->planted_at->toDateString()) {
                    throw ValidationException::withMessages(['rescheduled_date' => 'Jadwal panen tidak boleh sebelum tanggal tanam aktual.']);
                }
                $attributes['due_date'] = $date->addDays($locked->tolerance_days)->toDateString();
            }
            $locked->forceFill($attributes)->save();
            if ($cycle !== null && (! $rescheduling || $previousDate !== $locked->effectiveScheduledDate()?->toDateString() || $cycle->estimated_harvest_at === null)) {
                $this->estimates->synchronize($actor, $team, $cycle, $rescheduling ? 'rescheduled_date' : 'status');
            }

            return $locked->refresh();
        });
    }
}
