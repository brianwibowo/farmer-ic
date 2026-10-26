<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string|null $image_url
 */
#[Fillable(['name', 'description', 'image_url'])]
class Commodity extends FarmerModel
{
    protected $table = 'commodities';

    /** @return HasMany<CropVariety, $this> */
    public function varieties(): HasMany
    {
        return $this->hasMany(CropVariety::class, 'commodity_id');
    }
}
