<?php

namespace App\Models\Farmer;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $planting_cycle_id
 * @property string|null $observation_id
 * @property string $risk_description
 * @property string|null $severity
 * @property string|null $recommendation
 * @property string|null $status
 * @property CarbonImmutable $detected_at
 */
#[Fillable(['planting_cycle_id', 'observation_id', 'risk_description', 'severity', 'recommendation', 'status', 'detected_at'])]
class FarmRisk extends FarmerModel
{
    protected $table = 'farm_risks';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'detected_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<PlantingCycle, $this> */
    public function plantingCycle(): BelongsTo
    {
        return $this->belongsTo(PlantingCycle::class, 'planting_cycle_id');
    }

    /** @return BelongsTo<PlantObservation, $this> */
    public function observation(): BelongsTo
    {
        return $this->belongsTo(PlantObservation::class, 'observation_id');
    }
}
