<?php

namespace App\Services;

use App\Models\ActivityPhoto;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\PlantingCycleTask;
use App\Models\PlantObservation;
use App\Models\Team;
use App\Models\User;
use App\Services\Activity\BlockPreparationTimeline;
use App\Support\ActivityPhotoFile;
use App\Support\LandArea;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FarmBlockService
{
    /** @return array{commodities: mixed, sops: mixed} */
    public function configurationOptions(User $actor, Team $team): array
    {
        return [
            'commodities' => Commodity::visibleTo($actor, $team)->orderBy('name')->get(['id', 'name']),
            'sops' => \App\Models\SopTemplate::visibleTo($actor, $team)->where('team_id', $team->id)->orderBy('name')->get(['id', 'name', 'commodity_id', 'is_active']),
        ];
    }

    /** @return LengthAwarePaginator<int, Farm> */
    public function farms(User $actor, Team $team): LengthAwarePaginator
    {
        return Farm::visibleTo($actor, $team)->select(['id', 'name', 'total_area_ha'])->orderBy('name')->orderBy('id')->paginate(15);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, FarmBlock>
     */
    public function paginate(Farm $farm, User $actor, Team $team, array $filters): LengthAwarePaginator
    {
        $query = FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id);
        if (($filters['search'] ?? '') !== '') {
            $search = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($filters['search'])).'%';
            $query->where('name', 'ilike', $search);
        }
        if (in_array($filters['status'] ?? '', ['active', 'inactive'], true)) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        $paginator = $query->orderBy('name')->orderBy('id')->paginate($filters['per_page'] ?? 15)->appends($filters);
        $paginator->setCollection($this->overview($farm, $actor, $team, $paginator->getCollection()));

        return $paginator;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FarmBlock>|null  $selected
     * @return \Illuminate\Support\Collection<int, FarmBlock>
     */
    public function overview(Farm $farm, User $actor, Team $team, ?\Illuminate\Support\Collection $selected = null, bool $includeGrowth = false): \Illuminate\Support\Collection
    {
        $blocks = FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)
            ->when($selected !== null, fn ($query) => $query->whereIn('id', $selected->pluck('id')))->orderBy('name')->get();
        if ($blocks->isEmpty()) {
            return $blocks;
        }
        $batches = PlantingCycle::visibleTo($actor, $team)->whereIn('block_id', $blocks->modelKeys())->orderBy('batch_number')->get();
        $cycleNumbers = \App\Models\FarmCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)->pluck('cycle_number', 'id');
        $stages = PlantingCycleStage::visibleTo($actor, $team)->whereIn('planting_cycle_id', $batches->modelKeys())->orderBy('position')->get()->groupBy('planting_cycle_id');
        $clearing = app(BlockPreparationTimeline::class)->clearingForBlocks($actor, $team, collect($blocks->modelKeys()));
        $today = CarbonImmutable::today('Asia/Jakarta');
        $growth = $includeGrowth ? $this->growthOverview($farm, $batches, $stages, $actor, $team, $today) : [];
        $mapped = \App\Models\BlockMappingDetail::visibleTo($actor, $team)->whereIn('block_id', $blocks->modelKeys())->distinct()->pluck('block_id');
        $activeArea = LandArea::squareMetres(FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->where('is_active', true)->sum('area_ha')) ?? 0;
        $farmCapacity = $this->batchCapacity($farm, $blocks->firstOrFail(), PlantingCycle::visibleTo($actor, $team)->toBase());

        return $blocks->map(function (FarmBlock $block) use ($farm, $batches, $cycleNumbers, $stages, $clearing, $mapped, $activeArea, $farmCapacity, $growth, $includeGrowth, $today): FarmBlock {
            $block->setAttribute('land_clearing_started_at', $clearing[$block->id]['started_at'] ?? null);
            $block->setAttribute('land_clearing_completed_at', $clearing[$block->id]['completed_at'] ?? null);
            $blockBatches = $batches->where('block_id', $block->id);
            $running = $blockBatches->whereIn('status', ['planned', 'active']);
            $reserved = $running->sum(fn (PlantingCycle $batch): int => LandArea::squareMetres($batch->planted_area_ha) ?? 0);
            $area = LandArea::squareMetres($block->area_ha) ?? 0;
            $remaining = max(0, $area - $reserved);
            $block->setRelation('farm', $farm)->makeHidden('farm');
            $block->setAttribute('capacity', [
                'maximum_area_ha' => LandArea::hectares(max(0, (LandArea::squareMetres($farm->total_area_ha) ?? 0) - $activeArea + ($block->is_active ? $area : 0))),
                'minimum_area_ha' => LandArea::hectares(max(1, $reserved)), 'running_batches' => $running->count(),
            ]);
            $block->setAttribute('batch_capacity', [
                ...$farmCapacity, 'block_total_ha' => LandArea::hectares($area), 'block_batches_ha' => LandArea::hectares($reserved),
                'block_remaining_ha' => LandArea::hectares($remaining),
                'available_ha' => LandArea::hectares(min($remaining, LandArea::squareMetres($farmCapacity['farm_remaining_ha']) ?? 0)),
            ]);
            $block->setAttribute('deletionBlocked', $blockBatches->isNotEmpty() || $mapped->contains($block->id));
            $block->setAttribute('batches', $blockBatches->map(fn (PlantingCycle $batch): array => [
                ...$batch->only(['id', 'batch_number', 'subblock_label', 'reserved_area_ha', 'planted_area_ha', 'status']),
                'farm_cycle_id' => $batch->getAttribute('farm_cycle_id'),
                'farm_cycle_number' => $cycleNumbers[$batch->getAttribute('farm_cycle_id')] ?? null,
                'planned_planting_at' => $batch->planned_planting_at?->toDateString(), 'planted_at' => $batch->planted_at?->toDateString(),
                ...($includeGrowth ? ['age' => $this->batchAge($batch, $today)] : []),
                ...(isset($growth[$batch->id]) ? ['growth' => $growth[$batch->id]] : []),
                'stages' => ($stages->get($batch->id) ?? collect())->map(fn ($stage) => $stage->only(['code', 'position', 'status', 'started_at', 'completed_at']))->values(),
            ])->values());

            return $block;
        });
    }

    /** @return array{hst: int|null, as_of: string, status: string} */
    private function batchAge(PlantingCycle $batch, CarbonImmutable $today): array
    {
        $age = ['hst' => null, 'as_of' => $today->toDateString(), 'status' => 'not_planted'];
        if ($batch->status === 'cancelled') {
            return [...$age, 'status' => 'cancelled'];
        }
        if ($batch->planted_at === null) {
            return $age;
        }
        $planted = CarbonImmutable::parse($batch->planted_at->toDateString(), 'Asia/Jakarta');
        if ($planted->gt($today)) {
            return [...$age, 'status' => 'future'];
        }

        return [...$age, 'hst' => (int) $planted->diffInDays($today), 'status' => 'available'];
    }

    /**
     * @param  EloquentCollection<int, PlantingCycle>  $batches
     * @param  \Illuminate\Support\Collection<array-key, EloquentCollection<int, PlantingCycleStage>>  $stages
     * @return array<string, array<string, mixed>>
     */
    private function growthOverview(Farm $farm, EloquentCollection $batches, \Illuminate\Support\Collection $stages, User $actor, Team $team, CarbonImmutable $today): array
    {
        $eligible = $batches->filter(function (PlantingCycle $batch) use ($stages, $today): bool {
            $timeline = $stages->get($batch->id, collect());

            return in_array($batch->status, ['active', 'harvested'], true)
                && $batch->planted_at !== null && $batch->planted_at->toDateString() <= $today->toDateString()
                && $timeline->contains(fn (PlantingCycleStage $stage): bool => $stage->code === 'planting' && $stage->status === 'completed')
                && $timeline->contains(fn (PlantingCycleStage $stage): bool => $stage->code === 'maintenance' && in_array($stage->status, ['in_progress', 'completed'], true));
        })->keyBy('id');
        $eligibleIds = $eligible->modelKeys();
        $canViewObservations = $eligibleIds !== [] && Gate::forUser($actor)->allows('viewAny', [PlantObservation::class, $team]);
        $canViewActivities = $eligibleIds !== [] && Gate::forUser($actor)->allows('viewAny', [FarmActivity::class, $team]);
        $canViewPhotos = $eligibleIds !== [] && Gate::forUser($actor)->allows('viewAny', [ActivityPhoto::class, $team]);
        $canViewTasks = $eligibleIds !== [] && Gate::forUser($actor)->allows('viewAny', [PlantingCycleTask::class, $team]);
        $isAdmin = Gate::forUser($actor)->allows('admin.access');

        $observations = $canViewObservations
            ? PlantObservation::visibleTo($actor, $team)->whereIn('planting_cycle_id', $eligibleIds)
                ->with([
                    'observer:id,name',
                    ...($canViewActivities ? [
                        'activity' => fn ($activities) => $activities->visibleTo($actor, $team)->select(['id', 'farm_id', 'planting_cycle_task_id', 'description']),
                        ...($canViewPhotos ? ['activity.photos' => fn ($photos) => $photos->visibleTo($actor, $team)->orderBy('uploaded_at')->orderBy('id')] : []),
                    ] : []),
                ])
                ->orderByDesc('observed_at')->orderByDesc('id')
                ->get()
            : collect();
        $observationsByCycle = $observations->groupBy('planting_cycle_id');

        $tasks = $canViewTasks
            ? PlantingCycleTask::visibleTo($actor, $team)
                ->whereIn('planting_cycle_id', $eligibleIds)
                ->where('activity_category', 'growth_observation')
                ->orderBy('offset_days')->orderBy('scheduled_date')->orderBy('id')
                ->get()
            : collect();
        $tasksByCycle = $tasks->groupBy('planting_cycle_id');
        $tasksById = $tasks->keyBy('id');
        $extraTaskIds = $canViewTasks && $canViewActivities
            ? $observations->map(fn (PlantObservation $obs) => $obs->activity?->getAttribute('planting_cycle_task_id'))
                ->filter(fn ($id): bool => is_string($id) && $id !== '' && ! $tasksById->has($id))
                ->unique()->values()->all()
            : [];
        if ($extraTaskIds !== []) {
            $tasksById = $tasksById->merge(PlantingCycleTask::visibleTo($actor, $team)->whereIn('id', $extraTaskIds)->get()->keyBy('id'));
        }

        $varieties = $eligibleIds !== []
            ? CropVariety::visibleTo($actor, $team)->whereIn('id', $eligible->pluck('variety_id')->filter())->get(['id', 'commodity_id', 'name'])->keyBy('id')
            : collect();
        $blockCommodities = FarmBlock::visibleTo($actor, $team)->whereIn('id', $batches->pluck('block_id')->filter())->pluck('commodity_id', 'id');
        $commodities = Commodity::visibleTo($actor, $team)->whereIn('id', $varieties->pluck('commodity_id')->merge($blockCommodities->values())->filter())
            ->pluck('name', 'id');

        return $batches->mapWithKeys(function (PlantingCycle $batch) use (
            $actor,
            $farm,
            $blockCommodities,
            $observationsByCycle,
            $tasksByCycle,
            $tasksById,
            $varieties,
            $commodities,
            $eligible,
            $today,
            $isAdmin,
            $canViewActivities,
            $canViewPhotos,
        ): array {
            if (! $eligible->has($batch->id) || $batch->planted_at === null) {
                return [$batch->id => ['available' => false, 'as_of' => $today->toDateString(), 'hst' => null,
                    'hst_percentage' => null, 'commodity' => null, 'variety' => null, 'estimated_harvest_at' => null,
                    'observation' => null, 'observations' => [], 'tasks' => []]];
            }
            $cycleObservations = $observationsByCycle->get($batch->id, collect());
            $latest = $cycleObservations->first();
            $variety = $varieties->get($batch->variety_id);
            $plantedDay = CarbonImmutable::parse($batch->planted_at->toDateString(), 'Asia/Jakarta');
            $hst = (int) $plantedDay->diffInDays($today);
            $hstPercentage = null;
            if ($batch->estimated_harvest_at !== null) {
                $harvestDay = CarbonImmutable::parse($batch->estimated_harvest_at->toDateString(), 'Asia/Jakarta');
                $totalDays = (int) $plantedDay->diffInDays($harvestDay, false);
                if ($totalDays > 0) {
                    $hstPercentage = min(100.0, max(0.0, round(($hst / $totalDays) * 100, 1)));
                }
            }

            $observationItems = $cycleObservations->map(function (PlantObservation $obs) use ($farm, $tasksById, $actor, $isAdmin, $canViewActivities, $canViewPhotos): array {
                $activity = $canViewActivities && $obs->relationLoaded('activity') ? $obs->activity : null;
                $canActivity = $activity instanceof FarmActivity && Gate::forUser($actor)->allows('view', $activity);
                $linkedTaskId = $canActivity ? $activity->getAttribute('planting_cycle_task_id') : null;
                $linkedTask = is_string($linkedTaskId) ? $tasksById->get($linkedTaskId) : null;
                $canTask = $linkedTask instanceof PlantingCycleTask && Gate::forUser($actor)->allows('view', $linkedTask);

                $photos = $canActivity && $canViewPhotos && $activity->relationLoaded('photos')
                    ? $activity->photos->filter(fn (ActivityPhoto $photo): bool => Gate::forUser($actor)->allows('view', $photo))
                        ->map(function (ActivityPhoto $photo) use ($farm, $activity): array {
                            $photo->setRelation('activity', $activity);

                            return [
                                'id' => $photo->id,
                                'url' => route('daily-logs.photos', ['farm' => $farm->id, 'activity' => $activity->id, 'photo' => $photo->id], false),
                                'available' => ActivityPhotoFile::available($photo),
                            ];
                        })->values()->all()
                    : [];

                return [
                    'id' => $obs->id,
                    'observed_at' => $obs->observed_at->toISOString(),
                    'observer_name' => $obs->observer?->name,
                    'phase' => $obs->growth_phase,
                    'notes' => $obs->notes ?? ($canActivity ? $activity->description : null),
                    'task_id' => $canTask ? $linkedTask->id : null,
                    'task_name' => $canTask ? $linkedTask->name : null,
                    'task_url' => $canTask && $isAdmin ? route('tasks.show', $linkedTask, false) : null,
                    'activity_url' => $canActivity && $isAdmin ? route('admin.logbook.activity', $activity, false) : null,
                    'photos' => $photos,
                ];
            })->values()->all();

            $taskItems = $tasksByCycle->get($batch->id, collect())
                ->filter(fn (PlantingCycleTask $task): bool => Gate::forUser($actor)->allows('view', $task))
                ->map(fn (PlantingCycleTask $task): array => [
                    'id' => $task->id,
                    'name' => $task->name,
                    'status' => $task->status,
                    'offset_days' => $task->offset_days,
                    'scheduled_date' => $task->effectiveScheduledDate()?->toDateString(),
                    'due_date' => $task->due_date?->toDateString(),
                    'completed_at' => $task->completed_at?->toISOString(),
                    'url' => $isAdmin ? route('tasks.show', $task, false) : null,
                ])->values()->all();

            return [$batch->id => [
                'available' => true,
                'as_of' => $today->toDateString(),
                'hst' => $hst,
                'hst_percentage' => $hstPercentage,
                'commodity' => $commodities->get($variety->commodity_id ?? $blockCommodities->get($batch->block_id)),
                'variety' => $variety?->name,
                'estimated_harvest_at' => $batch->estimated_harvest_at?->toDateString(),
                'observation' => ! $latest instanceof PlantObservation ? null : [
                    'id' => $latest->id,
                    'observed_at' => $latest->observed_at->toISOString(),
                    'phase' => $latest->growth_phase,
                    'percentage' => $latest->growth_percentage === null ? null : (float) $latest->growth_percentage,
                ],
                'observations' => $observationItems,
                'tasks' => $taskItems,
            ]];
        })->all();
    }

    /** @return array{total: int, active: int, active_area_ha: string} */
    public function summary(Farm $farm, User $actor, Team $team): array
    {
        $query = FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id);
        $active = (clone $query)->where('is_active', true);

        return ['total' => (clone $query)->count(), 'active' => (clone $active)->count(),
            'active_area_ha' => (string) (clone $active)->selectRaw('COALESCE(SUM(area_ha), 0)::numeric(12,4) AS area')->value('area')];
    }

    /** @return array<string, mixed> */
    public function detail(FarmBlock $block, User $actor, Team $team): array
    {
        $visible = FarmBlock::visibleTo($actor, $team)->findOrFail($block->id);
        $clearing = app(BlockPreparationTimeline::class)->clearingForBlocks($actor, $team, collect([$visible->id]));
        $visible->setAttribute('land_clearing_started_at', $clearing[$visible->id]['started_at'] ?? null);
        $visible->setAttribute('land_clearing_completed_at', $clearing[$visible->id]['completed_at'] ?? null);
        $batches = PlantingCycle::visibleTo($actor, $team)->where('block_id', $visible->id)->orderBy('batch_number')->get();
        $stages = PlantingCycleStage::visibleTo($actor, $team)->whereIn('planting_cycle_id', $batches->modelKeys())->orderBy('position')->get()->groupBy('planting_cycle_id');

        $farm = Farm::visibleTo($actor, $team)->findOrFail($visible->farm_id);

        return ['block' => $visible->toArray(),
            'deletionBlocked' => $batches->isNotEmpty() || \App\Models\BlockMappingDetail::visibleTo($actor, $team)->where('block_id', $visible->id)->exists(),
            'blockCapacity' => $this->formCapacity($farm, $actor, $team, $visible),
            'capacity' => $this->batchCapacity($farm, $visible, PlantingCycle::visibleTo($actor, $team)->toBase()),
            'batches' => $batches->map(fn (PlantingCycle $batch) => [
                ...$batch->only(['id', 'batch_number', 'subblock_label', 'reserved_area_ha', 'planted_area_ha', 'planned_planting_at', 'planted_at', 'status']),
                'planned_planting_at' => $batch->planned_planting_at?->toDateString(),
                'planted_at' => $batch->planted_at?->toDateString(),
                'stages' => ($stages->get($batch->id) ?? collect())->map(fn ($stage) => $stage->only(['code', 'position', 'status', 'started_at', 'completed_at'])),
            ])];
    }

    /** @param array<string, mixed> $data */
    public function create(Farm $farm, User $actor, Team $team, array $data): FarmBlock
    {
        return DB::transaction(function () use ($farm, $actor, $team, $data): FarmBlock {
            $lockedFarm = Farm::visibleTo($actor, $team)->whereKey($farm->id)->lockForUpdate()->firstOrFail();
            $this->validateBlock($lockedFarm, $data);
            $this->validateBlockSop($actor, $team, $data);
            $block = new FarmBlock($data);
            $block->forceFill([...array_intersect_key($data, array_flip(['commodity_id', 'default_sop_template_id'])), 'farm_id' => $lockedFarm->id, 'created_by' => $actor->id, 'land_clearing_started_at' => now()])->save();

            return $block->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function update(FarmBlock $block, User $actor, Team $team, array $data): FarmBlock
    {
        return DB::transaction(function () use ($block, $actor, $team, $data): FarmBlock {
            $farm = Farm::visibleTo($actor, $team)->whereKey($block->farm_id)->lockForUpdate()->firstOrFail();
            $locked = FarmBlock::visibleTo($actor, $team)->whereKey($block->id)->lockForUpdate()->firstOrFail();
            $this->validateBlock($farm, array_replace($locked->only(['name', 'area_ha', 'is_active']), $data), $locked);
            $this->validateBlockSop($actor, $team, array_replace($locked->only(['commodity_id', 'default_sop_template_id']), $data));
            $locked->fill($data)->save();
            $locked->forceFill(array_intersect_key($data, array_flip(['commodity_id', 'default_sop_template_id'])))->save();

            return $locked->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    private function validateBlockSop(User $actor, Team $team, array $data): void
    {
        $commodity = $data['commodity_id'] ?? null;
        $sop = $data['default_sop_template_id'] ?? null;
        if ($commodity && ! Commodity::visibleTo($actor, $team)->whereKey($commodity)->exists()) {
            throw ValidationException::withMessages(['commodity_id' => 'Komoditas tidak tersedia.']);
        }
        if ($sop && (! $commodity || ! \App\Models\SopTemplate::visibleTo($actor, $team)->where('team_id', $team->id)->where('commodity_id', $commodity)->whereKey($sop)->exists())) {
            throw ValidationException::withMessages(['default_sop_template_id' => 'Pilih SOP yang sesuai dengan komoditas blok.']);
        }
    }

    /** @param array<string, mixed> $data */
    private function validateBlock(Farm $farm, array $data, ?FarmBlock $block = null): void
    {
        // Physical integrity checks deliberately include every historical reference,
        // independent of the actor's list filters or related-record permissions.
        $others = DB::table('farm_blocks')->where('farm_id', $farm->id)->when($block, fn ($query) => $query->where('id', '<>', $block->id));
        if ((clone $others)->whereRaw('LOWER(TRIM(name)) = LOWER(TRIM(?))', [$data['name']])->exists()) {
            throw ValidationException::withMessages(['name' => 'Nama blok sudah digunakan dalam kebun ini.']);
        }
        if (($data['is_active'] ?? true) && (clone $others)->where('is_active', true)
            ->selectRaw('COALESCE(SUM(area_ha), 0) + CAST(? AS numeric) > CAST(? AS numeric) AS exceeds', [$data['area_ha'], $farm->total_area_ha])->value('exceeds')) {
            throw ValidationException::withMessages(['area_ha' => 'Total luas blok aktif melebihi luas lahan.']);
        }
        if ($block) {
            $running = DB::table('planting_cycles as c')
                ->where('c.block_id', $block->id)->whereIn('c.status', ['planned', 'active']);
            if (! ($data['is_active'] ?? true) && (clone $running)->exists()) {
                throw ValidationException::withMessages(['is_active' => 'Blok masih memiliki batch berjalan dan belum dapat dinonaktifkan.']);
            }
            if ((clone $running)->selectRaw('COALESCE(SUM(c.planted_area_ha), 0) > CAST(? AS numeric) AS exceeds', [$data['area_ha']])->value('exceeds')) {
                throw ValidationException::withMessages(['area_ha' => 'Luas blok tidak boleh kurang dari total luas tanam subblok berjalan.']);
            }
        }
    }

    /** @return array{maximum_area_ha: string, minimum_area_ha: string, running_batches: int} */
    public function formCapacity(Farm $farm, User $actor, Team $team, ?FarmBlock $block = null): array
    {
        $others = FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->where('is_active', true)
            ->when($block, fn ($q) => $q->whereKeyNot($block->id))->sum('area_ha');
        $running = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)
            ->where('block_id', $block?->id)
            ->whereIn('status', ['planned', 'active']);

        return ['maximum_area_ha' => LandArea::hectares(max(0, LandArea::squareMetres($farm->total_area_ha) - LandArea::squareMetres($others))),
            'minimum_area_ha' => LandArea::hectares(max(1, LandArea::squareMetres((clone $running)->sum('planted_area_ha')))),
            'running_batches' => (clone $running)->count()];
    }

    public function delete(FarmBlock $block, User $actor, Team $team): void
    {
        DB::transaction(function () use ($block, $actor, $team): void {
            Farm::visibleTo($actor, $team)->whereKey($block->farm_id)->lockForUpdate()->firstOrFail();
            $locked = FarmBlock::visibleTo($actor, $team)->whereKey($block->id)->lockForUpdate()->firstOrFail();
            if (DB::table('planting_cycles')->where('block_id', $locked->id)->exists()
                || DB::table('block_mapping_details')->where('block_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['delete' => 'Blok memiliki histori batch atau pemetaan. Nonaktifkan blok untuk mempertahankan histori.']);
            }
            $locked->delete();
        });
    }

    /** @param array<string, mixed> $data
     * @return array{batch: PlantingCycle, generation: array{status: string, created: int, rescheduled: int, version_id: string|null, detail: string|null}}
     */
    public function prepareBatch(FarmBlock $block, User $actor, Team $team, array $data): array
    {
        return DB::transaction(function () use ($block, $actor, $team, $data): array {
            $batch = $this->createBatch($block, $actor, $team, $data);

            return ['batch' => $batch, 'generation' => app(PlantingCycleTaskService::class)->generateForSubblok($batch, $actor)];
        });
    }

    /** @param array<string, mixed> $data */
    public function createBatch(FarmBlock $block, User $actor, Team $team, array $data = []): PlantingCycle
    {
        return DB::transaction(function () use ($block, $actor, $team, $data): PlantingCycle {
            $farm = Farm::visibleTo($actor, $team)->whereKey($block->farm_id)->lockForUpdate()->firstOrFail();
            $locked = FarmBlock::visibleTo($actor, $team)->whereKey($block->id)->lockForUpdate()->firstOrFail();
            if (! $locked->is_active) {
                throw ValidationException::withMessages(['subblock_label' => 'Aktifkan blok sebelum membuat subblok/batch.']);
            }
            $capacity = $this->batchCapacity($farm, $locked, DB::table('planting_cycles'));
            if ((LandArea::squareMetres($capacity['available_ha']) ?? 0) < 1) {
                throw ValidationException::withMessages(['subblock_label' => 'Kapasitas lahan pada blok atau lahan sudah penuh. Subblok baru dapat dibuat setelah siklus berjalan selesai.']);
            }
            $labels = PlantingCycle::visibleTo($actor, $team)->where('block_id', $locked->id)->pluck('subblock_label')->all();
            $label = $data['subblock_label'] ?? null;
            if ($label === null) {
                for ($number = 1; ; $number++) {
                    $label = $this->letterLabel($number);
                    if (! in_array($label, $labels, true)) {
                        break;
                    }
                }
            }
            if (in_array($label, $labels, true)) {
                throw ValidationException::withMessages(['subblock_label' => 'Label subblok sudah digunakan pada blok ini.']);
            }
            $batch = new PlantingCycle;
            $farmCycle = app(FarmCycleService::class)->forBatch($farm, $actor, $team, $data);
            $batch->forceFill(['farm_id' => $farm->id, 'block_id' => $locked->id,
                'farm_cycle_id' => $farmCycle->id,
                'batch_number' => 1 + (int) PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)->max('batch_number'),
                'subblock_label' => $label, 'reserved_area_ha' => '0.0000', 'planted_area_ha' => '0.0000',
                'planned_planting_at' => $data['planned_planting_at'] ?? null,
                'planted_at' => null, 'variety_id' => null, 'status' => 'planned', 'created_by' => $actor->id])->save();
            foreach (PlantingCycleStage::CODES as $position => $code) {
                $stage = new PlantingCycleStage;
                $stage->forceFill(['planting_cycle_id' => $batch->id, 'code' => $code, 'position' => $position + 1,
                    'status' => 'upcoming', 'started_at' => null, 'completed_at' => null])->save();
            }

            app(\App\Services\Activity\PlantingCycleStageService::class)->initialize($actor, $team, $batch);

            return $batch->refresh();
        });
    }

    public function isBatchEditable(FarmBlock $block, PlantingCycle $batch): bool
    {
        return $block->is_active && $batch->status === 'planned' && $batch->planted_at === null && $batch->sop_template_version_id === null;
    }

    /** @param array<string, mixed> $data */
    public function updateBatch(FarmBlock $block, PlantingCycle $batch, User $actor, Team $team, array $data): void
    {
        DB::transaction(function () use ($block, $batch, $actor, $team, $data): void {
            Farm::visibleTo($actor, $team)->whereKey($block->farm_id)->lockForUpdate()->firstOrFail();
            $lockedBlock = FarmBlock::visibleTo($actor, $team)->whereKey($block->id)->lockForUpdate()->firstOrFail();
            $locked = PlantingCycle::visibleTo($actor, $team)->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if (! $this->isBatchEditable($lockedBlock, $locked)) {
                throw ValidationException::withMessages(['subblock_label' => 'Sub blok hanya dapat diubah sebelum penanaman dan penjadwalan SOP.']);
            }
            $label = $data['subblock_label'] ?? $locked->getAttribute('subblock_label');
            if (DB::table('planting_cycles')->where('block_id', $locked->block_id)->where('id', '<>', $locked->id)->where('subblock_label', $label)->exists()) {
                throw ValidationException::withMessages(['subblock_label' => 'Label sub blok sudah dipakai pada blok ini.']);
            }
            $locked->forceFill(['subblock_label' => $label,
                'planned_planting_at' => $data['planned_planting_at'] ?? null])->save();
        });
    }

    /**
     * @param  EloquentCollection<int, FarmBlock>  $blocks
     * @return array<string, array{available_ha: string, block_remaining_ha: string, farm_remaining_ha: string}>
     */
    public function capacitiesForBlocks(Farm $farm, User $actor, Team $team, EloquentCollection $blocks): array
    {
        if ($blocks->isEmpty()) {
            return [];
        }
        $first = $blocks->firstOrFail();
        $farmCapacity = $this->batchCapacity($farm, $first, PlantingCycle::visibleTo($actor, $team)->toBase());
        $farmRemaining = LandArea::squareMetres($farmCapacity['farm_remaining_ha']) ?? 0;
        $blockUsed = PlantingCycle::visibleTo($actor, $team)
            ->whereIn('block_id', $blocks->modelKeys())
            ->whereIn('status', ['planned', 'active'])
            ->selectRaw('block_id, COALESCE(SUM(planted_area_ha), 0) AS used_ha')
            ->groupBy('block_id')
            ->pluck('used_ha', 'block_id');

        return $blocks->mapWithKeys(function (FarmBlock $block) use ($blockUsed, $farmRemaining, $farmCapacity): array {
            $area = LandArea::squareMetres($block->area_ha) ?? 0;
            $used = LandArea::squareMetres($blockUsed[$block->id] ?? 0) ?? 0;
            $blockRemaining = max(0, $area - $used);

            return [
                $block->id => [
                    'available_ha' => LandArea::hectares(min($blockRemaining, $farmRemaining)),
                    'block_remaining_ha' => LandArea::hectares($blockRemaining),
                    'farm_remaining_ha' => $farmCapacity['farm_remaining_ha'],
                ],
            ];
        })->all();
    }

    /** @return array<string, string> */
    private function batchCapacity(Farm $farm, FarmBlock $block, Builder $cycles): array
    {
        $usage = $cycles->where('planting_cycles.farm_id', $farm->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN block_id IS NULL AND planting_cycles.status = 'active' THEN planted_area_ha ELSE 0 END), 0) AS legacy_active_ha")
            ->selectRaw("COALESCE(SUM(CASE WHEN block_id IS NOT NULL AND planting_cycles.status IN ('planned', 'active') THEN planted_area_ha ELSE 0 END), 0) AS farm_batches_ha")
            ->selectRaw("COALESCE(SUM(CASE WHEN block_id = ? AND planting_cycles.status IN ('planned', 'active') THEN planted_area_ha ELSE 0 END), 0) AS block_batches_ha", [$block->id])
            ->first();
        $legacy = LandArea::squareMetres(data_get($usage, 'legacy_active_ha', 0));
        $farmBatches = LandArea::squareMetres(data_get($usage, 'farm_batches_ha', 0));
        $blockBatches = LandArea::squareMetres(data_get($usage, 'block_batches_ha', 0));
        $farmRemaining = max(0, LandArea::squareMetres($farm->total_area_ha) - $legacy - $farmBatches);
        $blockRemaining = max(0, LandArea::squareMetres($block->area_ha) - $blockBatches);

        return [
            'farm_total_ha' => LandArea::hectares(LandArea::squareMetres($farm->total_area_ha)),
            'block_total_ha' => LandArea::hectares(LandArea::squareMetres($block->area_ha)),
            'legacy_active_ha' => LandArea::hectares($legacy),
            'farm_batches_ha' => LandArea::hectares($farmBatches),
            'block_batches_ha' => LandArea::hectares($blockBatches),
            'farm_remaining_ha' => LandArea::hectares($farmRemaining),
            'block_remaining_ha' => LandArea::hectares($blockRemaining),
            'available_ha' => LandArea::hectares(min($farmRemaining, $blockRemaining)),
        ];
    }

    private function letterLabel(int $number): string
    {
        $label = '';
        do {
            $number--;
            $label = chr(65 + $number % 26).$label;
            $number = intdiv($number, 26);
        } while ($number > 0);

        return $label;
    }
}
