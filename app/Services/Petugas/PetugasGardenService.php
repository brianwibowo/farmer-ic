<?php

namespace App\Services\Petugas;

use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use UnexpectedValueException;

class PetugasGardenService
{
    /** @return array<string, mixed> */
    public function overview(User $actor, ?Farm $farm, ?Team $team, ?FarmBlock $selected = null, int $page = 1): array
    {
        $empty = [
            'farm' => null, 'summary' => ['blocks' => 0, 'batches' => 0], 'blocks' => [], 'selected_block' => null,
            'batches' => ['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1, 'prev_page_url' => null, 'next_page_url' => null],
        ];
        if ($farm === null || $team === null) {
            return $empty;
        }
        $farm = Farm::visibleTo($actor, $team)->whereKey($farm->id)->first();
        if ($farm === null) {
            return $empty;
        }
        $blocks = FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)
            ->orderByDesc('is_active')->orderBy('code')->orderBy('id')->get();
        $clearing = app(\App\Services\Activity\BlockPreparationTimeline::class)->clearingForBlocks($actor, $team, collect($blocks->modelKeys()));
        foreach ($blocks as $block) {
            $block->setAttribute('land_clearing_started_at', $clearing[$block->id]['started_at'] ?? null);
            $block->setAttribute('land_clearing_completed_at', $clearing[$block->id]['completed_at'] ?? null);
        }
        $cycles = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)->whereNotNull('block_id');
        $blockCounts = (clone $cycles)->selectRaw('block_id, COUNT(*) AS total')->groupBy('block_id')->pluck('total', 'block_id');
        $selected = $selected === null ? $blocks->first() : $blocks->firstWhere('id', $selected->id);
        $blockPayload = fn (FarmBlock $block): array => [
            ...$block->only(['id', 'code', 'name', 'area_ha', 'description', 'is_active']),
            'batches_count' => (int) ($blockCounts[$block->id] ?? 0),
            'land_clearing_started_at' => $this->clearingTimestamp($block, 'land_clearing_started_at'),
            'land_clearing_completed_at' => $this->clearingTimestamp($block, 'land_clearing_completed_at'),
        ];
        $batches = $empty['batches'];
        if ($selected !== null) {
            $today = CarbonImmutable::today('Asia/Jakarta');
            $lastPage = max(1, (int) ceil(($blockCounts[$selected->id] ?? 0) / 6));
            $batches = (clone $cycles)->where('block_id', $selected->id)
                ->with(['stages' => fn ($stages) => $stages->visibleTo($actor, $team)->orderBy('position')])
                ->orderByRaw("CASE WHEN status IN ('planned', 'active') THEN 0 ELSE 1 END")
                ->orderByDesc('batch_number')->orderBy('id')->paginate(6, ['*'], 'page', max(1, min($page, $lastPage)))->appends(['block' => $selected->id]);
            app(\App\Services\Activity\BlockPreparationTimeline::class)->apply($actor, $team, $batches->getCollection());
            $batches = $batches->through(function (PlantingCycle $batch) use ($today): array {
                $stages = $batch->stages;
                $current = $stages->firstWhere('status', 'in_progress') ?? $stages->where('status', 'completed')->last();

                return [
                    ...$batch->only(['id', 'batch_number', 'subblock_label', 'reserved_area_ha', 'planted_area_ha', 'status']),
                    'planned_planting_at' => $batch->planned_planting_at?->toDateString(),
                    'planted_at' => $batch->planted_at?->toDateString(),
                    'estimated_harvest_at' => $batch->estimated_harvest_at?->toDateString(),
                    'hst' => $batch->planted_at !== null && $batch->planted_at->lte($today) ? (int) $batch->planted_at->diffInDays($today) : null,
                    'current_stage' => $current?->code,
                    'stages' => $stages->map(fn ($stage) => $stage->only(['code', 'position', 'status']))->values()->all(),
                ];
            });
        }

        return [
            'farm' => $farm->only(['id', 'name', 'land_tenure', 'address', 'city', 'district', 'province', 'total_area_ha', 'latitude', 'longitude']),
            'summary' => ['blocks' => $blocks->count(), 'batches' => (int) $blockCounts->sum()],
            'blocks' => $blocks->map($blockPayload)->all(),
            'selected_block' => $selected === null ? null : $blockPayload($selected),
            'batches' => $batches,
        ];
    }

    private function clearingTimestamp(FarmBlock $block, string $attribute): ?string
    {
        $date = $block->getAttribute($attribute);
        if ($date === null) {
            return null;
        }
        if (! $date instanceof DateTimeInterface) {
            throw new UnexpectedValueException('Farm block clearing timestamps must be cast to dates.');
        }

        return $date->format(DateTimeInterface::ATOM);
    }
}
