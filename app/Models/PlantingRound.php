<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlantingRound extends FarmerModel
{
    public $timestamps = true;

    /** @return BelongsTo<FarmBlock, $this> */
    public function block(): BelongsTo
    {
        return $this->belongsTo(FarmBlock::class, 'block_id');
    }

    /** @return HasMany<PlantingCycle, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(PlantingCycle::class);
    }
}
