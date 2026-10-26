<?php

namespace App\Models\Farmer;

use App\Models\Farm;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $farm_id
 * @property CarbonImmutable $recorded_at
 * @property string|null $condition
 * @property string|null $temperature_c
 * @property string|null $humidity_percentage
 * @property string|null $data_source
 */
#[Fillable(['farm_id', 'recorded_at', 'condition', 'temperature_c', 'humidity_percentage', 'data_source'])]
class WeatherRecord extends FarmerModel
{
    protected $table = 'weather_records';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'immutable_datetime',
            'temperature_c' => 'decimal:2',
            'humidity_percentage' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Farm, $this> */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }
}
