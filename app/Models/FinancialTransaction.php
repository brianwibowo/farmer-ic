<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $farm_id
 * @property string|null $planting_cycle_id
 * @property string $category_id
 * @property string $recorded_by
 * @property CarbonImmutable $transaction_date
 * @property string $transaction_type
 * @property string $amount
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['farm_id', 'planting_cycle_id', 'category_id', 'recorded_by', 'transaction_date', 'transaction_type', 'amount', 'description'])]
class FinancialTransaction extends FarmerModel
{
    protected $table = 'financial_transactions';

    public $timestamps = true;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'transaction_date' => 'immutable_date',
            'amount' => 'decimal:2',
            'created_at' => 'immutable_datetime',
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

    /** @return BelongsTo<FinancialCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
