<?php

namespace App\Http\Requests\Finance;

class SaveFinancialProjectionRequest extends FinanceRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            ...$this->serverOwned(),
            'planting_cycle_id' => ['sometimes', 'nullable', 'uuid'],
            'period_start' => [...$this->presence(), 'date_format:Y-m-d'],
            'period_end' => [...$this->presence(), 'date_format:Y-m-d'],
            'projected_income' => ['sometimes', 'nullable', ...$this->money()],
            'projected_expense' => ['sometimes', 'nullable', ...$this->money()],
        ];
    }
}
