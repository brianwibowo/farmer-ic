<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $farm_id
 * @property CarbonImmutable|null $pks_started_at
 * @property CarbonImmutable|null $pks_ends_at
 * @property string $institution
 * @property string $summary
 */
#[Fillable(['farm_id', 'pks_started_at', 'pks_ends_at', 'institution', 'summary'])]
class FarmLegality extends FarmerModel
{
    public $timestamps = true;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'pks_started_at' => 'immutable_date',
            'pks_ends_at' => 'immutable_date',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Farm, $this> */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }
}
