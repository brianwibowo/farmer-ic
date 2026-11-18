<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $farm_id
 * @property int $cycle_number
 */
#[Fillable(['farm_id', 'cycle_number', 'created_by', 'created_at'])]
class FarmCycle extends FarmerModel
{
    protected function casts(): array
    {
        return ['cycle_number' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(PlantingCycle::class, 'farm_cycle_id');
    }
}
