<?php

namespace App\Services\Activity;

use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Workflow prerequisites, separate from authorization; callers supply a scoped cycle. */
class ActivitySequenceService
{
    private const SOIL = ['loosening', 'beds', 'liming', 'base_fertilization'];

    public static function isBlockPreparation(string $code): bool
    {
        return $code === 'land_cleaning' || in_array($code, self::SOIL, true);
    }

    public function blockReason(User $actor, FarmBlock $block, string $code): ?string
    {
        if (! in_array($code, [...self::SOIL, 'planting'], true)) {
            return null;
        }
        $team = $block->farm->team;
        $activities = FarmActivity::visibleTo($actor, $team)->where('farm_id', $block->farm_id)->where('block_id', $block->id);
        if ($code !== 'planting' && ! (clone $activities)->whereHas('activityType', fn ($q) => $q->visibleTo($actor, $team)->where('code', 'land_cleaning'))->exists()) {
            return 'Catat pembersihan lahan pada blok ini terlebih dahulu.';
        }
        if ($code === 'planting' && ! (clone $activities)->whereHas('activityType', fn ($q) => $q->visibleTo($actor, $team)->whereIn('code', self::SOIL))->exists()) {
            return 'Catat minimal satu aktivitas pengolahan tanah pada blok ini terlebih dahulu.';
        }

        return null;
    }

    public function blockedReason(User $actor, PlantingCycle $cycle, string $code): ?string
    {
        if (in_array($code, ['incident', 'other', 'land_cleaning'], true)) {
            return null;
        }
        $team = $cycle->farm->team;
        if ($cycle->planted_at === null && in_array($code, ['irrigation', 'weeding', 'follow_up_fertilization', 'growth_check', 'pest_control', 'harvest'], true)) {
            return 'Catat penanaman pada subblok ini terlebih dahulu.';
        }
        // Existing planted subblocks must remain usable, including additional planting reports.
        if ($cycle->planted_at === null && in_array($code, [...self::SOIL, 'planting'], true)) {
            $blockId = $cycle->getAttribute('block_id');
            $block = $blockId === null ? null : FarmBlock::visibleTo($actor, $team)->where('farm_id', $cycle->farm_id)->whereKey($blockId)->first();
            if (! $block) {
                return 'Blok subblok ini tidak tersedia.';
            }

            return $this->blockReason($actor, $block, $code);
        }
        if ($code === 'harvest') {
            $unfinished = PlantingCycleTask::visibleTo($actor, $team)->where('planting_cycle_id', $cycle->id)
                ->where('activity_category', 'maintenance')->whereNotIn('status', ['completed', 'skipped'])->exists();
            if ($unfinished) {
                return 'Selesaikan seluruh tugas SOP perawatan atau minta Admin melewati tugas yang tidak diperlukan sebelum panen.';
            }
        }

        return null;
    }

    public function ensureAllowed(User $actor, PlantingCycle $cycle, string $code, string $field = 'cycle_id'): void
    {
        $reason = $this->blockedReason($actor, $cycle, $code);
        if ($reason !== null) {
            throw ValidationException::withMessages([$field => $reason]);
        }
    }
}
