<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $farm_id
 * @property string $recorded_by
 * @property CarbonImmutable $work_date
 * @property string $start_time
 * @property string $end_time
 * @property int $worker_count
 * @property string|null $notes
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['farm_id', 'recorded_by', 'work_date', 'start_time', 'end_time', 'worker_count', 'notes'])]
class DailyActivityLog extends FarmerModel
{
    public $timestamps = true;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['work_date' => 'immutable_date', 'worker_count' => 'integer', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Farm, $this> */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return HasMany<FarmActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(FarmActivity::class)->orderBy('position')->orderBy('id');
    }
}
