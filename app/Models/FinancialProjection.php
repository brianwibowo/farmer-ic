<?php

namespace App\Models\Farmer;

use App\Models\Farm;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $farm_id
 * @property string|null $planting_cycle_id
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property string|null $projected_income
 * @property string|null $projected_expense
 * @property CarbonImmutable $calculated_at
 */
#[Fillable(['farm_id', 'planting_cycle_id', 'period_start', 'period_end', 'projected_income', 'projected_expense', 'calculated_at'])]
class FinancialProjection extends FarmerModel
{
    protected $table = 'financial_projections';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'projected_income' => 'decimal:2',
            'projected_expense' => 'decimal:2',
            'calculated_at' => 'immutable_datetime',
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
}
