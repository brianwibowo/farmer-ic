<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $activity_id
 * @property string|null $water_source
 * @property string|null $irrigation_method
 * @property string|null $machine_type
 * @property string|null $duration_hours
 */
#[Fillable(['activity_id', 'water_source', 'irrigation_method', 'machine_type', 'duration_hours'])]
class IrrigationDetail extends ActivityDetail
{
    protected $table = 'irrigation_details';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'duration_hours' => 'decimal:2',
        ];
    }
}
