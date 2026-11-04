<?php

namespace App\Services\Activity;

use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AdminLogbookService
{
    /** @return Builder<DailyActivityLog> */
    private function logs(User $actor, Team $team, ?Farm $farm = null): Builder
    {
        return DailyActivityLog::visibleTo($actor, $team)
            ->whereIn('farm_id', Farm::visibleTo($actor, $team)->select('id'))
            ->when($farm, fn ($query) => $query->where('farm_id', $farm->id));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array{id: string, name: string, city: string|null, district: string|null, province: string|null, log_count: int, latest_work_date: mixed}>
     */
    public function farms(User $actor, Team $team, array $filters): LengthAwarePaginator
    {
        $query = Farm::visibleTo($actor, $team)->select(['id', 'name', 'city', 'district', 'province'])
            ->selectSub($this->logs($actor, $team)->whereColumn('farm_id', 'farms.id')->selectRaw('count(*)'), 'log_count')
            ->selectSub($this->logs($actor, $team)->whereColumn('farm_id', 'farms.id')->selectRaw('max(work_date)'), 'latest_work_date');
        if ($search = trim($filters['search'] ?? '')) {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(fn ($query) => $query->where('name', 'ilike', $pattern)->orWhere('city', 'ilike', $pattern)->orWhere('province', 'ilike', $pattern));
        }

        return $query->orderBy('name')->orderBy('id')->paginate($filters['per_page'] ?? 15)->appends($filters)
            ->through(fn (Farm $farm): array => [
                ...$farm->only(['id', 'name', 'city', 'district', 'province']),
                'log_count' => (int) $farm->getAttribute('log_count'),
                'latest_work_date' => $farm->getAttribute('latest_work_date'),
            ]);
    }

    /** @return array{farms: int, reports: int, today: int, activities: int, obstacles: int} */
    public function summary(User $actor, Team $team, ?Farm $farm = null): array
    {
        $logs = $this->logs($actor, $team, $farm);
        $activities = FarmActivity::visibleTo($actor, $team)->whereIn('daily_activity_log_id', (clone $logs)->select('id'));

        return [
            'farms' => $farm ? 1 : Farm::visibleTo($actor, $team)->count(),
            'reports' => (clone $logs)->count(),
            'today' => (clone $logs)->where('work_date', now('Asia/Jakarta')->toDateString())->count(),
            'activities' => (clone $activities)->count(),
            'obstacles' => (clone $activities)->where('obstacle_status', 'reported')->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array{id: string, notes: string|null, work_date: string, start_time: string, end_time: string, recorder_name: string|null, activities_count: int, obstacle_count: int}>
     */
    public function paginate(Farm $farm, User $actor, Team $team, array $filters): LengthAwarePaginator
    {
        $query = $this->logs($actor, $team, $farm)
            ->with(['recorder' => fn ($query) => $query->visibleTo($actor, $team)->select('id', 'name')])
            ->withCount([
                'activities' => fn ($query) => $query->visibleTo($actor, $team),
                'activities as obstacle_count' => fn ($query) => $query->visibleTo($actor, $team)->where('obstacle_status', 'reported'),
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
                ...$log->only(['id', 'notes']),
                'work_date' => $log->work_date->format('Y-m-d'),
                'start_time' => substr($log->start_time, 0, 5),
                'end_time' => substr($log->end_time, 0, 5),
                'recorder_name' => $log->recorder?->name,
                'activities_count' => (int) $log->getAttribute('activities_count'),
                'obstacle_count' => (int) $log->getAttribute('obstacle_count'),
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
        return $this->logs($actor, $team, $farm)->with([
            'recorder' => fn ($query) => $query->visibleTo($actor, $team)->select('id', 'name'),
            'activities' => fn ($query) => $query->visibleTo($actor, $team)->with([
                'activityType' => fn ($query) => $query->visibleTo($actor, $team),
                'blockMappingDetail' => fn ($query) => $query->visibleTo($actor, $team),
            ]),
        ])->findOrFail($id);
    }
}
