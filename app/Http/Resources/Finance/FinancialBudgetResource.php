<?php

namespace App\Http\Resources\Finance;

use App\Models\FinancialBudget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FinancialBudget */
class FinancialBudgetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'farm_id' => $this->farm_id,
            'category_id' => $this->category_id,
            'created_by' => $this->created_by,
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'budget_amount' => $this->budget_amount,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
