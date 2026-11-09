<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\Team;
use App\Models\User;
use App\Services\Activity\BlockPreparationTimeline;
use App\Support\LandArea;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FarmBlockService
{
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
            $query->where(fn ($query) => $query->where('code', 'ilike', $search)->orWhere('name', 'ilike', $search));
        }
        if (in_array($filters['status'] ?? '', ['active', 'inactive'], true)) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        $paginator = $query->orderBy('code')->orderBy('id')->paginate($filters['per_page'] ?? 15)->appends($filters);
        $paginator->setCollection($this->overview($farm, $actor, $team, $paginator->getCollection()));

        return $paginator;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FarmBlock>|null  $selected
     * @return \Illuminate\Support\Collection<int, FarmBlock>
     */
    public function overview(Farm $farm, User $actor, Team $team, ?\Illuminate\Support\Collection $selected = null): \Illuminate\Support\Collection
    {
        $blocks = FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)
            ->when($selected !== null, fn ($query) => $query->whereIn('id', $selected->pluck('id')))->orderBy('code')->get();
        if ($blocks->isEmpty()) {
            return $blocks;
        }
        $batches = PlantingCycle::visibleTo($actor, $team)->whereIn('block_id', $blocks->modelKeys())->orderBy('batch_number')->get();
        $stages = PlantingCycleStage::visibleTo($actor, $team)->whereIn('planting_cycle_id', $batches->modelKeys())->orderBy('position')->get()->groupBy('planting_cycle_id');
        $preparation = app(BlockPreparationTimeline::class)->forCycles($actor, $team, $batches);
        $clearing = app(BlockPreparationTimeline::class)->clearingForBlocks($actor, $team, collect($blocks->modelKeys()));
        $mapped = \App\Models\BlockMappingDetail::visibleTo($actor, $team)->whereIn('block_id', $blocks->modelKeys())->distinct()->pluck('block_id');
        $activeArea = LandArea::squareMetres(FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->where('is_active', true)->sum('area_ha')) ?? 0;
        $farmCapacity = $this->batchCapacity($farm, $blocks->firstOrFail(), PlantingCycle::visibleTo($actor, $team)->toBase());

        return $blocks->map(function (FarmBlock $block) use ($farm, $batches, $stages, $preparation, $clearing, $mapped, $activeArea, $farmCapacity): FarmBlock {
            $block->setAttribute('land_clearing_started_at', $clearing[$block->id]['started_at'] ?? null);
            $block->setAttribute('land_clearing_completed_at', $clearing[$block->id]['completed_at'] ?? null);
            $blockBatches = $batches->where('block_id', $block->id);
            $running = $blockBatches->whereIn('status', ['planned', 'active']);
            $reserved = $running->sum(fn (PlantingCycle $batch): int => LandArea::squareMetres($batch->getAttribute('reserved_area_ha')) ?? 0);
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
                'planned_planting_at' => $batch->planned_planting_at?->toDateString(), 'planted_at' => $batch->planted_at?->toDateString(),
                'stages' => ($stages->get($batch->id) ?? collect())->map(fn ($stage) => [...$stage->only(['code', 'position', 'status', 'started_at', 'completed_at']), ...($preparation[$batch->id][$stage->code] ?? [])])->values(),
            ])->values());

            return $block;
        });
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
        $preparation = app(BlockPreparationTimeline::class)->forCycles($actor, $team, $batches);

        $farm = Farm::visibleTo($actor, $team)->findOrFail($visible->farm_id);

        return ['block' => $visible->toArray(),
            'deletionBlocked' => $batches->isNotEmpty() || \App\Models\BlockMappingDetail::visibleTo($actor, $team)->where('block_id', $visible->id)->exists(),
            'blockCapacity' => $this->formCapacity($farm, $actor, $team, $visible),
            'capacity' => $this->batchCapacity($farm, $visible, PlantingCycle::visibleTo($actor, $team)->toBase()),
            'batches' => $batches->map(fn (PlantingCycle $batch) => [
                ...$batch->only(['id', 'batch_number', 'subblock_label', 'reserved_area_ha', 'planted_area_ha', 'planned_planting_at', 'planted_at', 'status']),
                'planned_planting_at' => $batch->planned_planting_at?->toDateString(),
                'planted_at' => $batch->planted_at?->toDateString(),
                'stages' => ($stages->get($batch->id) ?? collect())->map(fn ($stage) => [...$stage->only(['code', 'position', 'status', 'started_at', 'completed_at']), ...($preparation[$batch->id][$stage->code] ?? [])]),
            ])];
    }

    /** @param array<string, mixed> $data */
    public function create(Farm $farm, User $actor, Team $team, array $data): FarmBlock
    {
        return DB::transaction(function () use ($farm, $actor, $team, $data): FarmBlock {
            $lockedFarm = Farm::visibleTo($actor, $team)->whereKey($farm->id)->lockForUpdate()->firstOrFail();
            $this->validateBlock($lockedFarm, $data);
            $block = new FarmBlock($data);
            $block->forceFill(['farm_id' => $lockedFarm->id, 'created_by' => $actor->id, 'land_clearing_started_at' => now()])->save();

            return $block->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function update(FarmBlock $block, User $actor, Team $team, array $data): FarmBlock
    {
        return DB::transaction(function () use ($block, $actor, $team, $data): FarmBlock {
            $farm = Farm::visibleTo($actor, $team)->whereKey($block->farm_id)->lockForUpdate()->firstOrFail();
            $locked = FarmBlock::visibleTo($actor, $team)->whereKey($block->id)->lockForUpdate()->firstOrFail();
            $this->validateBlock($farm, array_replace($locked->only(['code', 'area_ha', 'is_active']), $data), $locked);
            $locked->fill($data)->save();

            return $locked->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    private function validateBlock(Farm $farm, array $data, ?FarmBlock $block = null): void
    {
        // Physical integrity checks deliberately include every historical reference,
        // independent of the actor's list filters or related-record permissions.
        $others = DB::table('farm_blocks')->where('farm_id', $farm->id)->when($block, fn ($query) => $query->where('id', '<>', $block->id));
        if ((clone $others)->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode blok sudah digunakan dalam kebun ini.']);
        }
        if (($data['is_active'] ?? true) && (clone $others)->where('is_active', true)
            ->selectRaw('COALESCE(SUM(area_ha), 0) + CAST(? AS numeric) > CAST(? AS numeric) AS exceeds', [$data['area_ha'], $farm->total_area_ha])->value('exceeds')) {
            throw ValidationException::withMessages(['area_ha' => 'Total luas blok aktif melebihi luas kebun.']);
        }
        if ($block) {
            $running = DB::table('planting_cycles as c')
                ->where('c.block_id', $block->id)->whereIn('c.status', ['planned', 'active']);
            if (! ($data['is_active'] ?? true) && (clone $running)->exists()) {
                throw ValidationException::withMessages(['is_active' => 'Blok masih memiliki batch berjalan dan belum dapat dinonaktifkan.']);
            }
            if ((clone $running)->selectRaw('COALESCE(SUM(c.reserved_area_ha), 0) > CAST(? AS numeric) AS exceeds', [$data['area_ha']])->value('exceeds')) {
                throw ValidationException::withMessages(['area_ha' => 'Luas blok tidak boleh kurang dari total alokasi batch berjalan.']);
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
            'minimum_area_ha' => LandArea::hectares(max(1, LandArea::squareMetres((clone $running)->sum('reserved_area_ha')))),
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
    public function createBatch(FarmBlock $block, User $actor, Team $team, array $data): PlantingCycle
    {
        return DB::transaction(function () use ($block, $actor, $team, $data): PlantingCycle {
            $farm = Farm::visibleTo($actor, $team)->whereKey($block->farm_id)->lockForUpdate()->firstOrFail();
            $locked = FarmBlock::visibleTo($actor, $team)->whereKey($block->id)->lockForUpdate()->firstOrFail();
            if (! $locked->is_active) {
                throw ValidationException::withMessages(['reserved_area_ha' => 'Aktifkan blok sebelum membuat subblok/batch.']);
            }
            // Integrity checks include all running reservations, even when a
            // caller's read permissions cannot expose every related record.
            $capacity = $this->batchCapacity($farm, $locked, DB::table('planting_cycles'));
            $requested = LandArea::squareMetres($data['reserved_area_ha']);
            if ($requested === null || $requested < 1) {
                throw ValidationException::withMessages(['reserved_area_ha' => 'Masukkan luas batch minimal 0,0001 ha atau 1 m².']);
            }
            if ($requested > LandArea::squareMetres($capacity['available_ha'])) {
                $blockLimited = LandArea::squareMetres($capacity['block_remaining_ha']) <= LandArea::squareMetres($capacity['farm_remaining_ha']);
                $remaining = LandArea::squareMetres($capacity[$blockLimited ? 'block_remaining_ha' : 'farm_remaining_ha']);
                $message = 'Luas batch '.LandArea::label($requested).' melebihi sisa kapasitas '.($blockLimited ? 'blok' : 'kebun').' '.LandArea::label($remaining).'.';
                if (! $blockLimited && LandArea::squareMetres($capacity['legacy_active_ha']) > 0) {
                    $message .= ' '.LandArea::label(LandArea::squareMetres($capacity['legacy_active_ha'])).' masih digunakan tanam aktif di luar batch.';
                }
                $message .= ' Maksimal batch baru '.LandArea::label(LandArea::squareMetres($capacity['available_ha'])).'.';
                throw ValidationException::withMessages(['reserved_area_ha' => $message]);
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
            $completedAt = $locked->land_clearing_completed_at ?? now();
            $batch = new PlantingCycle;
            $batch->forceFill(['farm_id' => $farm->id, 'block_id' => $locked->id,
                'batch_number' => 1 + (int) PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)->max('batch_number'),
                'subblock_label' => $label, 'reserved_area_ha' => $data['reserved_area_ha'], 'planted_area_ha' => '0.0000',
                'planned_planting_at' => $data['planned_planting_at'] ?? null,
                'planted_at' => null, 'variety_id' => null, 'status' => 'planned', 'created_by' => $actor->id])->save();
            foreach (PlantingCycleStage::CODES as $position => $code) {
                $stage = new PlantingCycleStage;
                $stage->forceFill(['planting_cycle_id' => $batch->id, 'code' => $code, 'position' => $position + 1,
                    'status' => $position === 0 ? 'completed' : ($position === 1 ? 'in_progress' : 'upcoming'),
                    'started_at' => $position === 0 ? $locked->land_clearing_started_at : ($position === 1 ? now() : null),
                    'completed_at' => $position === 0 ? $completedAt : null])->save();
            }

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
            $farm = Farm::visibleTo($actor, $team)->whereKey($block->farm_id)->lockForUpdate()->firstOrFail();
            $lockedBlock = FarmBlock::visibleTo($actor, $team)->whereKey($block->id)->lockForUpdate()->firstOrFail();
            $locked = PlantingCycle::visibleTo($actor, $team)->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if (! $this->isBatchEditable($lockedBlock, $locked)) {
                throw ValidationException::withMessages(['subblock_label' => 'Sub blok hanya dapat diubah sebelum penanaman dan penjadwalan SOP.']);
            }
            $capacity = $this->batchCapacity($farm, $lockedBlock, DB::table('planting_cycles')->where('planting_cycles.id', '<>', $locked->id));
            $requested = LandArea::squareMetres($data['reserved_area_ha']);
            if ($requested === null || $requested < 1 || $requested > LandArea::squareMetres($capacity['available_ha'])) {
                throw ValidationException::withMessages(['reserved_area_ha' => 'Luas sub blok maksimal '.LandArea::label(LandArea::squareMetres($capacity['available_ha'])).'.']);
            }
            $label = $data['subblock_label'] ?? $locked->getAttribute('subblock_label');
            if (DB::table('planting_cycles')->where('block_id', $locked->block_id)->where('id', '<>', $locked->id)->where('subblock_label', $label)->exists()) {
                throw ValidationException::withMessages(['subblock_label' => 'Label sub blok sudah dipakai pada blok ini.']);
            }
            $locked->forceFill(['subblock_label' => $label, 'reserved_area_ha' => $data['reserved_area_ha'],
                'planned_planting_at' => $data['planned_planting_at'] ?? null])->save();
        });
    }

    /** @return array<string, string> */
    private function batchCapacity(Farm $farm, FarmBlock $block, Builder $cycles): array
    {
        $usage = $cycles->where('planting_cycles.farm_id', $farm->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN block_id IS NULL AND planting_cycles.status = 'active' THEN planted_area_ha ELSE 0 END), 0) AS legacy_active_ha")
            ->selectRaw("COALESCE(SUM(CASE WHEN block_id IS NOT NULL AND planting_cycles.status IN ('planned', 'active') THEN reserved_area_ha ELSE 0 END), 0) AS farm_batches_ha")
            ->selectRaw("COALESCE(SUM(CASE WHEN block_id = ? AND planting_cycles.status IN ('planned', 'active') THEN reserved_area_ha ELSE 0 END), 0) AS block_batches_ha", [$block->id])
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
