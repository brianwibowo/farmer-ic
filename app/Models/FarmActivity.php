<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $farm_id
 * @property string|null $planting_cycle_id
 * @property string $activity_type_id
 * @property string $performed_by
 * @property CarbonImmutable $activity_date
 * @property string|null $description
 * @property string|null $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['farm_id', 'planting_cycle_id', 'activity_type_id', 'performed_by', 'activity_date', 'description', 'status'])]
class FarmActivity extends FarmerModel
{
    protected $table = 'farm_activities';

    public $timestamps = true;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'activity_date' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Farm, $this> */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    /** @return BelongsTo<PlantingCycle, $this> */
    public function plantingCycle(): BelongsTo
    {
        return $this->belongsTo(PlantingCycle::class, 'planting_cycle_id');
    }

    /** @return BelongsTo<ActivityType, $this> */
    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class, 'activity_type_id');
    }

    /** @return BelongsTo<FarmUser, $this> */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(FarmUser::class, 'performed_by');
    }

    /** @return HasMany<ActivityPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(ActivityPhoto::class, 'activity_id');
    }

    /** @return HasOne<PlantingDetail, $this> */
    public function plantingDetail(): HasOne
    {
        return $this->hasOne(PlantingDetail::class, 'activity_id');
    }

    /** @return HasOne<FertilizationDetail, $this> */
    public function fertilizationDetail(): HasOne
    {
        return $this->hasOne(FertilizationDetail::class, 'activity_id');
    }

    /** @return HasOne<IrrigationDetail, $this> */
    public function irrigationDetail(): HasOne
    {
        return $this->hasOne(IrrigationDetail::class, 'activity_id');
    }

    /** @return HasOne<PesticideDetail, $this> */
    public function pesticideDetail(): HasOne
    {
        return $this->hasOne(PesticideDetail::class, 'activity_id');
    }

    /** @return HasOne<IncidentDetail, $this> */
    public function incidentDetail(): HasOne
    {
        return $this->hasOne(IncidentDetail::class, 'activity_id');
    }

    /** @return HasOne<HarvestDetail, $this> */
    public function harvestDetail(): HasOne
    {
        return $this->hasOne(HarvestDetail::class, 'activity_id');
    }

    /** @return HasOne<PlantObservation, $this> */
    public function observation(): HasOne
    {
        return $this->hasOne(PlantObservation::class, 'activity_id');
    }
}
