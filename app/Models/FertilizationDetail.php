<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $activity_id
 * @property string|null $fertilization_stage
 * @property string|null $fertilizer_type
 * @property string|null $brand_name
 * @property string|null $subsidy_status
 * @property string|null $application_method
 * @property string|null $quantity_kg
 * @property string|null $area_ha
 * @property string|null $quantity_unit
 * @property string|null $sack_size_kg
 */
#[Fillable(['activity_id', 'fertilization_stage', 'fertilizer_type', 'brand_name', 'subsidy_status', 'application_method', 'quantity_kg', 'area_ha', 'quantity_unit', 'sack_size_kg'])]
class FertilizationDetail extends ActivityDetail
{
    protected $table = 'fertilization_details';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_kg' => 'decimal:3',
            'area_ha' => 'decimal:4',
            'sack_size_kg' => 'decimal:3',
        ];
    }
}
