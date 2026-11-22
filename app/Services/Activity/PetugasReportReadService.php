<?php

namespace App\Services\Activity;

use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantObservation;
use App\Models\User;
use App\Support\ActivityDetailMap;
use App\Support\ActivityPhotoFile;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class PetugasReportReadService
{
    public function __construct(private readonly ActivityReportHighlightService $highlights) {}

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function listing(User $actor, Farm $farm, array $filters): LengthAwarePaginator
    {
        return $this->filteredQuery($actor, $farm, $filters)
            ->orderByDesc('activity_date')->orderByDesc('id')->paginate((int) ($filters['per_page'] ?? 10))->withQueryString()
            ->through(fn ($activity) => $this->summary($actor, $farm, $activity));
    }

    /** @param array<string, mixed> $filters
     * @return array{sop: int, mandiri: int}
     */
    public function sourceCounts(User $actor, Farm $farm, array $filters): array
    {
        $query = $this->filteredQuery($actor, $farm, $filters);

        return [
            'sop' => (clone $query)->whereNotNull('planting_cycle_task_id')->count(),
            'mandiri' => (clone $query)->whereNull('planting_cycle_task_id')->count(),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return Builder<FarmActivity>
     */
    private function filteredQuery(User $actor, Farm $farm, array $filters): Builder
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
            $day = CarbonImmutable::parse($filters['date'], 'Asia/Jakarta')->startOfDay();
            $query->where(function ($q) use ($actor, $team, $filters, $day): void {
                $q->whereHas('dailyActivityLog', fn ($q) => $q->visibleTo($actor, $team)->whereDate('work_date', $filters['date']))
                    ->orWhere(fn ($q) => $q->whereNull('daily_activity_log_id')
                        ->where('activity_date', '>=', $day->utc())
                        ->where('activity_date', '<', $day->addDay()->utc()));
            });
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

        return $query;
    }

    /** @return array<string, mixed> */
    public function summary(User $actor, Farm $farm, FarmActivity $activity): array
    {
        $team = $farm->team;
        $activity = FarmActivity::visibleTo($actor, $team)->where('farm_id', $farm->id)->whereKey($activity->id)->firstOrFail();
        $type = ActivityType::visibleTo($actor, $team)->whereKey($activity->activity_type_id)->first();
        $cycle = PlantingCycle::visibleTo($actor, $team)->whereKey($activity->planting_cycle_id)->first();
        $blockId = $activity->getAttribute('block_id') ?? $cycle?->block_id;
        $block = $blockId ? FarmBlock::visibleTo($actor, $team)->whereKey($blockId)->first() : null;
        $log = DailyActivityLog::visibleTo($actor, $team)->whereKey($activity->daily_activity_log_id)->first();
        $photos = ActivityPhoto::visibleTo($actor, $team)->where('activity_id', $activity->id)->orderBy('uploaded_at')->orderBy('id')->get(['id', 'activity_id', 'image_url']);
        $photo = $photos->first(fn (ActivityPhoto $photo) => ActivityPhotoFile::available($photo));

        return ['id' => $activity->id, 'name' => $type->name ?? 'Aktivitas', 'code' => $type->code ?? null,
            'date' => $log?->work_date->toDateString() ?? $activity->activity_date->setTimezone('Asia/Jakarta')->toDateString(),
            'location' => implode(' · ', array_filter([$block === null ? null : $block->name, $cycle?->getAttribute('subblock_label')])) ?: 'Lokasi spesifik tidak tercatat',
            'source' => $activity->getAttribute('planting_cycle_task_id') === null ? 'Mandiri' : 'SOP',
            'worker_count' => $activity->worker_count,
            'notes' => $activity->description, 'highlight' => $this->highlight($actor, $farm, $activity, $type),
            'photo_count' => $photos->count(),
            'photo' => $photo ? route('daily-logs.photos', ['farm' => $farm, 'activity' => $activity, 'photo' => $photo]) : null];
    }

    private function highlight(User $actor, Farm $farm, FarmActivity $activity, ?ActivityType $type): ?string
    {
        if ($type === null) {
            return null;
        }
        $code = $type->code;

        $input = json_decode((string) $activity->getAttribute('task_details'), true);
        $values = is_array($input) ? $input : [];
        $detailKey = match ($code) {
            'land_cleaning' => 'land_cleaning',
            'base_fertilization' => 'fertilization',
            'growth_observation' => 'growth_check',
            'planting' => 'planting',
            'harvest' => 'harvest',
            'incident' => 'incident',
            'other' => 'other',
            default => null,
        };
        if ($detailKey !== null) {
            $detailClass = ActivityDetailMap::model($detailKey);
            if ($detailClass === null) {
                return null;
            }
            $detail = $detailClass::visibleTo($actor, $farm->team)->where('activity_id', $activity->id)->first();
            if ($detail === null) {
                return null;
            }
            $values = array_filter($detail->attributesToArray(), fn ($value) => $value !== null && $value !== '') + $values;
        } elseif ($code === 'maintenance' && Gate::forUser($actor)->allows('viewAny', [PlantObservation::class, $farm->team])) {
            $observation = PlantObservation::visibleTo($actor, $farm->team)->where('activity_id', $activity->id)->first();
            if ($observation !== null && $observation->growth_phase !== null && $observation->growth_phase !== '') {
                $values['growth_phase'] = $observation->growth_phase;
            }
        }

        return $this->highlights->format($code, $values);
    }
}
