<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmCycle;
use App\Models\HarvestDetail;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class FarmCycleService
{
    /** Resolve only a cycle in the actor's visible farm. Null means create a new cycle. */
    public function selection(Farm $farm, User $actor, Team $team, array $data): ?FarmCycle
    {
        if (! empty($data['create_farm_cycle'])) {
            return null;
        }
        $query = FarmCycle::visibleTo($actor, $team)->where('farm_id', $farm->id);
        if (! empty($data['farm_cycle_id'])) {
            $cycle = $query->whereKey($data['farm_cycle_id'])->first();
            if ($cycle === null) {
                throw ValidationException::withMessages(['farm_cycle_id' => 'Siklus tidak tersedia pada lahan ini.']);
            }

            return $cycle;
        }

        // Older clients may omit the selector; join the latest cycle, never advance it by area.
        return $query->orderByDesc('cycle_number')->first();
    }

    /** Called inside the batch transaction, after the farm row has been locked. */
    public function forBatch(Farm $farm, User $actor, Team $team, array $data): FarmCycle
    {
        $cycle = $this->selection($farm, $actor, $team, $data);
        if ($cycle !== null) {
            return $cycle;
        }
        $cycle = new FarmCycle;
        $cycle->forceFill([
            'farm_id' => $farm->id,
            'cycle_number' => 1 + (int) FarmCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)->max('cycle_number'),
            'created_by' => $actor->id,
            'created_at' => now(),
        ])->save();

        return $cycle;
    }

    /** All aggregates use the same visible record boundary as the cycle selector. */
    public function options(Farm $farm, User $actor, Team $team): array
    {
        $cycles = FarmCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)->orderByDesc('cycle_number')->get();
        if ($cycles->isEmpty()) {
            return [];
        }
        $batches = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)->whereIn('farm_cycle_id', $cycles->modelKeys());
        $counts = (clone $batches)->selectRaw('farm_cycle_id, COUNT(*) as total')->groupBy('farm_cycle_id')->pluck('total', 'farm_cycle_id');
        $activities = FarmActivity::visibleTo($actor, $team)->whereIn('planting_cycle_id', (clone $batches)->select('id'));
        $harvests = HarvestDetail::visibleTo($actor, $team)
            ->whereIn('harvest_details.activity_id', $activities->select('id'))
            ->join('farm_activities as cycle_activity', 'cycle_activity.id', '=', 'harvest_details.activity_id')
            ->join('planting_cycles as cycle_batch', 'cycle_batch.id', '=', 'cycle_activity.planting_cycle_id')
            ->selectRaw('cycle_batch.farm_cycle_id, SUM(harvest_details.gross_weight_ton) as total')
            ->groupBy('cycle_batch.farm_cycle_id')->pluck('total', 'cycle_batch.farm_cycle_id');

        return $cycles->map(fn (FarmCycle $cycle): array => [
            'id' => $cycle->id, 'cycle_number' => $cycle->cycle_number,
            'batch_count' => (int) ($counts[$cycle->id] ?? 0),
            'harvested_ton' => (string) ($harvests[$cycle->id] ?? '0.000'),
        ])->all();
    }
}
