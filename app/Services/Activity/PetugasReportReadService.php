<?php

namespace App\Services\Activity;

use App\Models\ActivityDetail;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class PetugasReportReadService
{
    public function __construct(private readonly ActivityReportHighlightService $highlights) {}

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function listing(User $actor, Farm $farm, array $filters): LengthAwarePaginator
    {
        $paginator = $this->filteredQuery($actor, $farm, $filters)
            ->orderByDesc('activity_date')->orderByDesc('id')->paginate((int) ($filters['per_page'] ?? 10))->withQueryString();
        $activities = $paginator->getCollection();
        $team = $farm->team;
        $types = ActivityType::visibleTo($actor, $team)->whereKey($activities->pluck('activity_type_id')->filter()->unique())->get()->keyBy('id');
        $cycles = PlantingCycle::visibleTo($actor, $team)->whereKey($activities->pluck('planting_cycle_id')->filter()->unique())->get()->keyBy('id');
        $blockIds = $activities->pluck('block_id')->merge($cycles->pluck('block_id'))->filter()->unique();
        $blocks = FarmBlock::visibleTo($actor, $team)->whereKey($blockIds)->get()->keyBy('id');
        $logs = DailyActivityLog::visibleTo($actor, $team)->whereKey($activities->pluck('daily_activity_log_id')->filter()->unique())->get()->keyBy('id');
        $photos = ActivityPhoto::visibleTo($actor, $team)->whereIn('activity_id', $activities->modelKeys())
            ->orderBy('uploaded_at')->orderBy('id')->get(['id', 'activity_id', 'image_url'])->groupBy('activity_id');
        $activitiesById = $activities->keyBy('id');
        foreach ($photos as $group) {
            foreach ($group as $photo) {
                $photo->setRelation('activity', $activitiesById->get($photo->activity_id));
            }
        }
        $detailCodes = ['land_cleaning' => 'land_cleaning', 'base_fertilization' => 'fertilization', 'growth_observation' => 'growth_check', 'planting' => 'planting', 'harvest' => 'harvest', 'incident' => 'incident', 'other' => 'other'];
        $details = [];
        foreach ($detailCodes as $code => $key) {
            $ids = $activities->filter(fn ($activity) => $types->get($activity->activity_type_id)?->code === $code)->modelKeys();
            $class = ActivityDetailMap::model($key);
            if ($ids !== [] && $class !== null) {
                $details[$code] = $class::visibleTo($actor, $team)->whereIn('activity_id', $ids)->get()->groupBy('activity_id')->map->first();
            }
        }
        $maintenanceIds = $activities->filter(fn ($activity) => $types->get($activity->activity_type_id)?->code === 'maintenance')->modelKeys();
        $observations = $maintenanceIds !== [] && Gate::forUser($actor)->allows('viewAny', [PlantObservation::class, $team])
            ? PlantObservation::visibleTo($actor, $team)->whereIn('activity_id', $maintenanceIds)->get()->groupBy('activity_id')->map->first() : collect();

        return $paginator->through(function (FarmActivity $activity) use ($farm, $types, $cycles, $blocks, $logs, $photos, $details, $observations): array {
            $type = $types->get($activity->activity_type_id);
            $cycle = $cycles->get($activity->planting_cycle_id);
            $block = $blocks->get($activity->getAttribute('block_id') ?? $cycle?->block_id);
            $log = $logs->get($activity->daily_activity_log_id);
            $activityPhotos = $photos->get($activity->id, collect());

            return $this->summary($farm, $activity, $type, $cycle, $block, $log, $activityPhotos, ($details[$type?->code] ?? null)?->get($activity->id), $observations->get($activity->id));
        });
    }

    /** @param array<string, mixed> $filters
     * @return array{sop: int, mandiri: int}
     */
    public function sourceCounts(User $actor, Farm $farm, array $filters): array
    {
        $counts = $this->filteredQuery($actor, $farm, $filters)
            ->selectRaw('COUNT(*) FILTER (WHERE planting_cycle_task_id IS NOT NULL) AS sop, COUNT(*) FILTER (WHERE planting_cycle_task_id IS NULL) AS mandiri')
            ->first();

        return [
            'sop' => (int) ($counts?->getAttribute('sop') ?? 0),
            'mandiri' => (int) ($counts?->getAttribute('mandiri') ?? 0),
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

    /**
     * @param  Collection<int, ActivityPhoto>  $photos
     * @return array<string, mixed>
     */
    private function summary(Farm $farm, FarmActivity $activity, ?ActivityType $type, ?PlantingCycle $cycle, ?FarmBlock $block, ?DailyActivityLog $log, Collection $photos, ActivityDetail|PlantObservation|null $detail, ?PlantObservation $observation): array
    {
        $photo = $photos->first(fn (ActivityPhoto $photo) => ActivityPhotoFile::available($photo));

        return ['id' => $activity->id, 'name' => $type->name ?? 'Aktivitas', 'code' => $type->code ?? null,
            'date' => $log?->work_date->toDateString() ?? $activity->activity_date->setTimezone('Asia/Jakarta')->toDateString(),
            'location' => implode(' · ', array_filter([$block === null ? null : $block->name, $cycle?->getAttribute('subblock_label')])) ?: 'Lokasi spesifik tidak tercatat',
            'source' => $activity->getAttribute('planting_cycle_task_id') === null ? 'Mandiri' : 'SOP',
            'worker_count' => $activity->worker_count,
            'notes' => $activity->description, 'highlight' => $this->highlight($activity, $type, $detail, $observation),
            'photo_count' => $photos->count(),
            'photo' => $photo ? route('daily-logs.photos', ['farm' => $farm, 'activity' => $activity, 'photo' => $photo]) : null];
    }

    private function highlight(FarmActivity $activity, ?ActivityType $type, ActivityDetail|PlantObservation|null $detail, ?PlantObservation $observation): ?string
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
            if ($detail === null) {
                return null;
            }
            $values = array_filter($detail->attributesToArray(), fn ($value) => $value !== null && $value !== '') + $values;
        } elseif ($code === 'maintenance') {
            if ($observation !== null && $observation->growth_phase !== null && $observation->growth_phase !== '') {
                $values['growth_phase'] = $observation->growth_phase;
            }
        }

        return $this->highlights->format($code, $values);
    }
}
