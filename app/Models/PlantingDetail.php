<?php

namespace App\Models\Farmer;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $activity_id
 * @property string|null $seed_name
 * @property string|null $quantity_kg
 * @property string|null $planting_pattern
 * @property string|null $area_ha
 */
#[Fillable(['activity_id', 'seed_name', 'quantity_kg', 'planting_pattern', 'area_ha'])]
class PlantingDetail extends ActivityDetail
{
    protected $table = 'planting_details';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_kg' => 'decimal:3',
            'area_ha' => 'decimal:4',
        ];
    }
}
