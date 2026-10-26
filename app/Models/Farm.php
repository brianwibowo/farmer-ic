<?php

namespace App\Models;

use App\Models\Farmer\FarmActivity;
use App\Models\Farmer\FarmerModel;
use App\Models\Farmer\FinancialBudget;
use App\Models\Farmer\FinancialProjection;
use App\Models\Farmer\FinancialTransaction;
use App\Models\Farmer\OperationalTarget;
use App\Models\Farmer\PlantingCycle;
use App\Models\Farmer\User as FarmerUser;
use App\Models\Farmer\WeatherRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $owner_id
 * @property string $created_by
 * @property string $name
 * @property string|null $address
 * @property string|null $city
 * @property string|null $district
 * @property string|null $province
 * @property string $total_area_ha
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $image_url
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['owner_id', 'name', 'address', 'city', 'district', 'province', 'total_area_ha', 'latitude', 'longitude', 'image_url'])]
class Farm extends FarmerModel
{
    protected $table = 'farms';

    public $timestamps = true;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'total_area_ha' => 'decimal:4',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<FarmerUser, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(FarmerUser::class, 'owner_id');
    }

    /** @return BelongsTo<FarmerUser, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(FarmerUser::class, 'created_by');
    }

    /** @return HasMany<PlantingCycle, $this> */
    public function plantingCycles(): HasMany
    {
        return $this->hasMany(PlantingCycle::class, 'farm_id');
    }

    /** @return HasMany<FarmActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(FarmActivity::class, 'farm_id');
    }

    /** @return HasMany<WeatherRecord, $this> */
    public function weatherRecords(): HasMany
    {
        return $this->hasMany(WeatherRecord::class, 'farm_id');
    }

    /** @return HasMany<FinancialTransaction, $this> */
    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'farm_id');
    }

    /** @return HasMany<FinancialProjection, $this> */
    public function financialProjections(): HasMany
    {
        return $this->hasMany(FinancialProjection::class, 'farm_id');
    }

    /** @return HasMany<FinancialBudget, $this> */
    public function financialBudgets(): HasMany
    {
        return $this->hasMany(FinancialBudget::class, 'farm_id');
    }

    /** @return HasMany<OperationalTarget, $this> */
    public function operationalTargets(): HasMany
    {
        return $this->hasMany(OperationalTarget::class, 'farm_id');
    }
}
