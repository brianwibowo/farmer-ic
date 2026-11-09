<?php

namespace App\Services\Petugas;

use App\Models\Farm;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;

class PetugasHomeService
{
    /** @return array<string, mixed> */
    public function overview(User $actor, ?Farm $farm, ?Team $team): array
    {
        $today = CarbonImmutable::today('Asia/Jakarta');
        $empty = [
            'date' => $today->toDateString(),
            'farm' => null,
            'summary' => ['pre_planting' => 0, 'planting' => 0, 'harvest' => 0],
            'task_summary' => ['total' => 0, 'completed' => 0],
            'tasks' => ['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1, 'prev_page_url' => null, 'next_page_url' => null],
        ];
        if ($farm === null || $team === null) {
            return $empty;
        }
        $visibleFarm = Farm::visibleTo($actor, $team)->whereKey($farm->id)->first();
        if ($visibleFarm === null) {
            return $empty;
        }

        $cycles = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $visibleFarm->id)
            ->whereNotNull('planting_round_id')
            ->whereIn('status', ['planned', 'active', 'harvested'])
            ->whereHas('plantingRound', fn ($rounds) => $rounds->visibleTo($actor, $team)
                ->whereHas('block', fn ($blocks) => $blocks->visibleTo($actor, $team)->where('farm_id', $visibleFarm->id)));
        $counts = (clone $cycles)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $tasks = PlantingCycleTask::visibleTo($actor, $team)
            ->whereIn('planting_cycle_id', (clone $cycles)->select('id'))
            ->whereRaw('COALESCE(rescheduled_date, scheduled_date) = ?', [$today->toDateString()])
            ->whereIn('status', ['pending', 'in_progress', 'completed']);
        $total = (clone $tasks)->count();
        $completed = (clone $tasks)->where('status', 'completed')->count();
        $page = $tasks->with([
            'stage' => fn ($stages) => $stages->visibleTo($actor, $team),
            'plantingCycle' => fn ($cycles) => $cycles->visibleTo($actor, $team)->with([
                'plantingRound' => fn ($rounds) => $rounds->visibleTo($actor, $team)->with([
                    'block' => fn ($blocks) => $blocks->visibleTo($actor, $team),
                ]),
            ]),
        ])->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderByDesc('is_required')->orderBy('due_date')->orderBy('name')->orderBy('id')
            ->paginate(10)->through(function (PlantingCycleTask $task) use ($today): array {
                $cycle = $task->plantingCycle;

                return [
                    ...$task->only(['id', 'name', 'instructions', 'status', 'is_required', 'offset_days', 'activity_category', 'activity_subcategory']),
                    'scheduled_date' => $task->effectiveScheduledDate()?->toDateString(),
                    'due_date' => $task->due_date?->toDateString(),
                    'stage' => $task->stage?->code,
                    'batch_number' => $cycle?->batch_number,
                    'subblock_label' => $cycle?->subblock_label,
                    'block_name' => $cycle?->plantingRound?->block?->name,
                    'hst' => $cycle?->planted_at !== null && $cycle->planted_at->lte($today)
                        ? (int) $cycle->planted_at->diffInDays($today) : null,
                ];
            });

        return [
            'date' => $today->toDateString(),
            'farm' => $visibleFarm->only(['id', 'name', 'city', 'district', 'province']),
            'summary' => [
                'pre_planting' => (int) ($counts['planned'] ?? 0),
                'planting' => (int) ($counts['active'] ?? 0),
                'harvest' => (int) ($counts['harvested'] ?? 0),
            ],
            'task_summary' => ['total' => $total, 'completed' => $completed],
            'tasks' => $page,
        ];
    }
}
