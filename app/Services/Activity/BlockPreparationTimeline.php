<?php

namespace App\Services\Activity;

use App\Models\FarmActivity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

/** Reads block-level clearing reports independently of subblock stage status. */
class BlockPreparationTimeline
{
    /** @param Collection<int, string> $blockIds
     * @return array<string, array{started_at: mixed, completed_at: mixed}>
     */
    public function clearingForBlocks(User $actor, Team $team, Collection $blockIds): array
    {
        $reports = FarmActivity::visibleTo($actor, $team)->whereIn('block_id', $blockIds)
            ->whereHas('activityType', fn ($q) => $q->visibleTo($actor, $team)->where('code', 'land_cleaning'))
            ->orderBy('activity_date')->get()->groupBy('block_id');

        return $reports->map(fn ($items) => ['started_at' => $items->first()->activity_date, 'completed_at' => $items->last()->activity_date])->all();
    }
}
