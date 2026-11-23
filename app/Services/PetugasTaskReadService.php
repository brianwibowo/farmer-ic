<?php

namespace App\Services;

use App\Models\ActivityPhoto;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\PlantObservation;
use App\Models\User;
use App\Services\Activity\TaskReportingWindow;
use App\Support\ActivityPhotoFile;
use App\Support\SubblockLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PetugasTaskReadService
{
    /** @return array<string, mixed> */
    public function listing(User $actor, Farm $farm, array $filters): array
    {
        $team = $farm->team;
        $today = CarbonImmutable::today('Asia/Jakarta')->toDateString();
        // PostgreSQL date arithmetic mirrors TaskReportingWindow, including undated cycles.
        $start = 'CASE WHEN planting_cycles.planted_at IS NULL THEN NULL ELSE COALESCE(planting_cycle_tasks.rescheduled_date, planting_cycles.planted_at + planting_cycle_tasks.offset_days) END';
        $urgency = "CASE WHEN planting_cycle_tasks.status IN ('completed', 'skipped') THEN 'completed' WHEN planting_cycles.planted_at IS NULL THEN 'upcoming' WHEN ($start + planting_cycle_tasks.tolerance_days) < ?::date THEN 'overdue' WHEN $start <= ?::date THEN 'today' ELSE 'upcoming' END";
        $base = PlantingCycleTask::visibleTo($actor, $team)
            ->join('planting_cycles', 'planting_cycles.id', '=', 'planting_cycle_tasks.planting_cycle_id')
            ->whereHas('plantingCycle', fn ($query) => $query->visibleTo($actor, $team)
                ->where('farm_id', $farm->id)->whereIn('status', SubblockLifecycle::statuses($filters['view'])));
        $statusQuery = (clone $base)->selectRaw("$urgency AS urgency", [$today, $today]);
        $counts = DB::query()->fromSub($statusQuery, 'task_statuses')->selectRaw("COUNT(*) FILTER (WHERE urgency = 'overdue') AS overdue, COUNT(*) FILTER (WHERE urgency = 'today') AS today, COUNT(*) FILTER (WHERE urgency = 'upcoming') AS upcoming, COUNT(*) FILTER (WHERE urgency = 'completed') AS completed")->first();
        $summary = ['overdue' => (int) $counts->overdue, 'today' => (int) $counts->today, 'upcoming' => (int) $counts->upcoming, 'completed' => (int) $counts->completed];
        $cycles = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)
            ->whereIn('status', SubblockLifecycle::statuses($filters['view']))
            ->whereHas('tasks', fn ($query) => $query->visibleTo($actor, $team))->get()->keyBy('id');
        $categories = (clone $base)->distinct()->pluck('planting_cycle_tasks.activity_category')->all();
        $query = clone $base;
        if ($filters['status'] !== 'all') {
            $statuses = $filters['status'] === 'active' ? ['overdue', 'today'] : [$filters['status']];
            $query->whereRaw("($urgency) IN (".implode(', ', array_fill(0, count($statuses), '?')).')', [$today, $today, ...$statuses]);
        }
        if ($filters['category']) {
            $query->where('planting_cycle_tasks.activity_category', $filters['category']);
        }
        if ($filters['block_id']) {
            $query->where('planting_cycles.block_id', $filters['block_id']);
        }
        if ($filters['subblok_id']) {
            $query->where('planting_cycles.id', $filters['subblok_id']);
        }
        if ($filters['q'] !== '') {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
            $visibleBlocks = FarmBlock::visibleTo($actor, $team)->select('farm_blocks.id', 'farm_blocks.name');
            $query->leftJoinSub($visibleBlocks, 'search_blocks', fn ($join) => $join->on('search_blocks.id', '=', 'planting_cycles.block_id'))
                ->whereRaw("(COALESCE(planting_cycle_tasks.name, '') || ' ' || COALESCE(search_blocks.name, '') || ' ' || COALESCE(planting_cycles.subblock_label, 'null')) ILIKE ?", [$term]);
        }
        $paginator = $query->select('planting_cycle_tasks.*')
            ->withCount(['activities as visible_activities_count' => fn ($query) => $query->visibleTo($actor, $team)])
            ->orderByRaw("CASE ($urgency) WHEN 'overdue' THEN 0 WHEN 'today' THEN 1 WHEN 'upcoming' THEN 2 ELSE 3 END", [$today, $today])
            ->orderByRaw("$start ASC NULLS LAST")
            ->orderBy('planting_cycle_tasks.offset_days')->orderBy('planting_cycle_tasks.name')->orderBy('planting_cycle_tasks.id')
            ->paginate($filters['per_page'])->withQueryString();
        $models = $paginator->getCollection();
        $pageCycles = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)
            ->whereKey($models->pluck('planting_cycle_id')->unique())->get()->keyBy('id');
        $cycles = $cycles->merge($pageCycles)->keyBy('id');
        $blocks = FarmBlock::visibleTo($actor, $team)->whereKey($cycles->pluck('block_id')->filter()->unique())->get()->keyBy('id');
        $scopedFarm = Farm::visibleTo($actor, $team)->findOrFail($farm->id);
        $scopedFarm->setRelation('team', $team);
        $varieties = CropVariety::visibleTo($actor, $team)->whereKey($pageCycles->pluck('variety_id')->filter()->unique())->get()->keyBy('id');
        $commodityIds = $varieties->pluck('commodity_id')->merge($blocks->pluck('commodity_id'))->filter()->unique();
        $commodities = Commodity::visibleTo($actor, $team)->whereKey($commodityIds)->get()->keyBy('id');
        $items = $models->map(function (PlantingCycleTask $task) use ($pageCycles, $scopedFarm, $blocks, $varieties, $commodities): array {
            $cycle = $pageCycles->get($task->planting_cycle_id);
            $block = $cycle ? $blocks->get($cycle->block_id) : null;
            if (! $cycle || ! $block) {
                throw (new ModelNotFoundException)->setModel(! $cycle ? PlantingCycle::class : FarmBlock::class);
            }
            $cycle->setRelation('farm', $scopedFarm);
            $task->setRelation('plantingCycle', $cycle);
            $variety = $varieties->get($cycle->variety_id);
            $commodity = $commodities->get($variety === null ? $block->commodity_id : $variety->commodity_id);

            return $this->formatTask($task, $cycle, $scopedFarm, $block, $variety, $commodity, (int) $task->visible_activities_count);
        });
        $paginator->setCollection($items);

        return [
            'tasks' => $paginator, 'summary' => $summary, 'taskModels' => $models->keyBy('id'),
            'options' => [
                'blocks' => $blocks->values()->map->only(['id', 'name'])->all(),
                'subbloks' => $cycles->filter(fn ($cycle) => $blocks->has($cycle->block_id))->map(fn ($cycle) => ['id' => $cycle->id, 'block_id' => $cycle->block_id, 'label' => $cycle->subblock_label, 'number' => $cycle->batch_number])->values()->all(),
                'categories' => $categories,
            ],
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
        $commodityId = $variety === null ? $block->commodity_id : $variety->commodity_id;
        $commodity = $commodityId !== null ? Commodity::visibleTo($actor, $team)->find($commodityId) : null;
        $count = FarmActivity::visibleTo($actor, $team)->where('planting_cycle_task_id', $task->id)->count();

        return $this->formatTask($task, $cycle, $farm, $block, $variety, $commodity, $count);
    }

    private function formatTask(PlantingCycleTask $task, PlantingCycle $cycle, Farm $farm, FarmBlock $block, ?CropVariety $variety, ?Commodity $commodity, int $count): array
    {
        $today = CarbonImmutable::today('Asia/Jakarta');
        $window = app(TaskReportingWindow::class)->range($task, $cycle);
        $effective = $window['start'] ?? null;
        $urgency = match (true) {
            in_array($task->status, ['completed', 'skipped'], true) => 'completed',
            $effective === null => 'upcoming',
            $window['end'] < $today->toDateString() => 'overdue',
            $effective <= $today->toDateString() => 'today',
            default => 'upcoming',
        };

        return [
            ...$task->only(['id', 'name', 'instructions', 'status', 'activity_category', 'offset_days', 'hok', 'is_required']),
            'scheduled_date' => $effective,
            'reporting_window' => $window,
            'due_date' => $window['end'] ?? null, 'urgency' => $urgency,
            'completed_at' => $task->completed_at?->toISOString(),
            'completed_by' => $task->getAttribute('completed_by'),
            'completion_note' => $task->getAttribute('completion_note'),
            'linked_activities_count' => $count,
            'can' => ['record' => false, 'complete' => false],
            'subblok' => [
                'id' => $cycle->id, 'number' => $cycle->getAttribute('batch_number'), 'label' => $cycle->getAttribute('subblock_label'),
                'status' => $cycle->status,
                'planned_planting_at' => $cycle->planned_planting_at?->toDateString(), 'planted_at' => $cycle->planted_at?->toDateString(),
                'hst' => $cycle->planted_at !== null && $cycle->planted_at->toDateString() <= $today->toDateString()
                    ? (int) CarbonImmutable::parse($cycle->planted_at->toDateString(), 'Asia/Jakarta')->diffInDays($today)
                    : null,
                'farm' => $farm->only(['id', 'name']), 'block' => $block->only(['id', 'name']),
                'commodity' => $commodity?->only(['id', 'name']),
                'variety' => $variety?->only(['id', 'name']) ?? ['id' => '', 'name' => 'Belum ditentukan'],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function reports(User $actor, PlantingCycleTask $task): array
    {
        $team = $task->plantingCycle->farm->team;
        $canViewObservations = Gate::forUser($actor)->allows('viewAny', [PlantObservation::class, $team]);

        return array_values(FarmActivity::visibleTo($actor, $team)->where('planting_cycle_task_id', $task->id)
            ->with([
                'performer:id,name',
                ...($canViewObservations ? ['observation' => fn ($q) => $q->visibleTo($actor, $team)] : []),
            ])
            ->orderByDesc('activity_date')->get()
            ->map(function (FarmActivity $activity) use ($actor, $team, $task, $canViewObservations): array {
                $log = DailyActivityLog::visibleTo($actor, $team)->find($activity->daily_activity_log_id);
                $decoded = $task->activity_category === 'growth_observation' && ! $canViewObservations
                    ? null
                    : json_decode((string) $activity->getAttribute('task_details'), true);
                $details = is_array($decoded) ? $decoded : [];
                $observation = $canViewObservations ? $activity->observation : null;
                if ($observation instanceof PlantObservation && $observation->growth_phase !== null) {
                    $details = ['growth_phase' => $observation->growth_phase] + $details;
                }

                return [
                    'id' => $activity->id, 'work_date' => $log?->work_date->toDateString(),
                    'performer_name' => $activity->performer?->name,
                    'worker_count' => $activity->worker_count,
                    'notes' => $activity->description, 'details' => $details === [] ? null : $details,
                    'photos' => ActivityPhoto::visibleTo($actor, $team)->where('activity_id', $activity->id)->get()->map(fn ($photo) => [
                        'id' => $photo->id, 'url' => route('petugas.aktivitas.photos', ['activity' => $activity->id, 'photo' => $photo->id]),
                        'available' => ActivityPhotoFile::available($photo),
                    ])->all(),
                ];
            })->all());
    }
}
