<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantingCycleStage extends FarmerModel
{
    public const CODES = ['land_clearing', 'soil_preparation', 'basic_fertilizer', 'planting', 'maintenance', 'harvest'];

    public $timestamps = true;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['position' => 'integer', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PlantingCycle, $this> */
    public function plantingCycle(): BelongsTo
    {
        return $this->belongsTo(PlantingCycle::class);
    }
}
