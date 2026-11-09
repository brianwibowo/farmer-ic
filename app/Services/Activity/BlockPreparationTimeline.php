<?php

namespace App\Services\Activity;

use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\PlantingRound;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

/** Projects block preparation from reports, without rewriting historical stage records. */
class BlockPreparationTimeline
{
    /** @return array<string, array{started_at: mixed, completed_at: mixed}> */
    public function clearingForBlocks(User $actor, Team $team, Collection $blockIds): array
    {
        $reports = FarmActivity::visibleTo($actor, $team)->whereIn('block_id', $blockIds)
            ->whereHas('activityType', fn ($q) => $q->visibleTo($actor, $team)->where('code', 'land_cleaning'))
            ->orderBy('activity_date')->get()->groupBy('block_id');

        return $reports->map(fn ($items) => ['started_at' => $items->first()->activity_date, 'completed_at' => $items->last()->activity_date])->all();
    }

    /** @param Collection<int, PlantingCycle> $cycles */
    public function apply(User $actor, Team $team, Collection $cycles): void
    {
        $preparation = $this->forCycles($actor, $team, $cycles);
        foreach ($cycles as $cycle) {
            foreach ($cycle->stages as $stage) {
                if (isset($preparation[$cycle->id][$stage->code])) {
                    $stage->forceFill($preparation[$cycle->id][$stage->code]);
                }
            }
        }
    }

    /** @param Collection<int, PlantingCycle> $cycles
     * @return array<string, array<string, array{status: string, started_at: mixed, completed_at: mixed}>>
     */
    public function forCycles(User $actor, Team $team, Collection $cycles): array
    {
        $rounds = PlantingRound::visibleTo($actor, $team)->whereIn('id', $cycles->pluck('planting_round_id')->filter())->pluck('block_id', 'id');
        $reports = FarmActivity::visibleTo($actor, $team)->whereIn('block_id', $rounds->values()->unique())
            ->whereHas('activityType', fn ($q) => $q->visibleTo($actor, $team)->whereIn('code', ['land_cleaning', 'loosening', 'beds', 'liming', 'base_fertilization']))
            ->with(['activityType' => fn ($q) => $q->visibleTo($actor, $team)])->orderBy('activity_date')->get();
        $result = [];
        foreach ($cycles as $cycle) {
            $blockId = $rounds->get($cycle->getAttribute('planting_round_id'));
            if ($blockId === null) {
                continue; // Legacy cycles without a provable block retain their recorded timeline.
            }
            $blockReports = $reports->where('block_id', $blockId)->where('farm_id', $cycle->farm_id);
            foreach (['land_clearing' => ['land_cleaning'], 'soil_preparation' => ['loosening', 'beds', 'liming', 'base_fertilization']] as $stage => $codes) {
                $matching = $blockReports->filter(fn ($report) => in_array($report->activityType?->code, $codes, true));
                $result[$cycle->id][$stage] = $matching->isEmpty()
                    ? ['status' => 'upcoming', 'started_at' => null, 'completed_at' => null]
                    : ['status' => 'completed', 'started_at' => $matching->first()->activity_date, 'completed_at' => $matching->last()->activity_date];
            }
        }

        return $result;
    }
}
