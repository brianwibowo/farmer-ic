<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $activity_id
 * @property int|null $bed_count
 * @property string|null $length_m
 * @property string|null $width_m
 * @property string|null $height_cm
 * @property string|null $bed_spacing_cm
 * @property string|null $area_ha
 */
#[Fillable(['activity_id', 'bed_count', 'length_m', 'width_m', 'height_cm', 'bed_spacing_cm', 'area_ha'])]
class BedPreparationDetail extends ActivityDetail
{
    protected $table = 'bed_preparation_details';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'bed_count' => 'integer',
            'length_m' => 'decimal:3',
            'width_m' => 'decimal:3',
            'height_cm' => 'decimal:2',
            'bed_spacing_cm' => 'decimal:2',
            'area_ha' => 'decimal:4',
        ];
    }
}
