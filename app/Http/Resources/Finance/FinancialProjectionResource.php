<?php

namespace App\Http\Resources\Finance;

use App\Models\FinancialProjection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FinancialProjection */
class FinancialProjectionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'farm_id' => $this->farm_id,
            'planting_cycle_id' => $this->planting_cycle_id,
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'projected_income' => $this->projected_income,
            'projected_expense' => $this->projected_expense,
            'calculated_at' => $this->calculated_at->toISOString(),
        ];
    }
}
