<?php

namespace App\Models\Farmer;

use App\Models\Farm;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $farm_id
 * @property string|null $category_id
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property string $budget_amount
 * @property string $created_by
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['farm_id', 'category_id', 'period_start', 'period_end', 'budget_amount', 'created_by'])]
class FinancialBudget extends FarmerModel
{
    protected $table = 'financial_budgets';

    public $timestamps = true;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'budget_amount' => 'decimal:2',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Farm, $this> */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    /** @return BelongsTo<FinancialCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
