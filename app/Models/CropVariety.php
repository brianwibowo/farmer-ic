<?php

namespace App\Models\Farmer;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $commodity_id
 * @property string $name
 * @property string|null $description
 */
#[Fillable(['commodity_id', 'name', 'description'])]
class CropVariety extends FarmerModel
{
    protected $table = 'crop_varieties';

    /** @return BelongsTo<Commodity, $this> */
    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class, 'commodity_id');
    }

    /** @return HasMany<PlantingCycle, $this> */
    public function plantingCycles(): HasMany
    {
        return $this->hasMany(PlantingCycle::class, 'variety_id');
    }
}
