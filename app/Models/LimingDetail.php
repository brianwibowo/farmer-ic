<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $activity_id
 * @property string|null $area_ha
 * @property string|null $material_type
 * @property string|null $brand_name
 * @property string|null $quantity_value
 * @property string|null $quantity_unit
 * @property string|null $sack_size_kg
 * @property string|null $application_method
 */
#[Fillable(['activity_id', 'area_ha', 'material_type', 'brand_name', 'quantity_value', 'quantity_unit', 'sack_size_kg', 'application_method'])]
class LimingDetail extends ActivityDetail
{
    protected $table = 'liming_details';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'area_ha' => 'decimal:4',
            'quantity_value' => 'decimal:3',
            'sack_size_kg' => 'decimal:3',
        ];
    }
}
