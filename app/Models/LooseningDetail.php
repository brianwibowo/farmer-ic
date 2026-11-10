<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $activity_id
 * @property string|null $area_ha
 * @property string|null $method
 * @property string|null $depth_cm
 */
#[Fillable(['activity_id', 'area_ha', 'method', 'depth_cm'])]
class LooseningDetail extends ActivityDetail
{
    protected $table = 'loosening_details';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'area_ha' => 'decimal:4',
            'depth_cm' => 'decimal:2',
        ];
    }
}
