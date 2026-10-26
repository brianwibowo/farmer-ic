<?php

namespace App\Models\Farmer;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $activity_id
 * @property string|null $pesticide_category
 * @property string|null $purpose
 * @property string|null $brand_name
 * @property string|null $active_ingredient
 * @property string|null $dosage_per_tank
 * @property string|null $tank_size_liters
 * @property int|null $tank_count
 */
#[Fillable(['activity_id', 'pesticide_category', 'purpose', 'brand_name', 'active_ingredient', 'dosage_per_tank', 'tank_size_liters', 'tank_count'])]
class PesticideDetail extends ActivityDetail
{
    protected $table = 'pesticide_details';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tank_size_liters' => 'decimal:2',
            'tank_count' => 'integer',
        ];
    }
}
