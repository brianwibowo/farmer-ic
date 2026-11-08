<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $planting_cycle_id
 * @property string|null $activity_id
 * @property string $observed_by
 * @property CarbonImmutable $observed_at
 * @property string|null $growth_phase
 * @property string|null $average_height_cm
 * @property string|null $disease_indication
 * @property string|null $pest_indication
 * @property string|null $health_status
 * @property string|null $growth_percentage
 * @property string|null $plant_condition
 * @property string|null $notes
 */
#[Fillable(['planting_cycle_id', 'activity_id', 'observed_by', 'observed_at', 'growth_phase', 'average_height_cm', 'disease_indication', 'pest_indication', 'health_status', 'growth_percentage', 'plant_condition', 'notes'])]
class PlantObservation extends FarmerModel
{
    protected $table = 'plant_observations';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'observed_at' => 'immutable_datetime',
            'average_height_cm' => 'decimal:2',
            'growth_percentage' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<PlantingCycle, $this> */
    public function plantingCycle(): BelongsTo
    {
        return $this->belongsTo(PlantingCycle::class, 'planting_cycle_id');
    }

    /** @return BelongsTo<FarmActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(FarmActivity::class, 'activity_id');
    }

    /** @return BelongsTo<User, $this> */
    public function observer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'observed_by');
    }

    /** @return HasMany<FarmRisk, $this> */
    public function risks(): HasMany
    {
        return $this->hasMany(FarmRisk::class, 'observation_id');
    }
}
