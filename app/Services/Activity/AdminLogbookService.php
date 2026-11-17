<?php

namespace App\Services\Activity;

use App\Models\ActivityType;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class AdminLogbookService
{
    public function __construct(private readonly DailyActivityReportService $reports) {}

    /** @return Builder<DailyActivityLog> */
    private function logs(User $actor, Team $team, ?Farm $farm = null): Builder
    {
        return DailyActivityLog::visibleTo($actor, $team)
            ->whereIn('farm_id', Farm::visibleTo($actor, $team)->select('id'))
            ->when($farm, fn (Builder $query) => $query->where('farm_id', $farm->id));
    }

    /** @return Builder<FarmActivity> */
    private function activitiesQuery(User $actor, Team $team, ?Farm $farm = null): Builder
    {
        $query = FarmActivity::visibleTo($actor, $team)
            ->whereIn('farm_id', Farm::visibleTo($actor, $team)->select('id'))
            ->when($farm, fn (Builder $builder) => $builder->where('farm_id', $farm->id));

        if (! Gate::forUser($actor)->allows('viewAny', [DailyActivityLog::class, $team])) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $builder) use ($actor, $team): void {
            $builder->whereIn('daily_activity_log_id', DailyActivityLog::visibleTo($actor, $team)->select('id'))
                ->orWhereNull('daily_activity_log_id');
        });
    }

    /** @param Builder<FarmActivity> $query */
    private function applyIncidentFilter(Builder $query, User $actor, Team $team): void
    {
        $query->where(function (Builder $builder) use ($actor, $team): void {
            $builder->whereHas('activityType', fn (Builder $type) => $type->visibleTo($actor, $team)->where('code', 'incident'))
                ->orWhereHas('incidentDetail', fn (Builder $detail) => $detail->visibleTo($actor, $team));
        });
    }

    /** @param Builder<FarmActivity> $query */
    private function applyDateBounds(Builder $query, User $actor, Team $team, ?string $fromDate, ?string $toDate): void
    {
        if ($fromDate !== null && $fromDate !== '') {
            $fromStart = CarbonImmutable::parse($fromDate, 'Asia/Jakarta')->startOfDay()->utc();
            $query->where(function (Builder $builder) use ($actor, $team, $fromDate, $fromStart): void {
                $builder->whereHas('dailyActivityLog', fn (Builder $log) => $log->visibleTo($actor, $team)->whereDate('work_date', '>=', $fromDate))
                    ->orWhere(fn (Builder $legacy) => $legacy->whereNull('daily_activity_log_id')->where('activity_date', '>=', $fromStart));
            });
        }

        if ($toDate !== null && $toDate !== '') {
            $toEnd = CarbonImmutable::parse($toDate, 'Asia/Jakarta')->startOfDay()->addDay()->utc();
            $query->where(function (Builder $builder) use ($actor, $team, $toDate, $toEnd): void {
                $builder->whereHas('dailyActivityLog', fn (Builder $log) => $log->visibleTo($actor, $team)->whereDate('work_date', '<=', $toDate))
                    ->orWhere(fn (Builder $legacy) => $legacy->whereNull('daily_activity_log_id')->where('activity_date', '<', $toEnd));
            });
        }
    }

    private function likePattern(string $search): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($search)).'%';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array{id: string, work_date: string, farm_id: string, farm_name: string|null, code: string, name: string, source: string, block_name: string|null, subblok: string|null, performer_name: string|null, worker_count: int|null, notes: string|null, photos_count: int, daily_log_id: string|null}>
     */
    public function activities(User $actor, Team $team, array $filters): LengthAwarePaginator
    {
        $query = $this->activitiesQuery($actor, $team)
            ->with([
                'farm' => fn ($farms) => $farms->visibleTo($actor, $team)->select('id', 'name'),
                'activityType' => fn ($types) => $types->visibleTo($actor, $team)->select('id', 'code', 'name'),
                'performer' => fn ($users) => $users->visibleTo($actor, $team)->select('id', 'name'),
                'plantingCycle' => fn ($cycles) => $cycles->visibleTo($actor, $team)->select('id', 'block_id', 'subblock_label'),
                'dailyActivityLog' => fn ($logs) => $logs->visibleTo($actor, $team)->select('id', 'work_date'),
            ])
            ->withCount(['photos' => fn ($photos) => $photos->visibleTo($actor, $team)]);

        if (! empty($filters['farm_id'])) {
            $query->where('farm_id', $filters['farm_id']);
        }
        if (! empty($filters['activity_type'])) {
            $query->whereHas('activityType', fn (Builder $type) => $type->visibleTo($actor, $team)->where('code', $filters['activity_type']));
        }
        if (($filters['source'] ?? null) === 'sop') {
            $query->whereNotNull('planting_cycle_task_id');
        } elseif (($filters['source'] ?? null) === 'mandiri') {
            $query->whereNull('planting_cycle_task_id');
        }
        if (! empty($filters['performed_by'])) {
            $query->where('performed_by', $filters['performed_by']);
        }

        $this->applyDateBounds($query, $actor, $team, $filters['from_date'] ?? null, $filters['to_date'] ?? null);

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $pattern = $this->likePattern($search);
            $matchingBlocks = FarmBlock::visibleTo($actor, $team)->where('name', 'ilike', $pattern)->select('id');
            $query->where(function (Builder $builder) use ($actor, $team, $pattern, $matchingBlocks): void {
                $builder->where('description', 'ilike', $pattern)
                    ->orWhereHas('farm', fn (Builder $farm) => $farm->visibleTo($actor, $team)
                        ->where(fn (Builder $q) => $q->where('name', 'ilike', $pattern)->orWhere('city', 'ilike', $pattern)->orWhere('province', 'ilike', $pattern)))
                    ->orWhereHas('activityType', fn (Builder $type) => $type->visibleTo($actor, $team)->where('name', 'ilike', $pattern))
                    ->orWhereHas('performer', fn (Builder $user) => $user->visibleTo($actor, $team)->where('name', 'ilike', $pattern))
                    ->orWhereHas('plantingCycle', fn (Builder $cycle) => $cycle->visibleTo($actor, $team)
                        ->where(fn (Builder $q) => $q->where('subblock_label', 'ilike', $pattern)->orWhereIn('block_id', clone $matchingBlocks)))
                    ->orWhereIn('block_id', clone $matchingBlocks);
            });
        }

        $paginator = $query
            ->orderByRaw("COALESCE((SELECT work_date FROM daily_activity_logs WHERE daily_activity_logs.id = farm_activities.daily_activity_log_id), (farm_activities.activity_date AT TIME ZONE 'Asia/Jakarta')::date) DESC")
            ->orderByDesc('activity_date')
            ->orderByDesc('created_at')
            ->orderBy('farm_activities.id')
            ->paginate($filters['per_page'] ?? 15)
            ->appends($filters);

        $blockIds = $paginator->getCollection()
            ->map(fn (FarmActivity $activity): mixed => $activity->getAttribute('block_id') ?? $activity->plantingCycle?->block_id)
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values();
        $blockNames = [];
        if ($blockIds->isNotEmpty()) {
            foreach (FarmBlock::visibleTo($actor, $team)->whereIn('id', $blockIds)->get(['id', 'name']) as $block) {
                $blockNames[$block->id] = (string) $block->getAttribute('name');
            }
        }

        return $paginator->through(function (FarmActivity $activity) use ($blockNames): array {
            $log = $activity->dailyActivityLog;
            $type = $activity->activityType;
            $blockId = $activity->getAttribute('block_id') ?? $activity->plantingCycle?->block_id;
            $subblok = $activity->plantingCycle?->getAttribute('subblock_label');

            return [
                'id' => $activity->id,
                'work_date' => $log?->work_date->toDateString() ?? $activity->activity_date->setTimezone('Asia/Jakarta')->toDateString(),
                'farm_id' => $activity->farm_id,
                'farm_name' => $activity->farm?->name,
                'code' => $type->code,
                'name' => $type->name,
                'source' => $activity->getAttribute('planting_cycle_task_id') !== null ? 'sop' : 'mandiri',
                'block_name' => is_string($blockId) ? ($blockNames[$blockId] ?? null) : null,
                'subblok' => is_string($subblok) ? $subblok : null,
                'performer_name' => $activity->performer?->name,
                'worker_count' => $activity->worker_count,
                'notes' => $activity->description,
                'photos_count' => (int) ($activity->getAttribute('photos_count') ?? 0),
                'daily_log_id' => $log?->id,
            ];
        });
    }

    /**
     * @return array{
     *     farms: list<array{id: string, name: string}>,
     *     activity_types: list<array{id: string, code: string, name: string}>,
     *     performers: list<array{id: string, name: string}>
     * }
     */
    public function filterOptions(User $actor, Team $team): array
    {
        $activities = $this->activitiesQuery($actor, $team);

        return [
            'farms' => array_values(Farm::visibleTo($actor, $team)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Farm $farm): array => ['id' => $farm->id, 'name' => $farm->name])->all()),
            'activity_types' => array_values(ActivityType::visibleTo($actor, $team)
                ->where('is_active', true)
                ->where(fn (Builder $q) => $q->where('is_selectable', true)->orWhereIn('id', (clone $activities)->select('activity_type_id')))
                ->orderBy('sort_order')
                ->get(['id', 'code', 'name'])
                ->map(fn (ActivityType $type): array => ['id' => $type->id, 'code' => $type->code, 'name' => $type->name])
                ->all()),
            'performers' => array_values(User::visibleTo($actor, $team)
                ->whereIn('id', (clone $activities)->select('performed_by'))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
                ->all()),
        ];
    }

    /** @return array{farms: int, reports: int, today: int, activities: int, incidents: int} */
    public function summary(User $actor, Team $team, ?Farm $farm = null): array
    {
        $logs = $this->logs($actor, $team, $farm);
        $activities = $this->activitiesQuery($actor, $team, $farm);
        $today = now('Asia/Jakarta')->toDateString();

        $todayActivities = clone $activities;
        $this->applyDateBounds($todayActivities, $actor, $team, $today, $today);

        $incidents = clone $activities;
        $this->applyIncidentFilter($incidents, $actor, $team);

        return [
            'farms' => $farm ? 1 : (Gate::forUser($actor)->allows('viewAny', [Farm::class, $team]) ? Farm::visibleTo($actor, $team)->count() : 0),
            'reports' => (clone $logs)->count(),
            'today' => $todayActivities->count(),
            'activities' => (clone $activities)->count(),
            'incidents' => $incidents->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array{id: string, work_date: string, recorder_name: string|null, activities_count: int, incident_count: int}>
     */
    public function paginate(Farm $farm, User $actor, Team $team, array $filters): LengthAwarePaginator
    {
        $query = $this->logs($actor, $team, $farm)
            ->with(['recorder' => fn ($query) => $query->visibleTo($actor, $team)->select('id', 'name')])
            ->withCount([
                'activities' => fn ($query) => $query->visibleTo($actor, $team),
                'activities as incident_count' => function ($query) use ($actor, $team): void {
                    $query->visibleTo($actor, $team);
                    $this->applyIncidentFilter($query, $actor, $team);
                },
            ]);

        foreach (['from_date' => '>=', 'to_date' => '<='] as $field => $operator) {
            if (! empty($filters[$field])) {
                $query->where('work_date', $operator, $filters[$field]);
            }
        }
        if (! empty($filters['recorded_by'])) {
            $query->where('recorded_by', $filters['recorded_by']);
        }

        return $query->orderByDesc('work_date')->orderByDesc('created_at')->orderBy('id')
            ->paginate($filters['per_page'] ?? 15)->appends($filters)
            ->through(fn (DailyActivityLog $log): array => [
                'id' => $log->id,
                'work_date' => $log->work_date->format('Y-m-d'),
                'recorder_name' => $log->recorder?->name,
                'activities_count' => (int) $log->getAttribute('activities_count'),
                'incident_count' => (int) $log->getAttribute('incident_count'),
            ]);
    }

    /** @return Collection<int, User> */
    public function recorders(Farm $farm, User $actor, Team $team): Collection
    {
        return User::visibleTo($actor, $team)->whereIn('id', $this->logs($actor, $team, $farm)->select('recorded_by'))
            ->orderBy('name')->get(['id', 'name']);
    }

    public function find(Farm $farm, User $actor, Team $team, string $id): DailyActivityLog
    {
        return $this->logs($actor, $team, $farm)->findOrFail($id);
    }

    /** @return array<string, mixed> */
    public function report(Farm $farm, User $actor, Team $team, DailyActivityLog $log): array
    {
        $report = $this->reports->report($actor, $farm, $log);

        return [
            ...$report,
            'created_at' => $log->created_at->toISOString(),
            'updated_at' => $log->updated_at?->toISOString(),
        ];
    }

    /** @return array{farm: array<string, mixed>, activity: array<string, mixed>} */
    public function activityDetail(User $actor, Team $team, Farm $farm, FarmActivity $activity): array
    {
        $shaped = $this->reports->activity($actor, $farm, $activity);
        $log = $activity->daily_activity_log_id === null
            ? null
            : DailyActivityLog::visibleTo($actor, $team)
                ->with(['recorder' => fn ($q) => $q->visibleTo($actor, $team)->select('id', 'name')])
                ->find($activity->daily_activity_log_id);

        if ($log !== null) {
            Gate::forUser($actor)->authorize('view', $log);
        }

        $taskId = $activity->getAttribute('planting_cycle_task_id');
        $task = is_string($taskId) && $taskId !== ''
            ? PlantingCycleTask::visibleTo($actor, $team)->whereKey($taskId)->first()
            : null;

        return [
            'farm' => $farm->only(['id', 'name', 'city', 'district', 'province']),
            'activity' => [
                ...$shaped,
                'work_date' => $log?->work_date->toDateString() ?? $activity->activity_date->setTimezone('Asia/Jakarta')->toDateString(),
                'created_at' => $activity->created_at?->toISOString(),
                'updated_at' => $activity->updated_at?->toISOString(),
                'daily_log' => $log === null ? null : [
                    'id' => $log->id,
                    'work_date' => $log->work_date->toDateString(),
                    'recorder_name' => $log->recorder?->name,
                    'activities_count' => FarmActivity::visibleTo($actor, $team)->where('daily_activity_log_id', $log->id)->count(),
                ],
                'task' => $task === null ? null : [
                    'id' => $task->id,
                    'name' => $task->name,
                    'status' => $task->status,
                ],
            ],
        ];
    }
}
