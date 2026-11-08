<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $activity_id
 * @property string|null $harvested_area_ha
 * @property string|null $gross_weight_ton
 * @property string|null $moisture_percentage
 * @property string|null $color
 * @property string|null $ripeness
 * @property string|null $cleanliness
 */
#[Fillable(['activity_id', 'harvested_area_ha', 'gross_weight_ton', 'moisture_percentage', 'color', 'ripeness', 'cleanliness'])]
class HarvestDetail extends ActivityDetail
{
    protected $table = 'harvest_details';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'harvested_area_ha' => 'decimal:4',
            'gross_weight_ton' => 'decimal:3',
            'moisture_percentage' => 'decimal:2',
        ];
    }
}
