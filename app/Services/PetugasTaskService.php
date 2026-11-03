<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class PetugasTaskService
{
    private const TERMINAL_STATUSES = ['completed', 'skipped', 'cancelled'];

    /** @var array<string, int> */
    private const URGENCY_ORDER = [
        'overdue' => 0,
        'today' => 1,
        'upcoming' => 2,
        'completed' => 3,
    ];

    /** @return list<array<string, mixed>> */
    public function forFarm(User $user, Team $team, Farm $farm): array
    {
        $today = CarbonImmutable::today();

        $rows = PlantingCycleTask::query()
            ->visibleTo($user, $team)
            ->with([
                'plantingCycle.farm:id,name,team_id',
                'plantingCycle.plantingRound.block:id,name',
                'plantingCycle.variety:id,name',
            ])
            ->whereHas('plantingCycle', fn (Builder $query) => $query->where('farm_id', $farm->id))
            ->get()
            ->map(fn (PlantingCycleTask $task): array => $this->row($task, $today))
            ->sort(fn (array $left, array $right): int => $this->compare($left, $right))
            ->all();

        return array_values($rows);
    }

    /** @return array<string, mixed> */
    private function row(PlantingCycleTask $task, CarbonImmutable $today): array
    {
        $cycle = $task->plantingCycle;
        $farm = $cycle?->farm;
        $block = $cycle?->plantingRound?->block;
        $variety = $cycle?->variety;
        $anchor = $cycle->planted_at ?? $cycle->planned_planting_at;
        $effective = $task->effectiveScheduledDate();

        return [
            'id' => $task->id,
            'name' => $task->name,
            'instructions' => $task->instructions,
            'status' => $task->status,
            'urgency' => $this->urgency($task, $today),
            'activity_category' => $task->activity_category,
            'activity_subcategory' => $task->activity_subcategory,
            'offset_days' => $task->offset_days,
            'scheduled_date' => $effective?->toDateString(),
            'due_date' => $task->due_date?->toDateString(),
            'is_required' => $task->is_required,
            'linked_activities_count' => 0,
            'latest_submission' => null,
            'can' => [
                'start' => $task->status === 'pending',
                'submit' => $task->status === 'in_progress',
            ],
            'subblok' => [
                'id' => $cycle?->id,
                'number' => (int) $cycle->batch_number,
                'label' => $cycle?->subblock_label,
                'planned_planting_at' => $cycle?->planned_planting_at?->toDateString(),
                'planted_at' => $cycle?->planted_at?->toDateString(),
                'hst' => $anchor === null
                    ? null
                    : (int) $anchor->startOfDay()->diffInDays($today->startOfDay(), false),
                'farm' => $farm === null
                    ? ['id' => '', 'name' => '-']
                    : ['id' => $farm->id, 'name' => $farm->name],
                'block' => $block === null
                    ? ['id' => '', 'name' => '-']
                    : ['id' => $block->id, 'name' => $block->name],
                'variety' => $variety === null
                    ? ['id' => '', 'name' => '-']
                    : ['id' => $variety->id, 'name' => $variety->name],
            ],
        ];
    }

    private function urgency(PlantingCycleTask $task, CarbonImmutable $today): string
    {
        if (in_array($task->status, self::TERMINAL_STATUSES, true)) {
            return 'completed';
        }

        $scheduled = $task->effectiveScheduledDate();
        $due = $task->due_date;

        if ($scheduled === null) {
            return 'upcoming';
        }
        if ($due !== null && $due->lt($today)) {
            return 'overdue';
        }
        if ($scheduled->lte($today) && ($due === null || $due->gte($today))) {
            return 'today';
        }

        return 'upcoming';
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function compare(array $left, array $right): int
    {
        $urgency = self::URGENCY_ORDER[$left['urgency']] <=> self::URGENCY_ORDER[$right['urgency']];
        if ($urgency !== 0) {
            return $urgency;
        }

        $date = strcmp(
            $left['scheduled_date'] ?? '9999-12-31',
            $right['scheduled_date'] ?? '9999-12-31',
        );
        if ($date !== 0) {
            return $date;
        }

        $offset = $left['offset_days'] <=> $right['offset_days'];
        if ($offset !== 0) {
            return $offset;
        }

        return strcmp($left['name'], $right['name']);
    }
}
