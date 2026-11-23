<?php

namespace App\Services\Petugas;

use App\Enums\IncidentSeverity;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\IncidentDetail;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Models\User;
use App\Support\SubblockLifecycle;
use Carbon\CarbonImmutable;

class PetugasHomeService
{
    /** @return array<string, mixed> */
    public function overview(User $actor, ?Farm $farm, ?Team $team): array
    {
        $today = CarbonImmutable::today('Asia/Jakarta');
        $todayDate = $today->toDateString();
        $todayStart = $today->startOfDay();
        $todayEnd = $today->endOfDay();
        $empty = [
            'date' => $todayDate,
            'farm' => null,
            'summary' => ['pre_planting' => 0, 'planting' => 0, 'harvest' => 0],
            'task_summary' => ['total' => 0, 'completed' => 0],
            'incidents' => ['data' => [], 'total' => 0],
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
            ->whereNotNull('block_id')
            ->whereIn('status', ['planned', 'active', 'harvested'])
            ->whereHas('block', fn ($blocks) => $blocks->visibleTo($actor, $team)->where('farm_id', $visibleFarm->id));
        $counts = (clone $cycles)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $tasks = PlantingCycleTask::visibleTo($actor, $team)
            ->whereIn('planting_cycle_id', (clone $cycles)->whereIn('status', SubblockLifecycle::RUNNING)->select('id'))
            ->where(function ($query) use ($todayDate, $todayStart, $todayEnd): void {
                $query->where(function ($active) use ($todayDate): void {
                    $active->whereIn('status', ['pending', 'in_progress'])
                        ->whereRaw('COALESCE(rescheduled_date, scheduled_date) <= ?', [$todayDate]);
                })->orWhere(function ($completed) use ($todayDate, $todayStart, $todayEnd): void {
                    $completed->where('status', 'completed')
                        ->where(function ($todayCompleted) use ($todayDate, $todayStart, $todayEnd): void {
                            $todayCompleted->whereRaw('COALESCE(rescheduled_date, scheduled_date) = ?', [$todayDate])
                                ->orWhereBetween('completed_at', [$todayStart, $todayEnd]);
                        });
                });
            });
        $total = (clone $tasks)->count();
        $completed = (clone $tasks)->where('status', 'completed')->count();
        $page = $tasks->with([
            'stage' => fn ($stages) => $stages->visibleTo($actor, $team),
            'plantingCycle' => fn ($cycles) => $cycles->visibleTo($actor, $team)->with([
                'block' => fn ($blocks) => $blocks->visibleTo($actor, $team),
            ]),
        ])->orderByRaw("CASE WHEN status IN ('in_progress', 'pending') THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status IN ('in_progress', 'pending') AND COALESCE(due_date, rescheduled_date, scheduled_date) < ? THEN 0 ELSE 1 END", [$todayDate])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderByDesc('is_required')
            ->orderByRaw('COALESCE(rescheduled_date, scheduled_date) ASC')
            ->orderBy('due_date')->orderBy('name')->orderBy('id')
            ->paginate(10)->through(function (PlantingCycleTask $task) use ($today, $todayDate): array {
                $cycle = $task->plantingCycle;
                $scheduledDate = $task->effectiveScheduledDate()?->toDateString();
                $dueDate = $task->due_date?->toDateString() ?? $scheduledDate;
                $urgency = match (true) {
                    $task->status === 'completed' => 'completed',
                    $dueDate !== null && $dueDate < $todayDate => 'overdue',
                    default => 'today',
                };

                return [
                    ...$task->only(['id', 'name', 'instructions', 'status', 'is_required', 'offset_days', 'activity_category']),
                    'urgency' => $urgency,
                    'scheduled_date' => $scheduledDate,
                    'due_date' => $task->due_date?->toDateString(),
                    'stage' => $task->stage?->code,
                    'batch_number' => $cycle?->batch_number,
                    'subblock_label' => $cycle?->subblock_label,
                    'block_name' => $cycle?->block?->name,
                    'hst' => $cycle?->planted_at !== null && $cycle->planted_at->lte($today)
                        ? (int) $cycle->planted_at->diffInDays($today) : null,
                ];
            });

        return [
            'date' => $todayDate,
            'farm' => $visibleFarm->only(['id', 'name', 'city', 'district', 'province']),
            'summary' => [
                'pre_planting' => (int) ($counts['planned'] ?? 0),
                'planting' => (int) ($counts['active'] ?? 0),
                'harvest' => (int) ($counts['harvested'] ?? 0),
            ],
            'task_summary' => ['total' => $total, 'completed' => $completed],
            'incidents' => $this->incidentAlerts($actor, $visibleFarm, $team),
            'tasks' => $page,
        ];
    }

    /** @return array{data: list<array<string, mixed>>, total: int} */
    private function incidentAlerts(User $actor, Farm $farm, Team $team): array
    {
        $query = IncidentDetail::visibleTo($actor, $team)
            ->whereIn('severity', ['light', 'low', 'medium', 'moderate', 'heavy', 'high', 'emergency', 'critical'])
            ->whereHas('activity', fn ($activities) => $activities->visibleTo($actor, $team)->where('farm_id', $farm->id)
                ->where('activity_date', '<=', CarbonImmutable::now('Asia/Jakarta'))
                ->where(fn ($statuses) => $statuses->whereIn('status', ['open', 'progress', 'in_progress', 'pending', 'reported'])->orWhereNull('status')));
        $total = (clone $query)->count();
        $items = $query->with([
            'activity' => fn ($activities) => $activities->visibleTo($actor, $team),
            'activity.incidentTicket' => fn ($tickets) => $tickets->visibleTo($actor, $team),
        ])
            ->orderByRaw("CASE WHEN severity IN ('emergency', 'critical', 'heavy', 'high') THEN 0 WHEN severity IN ('medium', 'moderate') THEN 1 ELSE 2 END")
            ->orderByDesc(FarmActivity::visibleTo($actor, $team)->select('activity_date')->whereColumn('farm_activities.id', 'incident_details.activity_id'))
            ->orderBy('activity_id')->limit(4)->get()->map(function (IncidentDetail $incident): array {
                $activity = $incident->activity;
                $ticket = $activity->incidentTicket;
                $severity = IncidentSeverity::fromStored(
                    $ticket !== null ? $ticket->severity : $incident->severity,
                ) ?? IncidentSeverity::Medium;

                return [
                    'id' => $incident->activity_id,
                    'ticket_id' => $ticket?->id,
                    'detail_url' => $ticket !== null
                        ? route('petugas.incidents.show', $ticket)
                        : route('petugas.laporan.show', $incident->activity_id),
                    'severity' => $severity->value,
                    'severity_label' => $severity->label(),
                    'category' => ($ticket !== null ? $ticket->incident_category : null) ?? $incident->incident_category,
                    'description' => $activity->description,
                    'reported_at' => $activity->activity_date->toISOString(),
                    'status' => ($ticket !== null && $ticket->status === 'in_progress') || in_array($activity->status, ['progress', 'in_progress'], true) ? 'in_progress' : 'open',
                    'action' => ($ticket !== null ? $ticket->admin_note : null) ?? $activity->action_taken ?? $incident->proposed_solution,
                ];
            })->all();

        return ['data' => array_values($items), 'total' => $total];
    }
}
