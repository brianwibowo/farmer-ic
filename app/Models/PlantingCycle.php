<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $farm_id
 * @property string|null $variety_id
 * @property CarbonImmutable|null $planned_planting_at
 * @property CarbonImmutable|null $planted_at
 * @property string|null $sop_template_version_id
 * @property CarbonImmutable|null $estimated_harvest_at
 * @property string $planted_area_ha
 * @property string|null $estimated_weight_ton
 * @property string $status
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['farm_id', 'variety_id', 'planned_planting_at', 'planted_at', 'sop_template_version_id', 'estimated_harvest_at', 'planted_area_ha', 'estimated_weight_ton', 'status'])]
class PlantingCycle extends FarmerModel
{
    protected $table = 'planting_cycles';

    public $timestamps = true;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'planned_planting_at' => 'immutable_date',
            'planted_at' => 'immutable_date',
            'estimated_harvest_at' => 'immutable_date',
            'planted_area_ha' => 'decimal:4',
            'estimated_weight_ton' => 'decimal:3',
            'created_at' => 'immutable_datetime',
            'batch_number' => 'integer',
            'reserved_area_ha' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Farm, $this> */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    /** @return BelongsTo<PlantingRound, $this> */
    public function plantingRound(): BelongsTo
    {
        return $this->belongsTo(PlantingRound::class);
    }

    /** @return HasMany<PlantingCycleStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(PlantingCycleStage::class);
    }

    /** @return BelongsTo<CropVariety, $this> */
    public function variety(): BelongsTo
    {
        return $this->belongsTo(CropVariety::class, 'variety_id');
    }

    /** @return BelongsTo<SopTemplateVersion, $this> */
    public function sopTemplateVersion(): BelongsTo
    {
        return $this->belongsTo(SopTemplateVersion::class, 'sop_template_version_id');
    }

    /** @return HasMany<PlantingCycleTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(PlantingCycleTask::class)
            ->orderBy('scheduled_date')
            ->orderBy('offset_days')
            ->orderByRaw("CASE activity_category WHEN 'planting' THEN 1 WHEN 'maintenance' THEN 2 WHEN 'harvest' THEN 3 ELSE 4 END")
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /** @return HasMany<FarmActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(FarmActivity::class, 'planting_cycle_id');
    }

    /** @return HasMany<PlantObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(PlantObservation::class, 'planting_cycle_id');
    }

    /** @return HasMany<FarmRisk, $this> */
    public function risks(): HasMany
    {
        return $this->hasMany(FarmRisk::class, 'planting_cycle_id');
    }

    /** @return HasMany<FinancialTransaction, $this> */
    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'planting_cycle_id');
    }

    /** @return HasMany<FinancialProjection, $this> */
    public function financialProjections(): HasMany
    {
        return $this->hasMany(FinancialProjection::class, 'planting_cycle_id');
    }

    /** @return HasMany<OperationalTarget, $this> */
    public function operationalTargets(): HasMany
    {
        return $this->hasMany(OperationalTarget::class, 'planting_cycle_id');
    }
}
