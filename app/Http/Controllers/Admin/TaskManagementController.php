<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListTasksRequest;
use App\Http\Requests\Admin\RescheduleTaskRequest;
use App\Http\Requests\Admin\SkipTaskRequest;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Models\User;
use App\Services\PlantingCycleTaskManagementService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskManagementController extends Controller
{
    private const TERMINAL_STATUSES = ['completed', 'skipped'];

    public function __construct(private readonly PlantingCycleTaskManagementService $tasks) {}

    public function index(ListTasksRequest $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam;
        abort_unless($team instanceof Team, 403);
        Gate::authorize('viewAny', [PlantingCycleTask::class, $team]);

        $filters = array_replace([
            'search' => '', 'block_id' => '', 'subblok_id' => '', 'status' => '', 'category' => '', 'per_page' => 15,
        ], $request->validated());

        $query = $this->query($user, $team, $filters);
        $summary = $this->summary($query);

        return Inertia::render('tasks/Index', [
            'tasks' => $query->paginate((int) $filters['per_page'])->withQueryString()
                ->through(fn (PlantingCycleTask $task, int $key): array => $this->row($task)),
            'filters' => $filters,
            'summary' => $summary,
            'blocks' => FarmBlock::query()
                ->visibleTo($user, $team)
                ->with('farm:id,name')
                ->orderBy('code')
                ->get(['id', 'farm_id', 'code', 'name'])
                ->map(fn (FarmBlock $block): array => [
                    'id' => $block->id,
                    'farm_id' => $block->farm_id,
                    'code' => $block->code,
                    'name' => $block->name,
                    'farm_name' => $block->farm?->name,
                ])->all(),
            'subbloks' => PlantingCycle::query()
                ->visibleTo($user, $team)
                ->whereNotNull('planting_round_id')
                ->whereNotNull('batch_number')
                ->whereHas('plantingRound.block')
                ->with('plantingRound.block:id,name')
                ->orderBy('batch_number')
                ->get(['id', 'planting_round_id', 'batch_number', 'subblock_label'])
                ->map(fn (PlantingCycle $subblok): array => [
                    'id' => $subblok->id,
                    'block_id' => $subblok->plantingRound?->block?->id,
                    'number' => (int) $subblok->batch_number,
                    'label' => $subblok->subblock_label,
                    'block_name' => $subblok->plantingRound?->block?->name,
                ])->all(),
        ]);
    }

    public function show(Request $request, PlantingCycleTask $task): Response
    {
        Gate::authorize('view', $task);
        $task->load($this->relations());

        return Inertia::render('tasks/Show', [
            'task' => $this->row($task),
        ]);
    }

    public function skip(SkipTaskRequest $request, PlantingCycleTask $task): RedirectResponse
    {
        Gate::authorize('update', $task);
        $this->tasks->skip($task, $request->user(), $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tugas dilewati.']);

        return back();
    }

    public function reschedule(RescheduleTaskRequest $request, PlantingCycleTask $task): RedirectResponse
    {
        Gate::authorize('update', $task);
        $this->tasks->reschedule($task, $request->user(), $request->payload());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jadwal tugas diperbarui.']);

        return back();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PlantingCycleTask>
     */
    private function query(User $user, Team $team, array $filters): Builder
    {
        $query = PlantingCycleTask::query()
            ->visibleTo($user, $team)
            ->whereIn('activity_category', PlantingCycleTask::CATEGORIES)
            ->with($this->relations());

        if (($filters['search'] ?? '') !== '') {
            $search = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['search']).'%';
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'ilike', $search)
                    ->orWhereHas('plantingCycle', fn (Builder $cycle) => $cycle
                        ->where('subblock_label', 'ilike', $search)
                        ->orWhereHas('farm', fn (Builder $farm) => $farm->where('name', 'ilike', $search))
                        ->orWhereHas('plantingRound.block', fn (Builder $block) => $block
                            ->where('name', 'ilike', $search)
                            ->orWhere('code', 'ilike', $search)));
            });
        }

        if (($filters['block_id'] ?? '') !== '') {
            $query->whereHas('plantingCycle.plantingRound.block', fn (Builder $block) => $block
                ->whereKey($filters['block_id']));
        }
        if (($filters['subblok_id'] ?? '') !== '') {
            $query->where('planting_cycle_id', $filters['subblok_id']);
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        }
        if (($filters['category'] ?? '') !== '') {
            $query->where('activity_category', $filters['category']);
        }

        return $query
            ->orderByRaw('COALESCE(rescheduled_date, scheduled_date) ASC NULLS LAST')
            ->orderBy('offset_days')
            ->orderByRaw("CASE activity_category WHEN 'planting' THEN 1 WHEN 'maintenance' THEN 2 WHEN 'harvest' THEN 3 ELSE 4 END")
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * @param  Builder<PlantingCycleTask>  $query
     * @return array{total: int, open: int, overdue: int, today: int, completed: int}
     */
    private function summary(Builder $query): array
    {
        $today = CarbonImmutable::today()->toDateString();

        return [
            'total' => (clone $query)->count(),
            'open' => (clone $query)->whereIn('status', ['pending', 'in_progress'])->count(),
            'overdue' => (clone $query)->whereNotIn('status', self::TERMINAL_STATUSES)
                ->whereNotNull('due_date')->where('due_date', '<', $today)->count(),
            'today' => (clone $query)->whereNotIn('status', self::TERMINAL_STATUSES)
                ->whereNotNull('due_date')->whereNotNull('scheduled_date')
                ->whereRaw('COALESCE(rescheduled_date, scheduled_date) <= ?', [$today])
                ->where('due_date', '>=', $today)->count(),
            'completed' => (clone $query)->whereIn('status', self::TERMINAL_STATUSES)->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function row(PlantingCycleTask $task): array
    {
        $cycle = $task->plantingCycle;
        $block = $cycle?->plantingRound?->block;
        $effective = $task->effectiveScheduledDate();

        return [
            'id' => $task->id,
            'name' => $task->name,
            'instructions' => $task->instructions,
            'status' => $task->status,
            'urgency' => $this->urgency($task),
            'activity_category' => $task->activity_category,
            'activity_subcategory' => $task->activity_subcategory,
            'offset_days' => $task->offset_days,
            'scheduled_date' => $task->scheduled_date?->toDateString(),
            'due_date' => $task->due_date?->toDateString(),
            'effective_scheduled_date' => $effective?->toDateString(),
            'rescheduled_date' => $task->rescheduled_date?->toDateString(),
            'reschedule_reason' => $task->reschedule_reason,
            'rescheduled_at' => $task->rescheduled_at?->toISOString(),
            'skip_reason' => $task->skip_reason,
            'is_required' => $task->is_required,
            'completed_at' => $task->completed_at?->toISOString(),
            'completed_by' => $task->getAttribute('completed_by'),
            'completion_note' => $task->getAttribute('completion_note'),
            'created_at' => $task->created_at?->toISOString(),
            'farm' => $cycle?->farm?->only(['id', 'name']),
            'block' => $block?->only(['id', 'code', 'name']),
            'subblok' => $cycle === null ? null : [
                'id' => $cycle->id,
                'number' => $cycle->batch_number,
                'label' => $cycle->subblock_label,
            ],
            'stage' => $task->stage?->only(['code']),
            'sop_version' => $cycle?->sopTemplateVersion?->only(['id', 'version']),
            'can' => [
                'update' => Gate::allows('update', $task) && in_array($task->status, ['pending', 'in_progress'], true),
            ],
        ];
    }

    private function urgency(PlantingCycleTask $task): string
    {
        if (in_array($task->status, self::TERMINAL_STATUSES, true)) {
            return 'completed';
        }

        $effective = $task->effectiveScheduledDate();
        $due = $task->due_date;
        $today = CarbonImmutable::today();

        if ($effective === null || $due === null) {
            return 'upcoming';
        }
        if ($due->lt($today)) {
            return 'overdue';
        }
        if ($effective->lte($today) && $due->gte($today)) {
            return 'today';
        }

        return 'upcoming';
    }

    /** @return array<int|string, mixed> */
    private function relations(): array
    {
        return [
            'plantingCycle.farm:id,name,team_id',
            'plantingCycle.plantingRound.block:id,code,name',
            'plantingCycle.variety:id,name',
            'plantingCycle.sopTemplateVersion:id,version',
            'stage:id,code',
        ];
    }
}
