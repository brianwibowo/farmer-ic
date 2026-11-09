<?php

namespace App\Services\Activity;

use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingRound;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PetugasReportReadService
{
    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function listing(User $actor, Farm $farm, array $filters): LengthAwarePaginator
    {
        $team = $farm->team;
        $query = FarmActivity::visibleTo($actor, $team)->where('farm_id', $farm->id);
        if (! empty($filters['q'])) {
            $query->where(function ($q) use ($filters, $actor, $team): void {
                $term = '%'.$filters['q'].'%';
                $q->where('description', 'ilike', $term)->orWhereHas('activityType', fn ($q) => $q->visibleTo($actor, $team)->where('name', 'ilike', $term))
                    ->orWhereHas('plantingCycle', fn ($q) => $q->visibleTo($actor, $team)->where('subblock_label', 'ilike', $term))
                    ->orWhereIn('block_id', FarmBlock::visibleTo($actor, $team)->where('name', 'ilike', $term)->select('id'));
            });
        }
        if (! empty($filters['type'])) {
            $query->whereHas('activityType', fn ($q) => $q->visibleTo($actor, $team)->where('code', $filters['type']));
        }
        if (! empty($filters['date'])) {
            $query->whereHas('dailyActivityLog', fn ($q) => $q->visibleTo($actor, $team)->whereDate('work_date', $filters['date']));
        }
        if (($filters['source'] ?? '') === 'sop') {
            $query->whereNotNull('planting_cycle_task_id');
        }
        if (($filters['source'] ?? '') === 'mandiri') {
            $query->whereNull('planting_cycle_task_id');
        }
        if (! empty($filters['mine'])) {
            $query->where('performed_by', $actor->id);
        }

        return $query->orderByDesc('activity_date')->orderByDesc('id')->paginate(15)->withQueryString()
            ->through(fn ($activity) => $this->summary($actor, $farm, $activity));
    }

    /** @return array<string, mixed> */
    public function summary(User $actor, Farm $farm, FarmActivity $activity): array
    {
        $team = $farm->team;
        $activity = FarmActivity::visibleTo($actor, $team)->where('farm_id', $farm->id)->whereKey($activity->id)->firstOrFail();
        $type = ActivityType::visibleTo($actor, $team)->whereKey($activity->activity_type_id)->first();
        $cycle = PlantingCycle::visibleTo($actor, $team)->whereKey($activity->planting_cycle_id)->first();
        $round = $cycle ? PlantingRound::visibleTo($actor, $team)->whereKey($cycle->getAttribute('planting_round_id'))->first() : null;
        $blockId = $activity->getAttribute('block_id') ?? $round?->getAttribute('block_id');
        $block = $blockId ? FarmBlock::visibleTo($actor, $team)->whereKey($blockId)->first() : null;
        $log = DailyActivityLog::visibleTo($actor, $team)->whereKey($activity->daily_activity_log_id)->first();
        $photo = ActivityPhoto::visibleTo($actor, $team)->where('activity_id', $activity->id)->first();

        return ['id' => $activity->id, 'name' => $type->name ?? 'Aktivitas', 'code' => $type->code ?? null,
            'date' => $log?->work_date->toDateString() ?? $activity->activity_date->setTimezone('Asia/Jakarta')->toDateString(),
            'location' => implode(' · ', array_filter([$block === null ? null : $block->name, $cycle?->getAttribute('subblock_label')])) ?: $farm->name,
            'source' => $activity->getAttribute('planting_cycle_task_id') === null ? 'Mandiri' : 'SOP',
            'notes' => $activity->description, 'photo' => $photo ? route('daily-logs.photos', ['farm' => $farm, 'activity' => $activity, 'photo' => $photo]) : null];
    }
}
