<?php

namespace App\Services\Activity;

use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class TaskReportingWindow
{
    /** @return array{start: string, end: string}|null */
    public function range(PlantingCycleTask $task, PlantingCycle $cycle): ?array
    {
        if ($cycle->planted_at === null) {
            return null;
        }

        $start = $task->rescheduled_date ?? $cycle->planted_at->addDays($task->offset_days);

        return ['start' => $start->toDateString(), 'end' => $start->addDays($task->tolerance_days)->toDateString()];
    }

    public function blockedReason(PlantingCycleTask $task, PlantingCycle $cycle, ?string $date = null): ?string
    {
        $range = $this->range($task, $cycle);
        if ($range === null) {
            return 'Pencatatan tersedia setelah tanggal tanam aktual dicatat.';
        }

        $date ??= CarbonImmutable::now('Asia/Jakarta')->toDateString();
        $label = $range['start'].' sampai '.$range['end'].' (WIB)';

        return match (true) {
            $date < $range['start'] => 'Belum dapat dilaporkan. Jendela pelaporan: '.$label.'.',
            $date > $range['end'] => 'Jendela pelaporan telah berakhir: '.$label.'. Hubungi Admin untuk penjadwalan ulang.',
            default => null,
        };
    }

    public function ensureAllowed(PlantingCycleTask $task, PlantingCycle $cycle, string $workDate): void
    {
        if ($reason = $this->blockedReason($task, $cycle)) {
            throw ValidationException::withMessages(['task' => $reason]);
        }
        if ($reason = $this->blockedReason($task, $cycle, $workDate)) {
            throw ValidationException::withMessages(['work_date' => 'Tanggal kerja di luar jendela tugas. '.$reason]);
        }
    }
}
