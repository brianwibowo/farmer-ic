<?php

namespace App\Services;

use App\Models\ActivityPhoto;
use App\Models\CropVariety;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\User;
use Carbon\CarbonImmutable;

class PetugasTaskReadService
{
    /** @return array<string, mixed> */
    public function listing(User $actor, Farm $farm, string $status): array
    {
        $team = $farm->team;
        $tasks = PlantingCycleTask::visibleTo($actor, $team)
            ->whereHas('plantingCycle', fn ($query) => $query->where('farm_id', $farm->id))
            ->get()->map(fn (PlantingCycleTask $task): array => $this->detail($actor, $task));
        $summary = collect(['overdue', 'today', 'upcoming', 'completed'])->mapWithKeys(fn ($key) => [$key => $tasks->where('urgency', $key)->count()])->all();

        return [
            'tasks' => $tasks->filter(fn ($task) => $status === 'all' || $task['urgency'] === $status)
                ->sortBy(fn ($task) => [array_search($task['urgency'], ['overdue', 'today', 'upcoming', 'completed']), $task['scheduled_date'] ?? '9999-12-31', $task['offset_days'], $task['name']])->values()->all(),
            'summary' => $summary,
        ];
    }

    /** @return array<string, mixed> */
    public function detail(User $actor, PlantingCycleTask $task): array
    {
        $team = $task->plantingCycle->farm->team;
        $task = PlantingCycleTask::visibleTo($actor, $team)->findOrFail($task->id);
        $cycle = PlantingCycle::visibleTo($actor, $team)->findOrFail($task->planting_cycle_id);
        $farm = Farm::visibleTo($actor, $team)->findOrFail($cycle->farm_id);
        $block = FarmBlock::visibleTo($actor, $team)->findOrFail($cycle->block_id);
        $variety = CropVariety::visibleTo($actor, $team)->find($cycle->variety_id);
        $today = CarbonImmutable::today('Asia/Jakarta');
        $effective = $task->effectiveScheduledDate();
        $urgency = match (true) {
            in_array($task->status, ['completed', 'skipped'], true) => 'completed',
            $effective === null => 'upcoming',
            $task->due_date !== null && $task->due_date->toDateString() < $today->toDateString() => 'overdue',
            $effective->toDateString() <= $today->toDateString() => 'today',
            default => 'upcoming',
        };

        return [
            ...$task->only(['id', 'name', 'instructions', 'status', 'activity_category', 'activity_subcategory', 'offset_days', 'is_required']),
            'scheduled_date' => $effective?->toDateString(),
            'due_date' => $task->due_date?->toDateString(), 'urgency' => $urgency,
            'completed_at' => $task->completed_at?->toISOString(),
            'completed_by' => $task->getAttribute('completed_by'),
            'completion_note' => $task->getAttribute('completion_note'),
            'linked_activities_count' => FarmActivity::visibleTo($actor, $team)->where('planting_cycle_task_id', $task->id)->count(),
            'can' => ['record' => false, 'complete' => false],
            'subblok' => [
                'id' => $cycle->id, 'number' => $cycle->getAttribute('batch_number'), 'label' => $cycle->getAttribute('subblock_label'),
                'planned_planting_at' => $cycle->planned_planting_at?->toDateString(), 'planted_at' => $cycle->planted_at?->toDateString(),
                'hst' => $cycle->planted_at ? (int) $cycle->planted_at->startOfDay()->diffInDays($today, false) : null,
                'farm' => $farm->only(['id', 'name']), 'block' => $block->only(['id', 'name']),
                'variety' => $variety?->only(['id', 'name']) ?? ['id' => '', 'name' => 'Belum ditentukan'],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function reports(User $actor, PlantingCycleTask $task): array
    {
        $team = $task->plantingCycle->farm->team;

        return array_values(FarmActivity::visibleTo($actor, $team)->where('planting_cycle_task_id', $task->id)->with('performer:id,name')->orderByDesc('activity_date')->get()
            ->map(function (FarmActivity $activity) use ($actor, $team): array {
                $log = DailyActivityLog::visibleTo($actor, $team)->find($activity->daily_activity_log_id);

                return [
                    'id' => $activity->id, 'work_date' => $log?->work_date->toDateString(),
                    'performer_name' => $activity->performer?->name,
                    'start_time' => $log?->start_time, 'end_time' => $log?->end_time, 'worker_count' => $activity->worker_count,
                    'notes' => $activity->description, 'details' => json_decode((string) $activity->getAttribute('task_details'), true),
                    'photos' => ActivityPhoto::visibleTo($actor, $team)->where('activity_id', $activity->id)->get()->map(fn ($photo) => [
                        'id' => $photo->id, 'url' => route('petugas.aktivitas.photos', ['activity' => $activity->id, 'photo' => $photo->id]),
                    ])->all(),
                ];
            })->all());
    }
}
