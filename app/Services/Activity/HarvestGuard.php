<?php

namespace App\Services\Activity;

use App\Models\HarvestDetail;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Support\LandArea;
use Illuminate\Validation\ValidationException;

/**
 * Harvest area integrity. Callers must hold an exclusive lock on the cycle
 * before validating so concurrent reports cannot spend the same remaining area.
 */
class HarvestGuard
{
    /**
     * @param  array<string, mixed>  $details  Normalized details containing area_ha and weight_ton.
     */
    public function ensureValid(
        User $actor,
        Team $team,
        PlantingCycle $cycle,
        array $details,
        string $areaField = 'details.area',
        string $weightField = 'details.weight',
    ): void {
        $areaM2 = LandArea::squareMetres($details['area_ha'] ?? null);
        $plantedM2 = LandArea::squareMetres($cycle->getAttribute('planted_area_ha'));

        if ($areaM2 === null || $areaM2 <= 0 || $plantedM2 === null || $plantedM2 <= 0) {
            throw ValidationException::withMessages([$areaField => 'Luas panen tidak valid.']);
        }

        $weight = $details['weight_ton'] ?? null;
        if (! is_numeric($weight) || (float) $weight <= 0) {
            throw ValidationException::withMessages([$weightField => 'Berat panen minimal 1 kg.']);
        }

        $previousM2 = LandArea::squareMetres($this->harvestedArea($actor, $team, $cycle)) ?? 0;
        $remainingM2 = $plantedM2 - $previousM2;

        if ($areaM2 > $remainingM2) {
            throw ValidationException::withMessages([
                $areaField => 'Luas panen melebihi sisa luas tanam subblok (sisa '.LandArea::label(max(0, $remainingM2)).').',
            ]);
        }
    }

    private function harvestedArea(User $actor, Team $team, PlantingCycle $cycle): string|int|float
    {
        return HarvestDetail::visibleTo($actor, $team)
            ->whereHas('activity', fn ($query) => $query->where('planting_cycle_id', $cycle->id))
            ->sum('harvested_area_ha');
    }
}
