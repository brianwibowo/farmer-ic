<?php

namespace App\Services\Activity;

use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Workflow prerequisites, separate from authorization; callers supply a scoped cycle. */
class ActivitySequenceService
{
    private const BLOCK_ACTIVITIES = ['land_cleaning', 'loosening', 'beds', 'liming', 'base_fertilization'];

    public static function isBlockPreparation(string $code): bool
    {
        return in_array($code, self::BLOCK_ACTIVITIES, true);
    }

    public function blockedReason(User $actor, PlantingCycle $cycle, string $code): ?string
    {
        if (! in_array($cycle->status, ['planned', 'active'], true)) {
            return 'Subblok sudah ditutup.';
        }
        if (in_array($code, ['incident', 'other', 'land_cleaning'], true)) {
            return null;
        }
        $team = $cycle->farm->team;
        if ($cycle->planted_at === null && in_array($code, ['irrigation', 'weeding', 'follow_up_fertilization', 'growth_check', 'pest_control', 'harvest'], true)) {
            return 'Catat penanaman pada subblok ini terlebih dahulu.';
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
