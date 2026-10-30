<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'area_ha', 'description', 'is_active'])]
class FarmBlock extends FarmerModel
{
    public $timestamps = true;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['area_ha' => 'decimal:4', 'is_active' => 'boolean', 'land_clearing_started_at' => 'immutable_datetime', 'land_clearing_completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Farm, $this> */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /** @return HasMany<PlantingRound, $this> */
    public function rounds(): HasMany
    {
        return $this->hasMany(PlantingRound::class, 'block_id');
    }
}
