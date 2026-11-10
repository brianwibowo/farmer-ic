<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $activity_id
 * @property string|null $area_ha
 * @property string|null $method
 * @property string|null $product_name
 * @property string|null $dosage_per_tank
 * @property string|null $water_liters
 * @property int|null $tank_count
 */
#[Fillable(['activity_id', 'area_ha', 'method', 'product_name', 'dosage_per_tank', 'water_liters', 'tank_count'])]
class LandCleaningDetail extends ActivityDetail
{
    protected $table = 'land_cleaning_details';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'area_ha' => 'decimal:4',
            'water_liters' => 'decimal:2',
            'tank_count' => 'integer',
        ];
    }
}
