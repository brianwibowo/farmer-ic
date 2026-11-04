<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $activity_id
 * @property string|null $description
 * @property string|null $area_ha
 */
#[Fillable(['activity_id', 'description', 'area_ha'])]
class OtherDetail extends ActivityDetail
{
    protected $table = 'other_details';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'area_ha' => 'decimal:4',
        ];
    }
}
