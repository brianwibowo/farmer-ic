<?php

namespace App\Http\Requests\Finance;

class SaveFinancialBudgetRequest extends FinanceRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            ...$this->serverOwned(),
            'category_id' => ['sometimes', 'nullable', 'uuid'],
            'period_start' => [...$this->presence(), 'date_format:Y-m-d'],
            'period_end' => [...$this->presence(), 'date_format:Y-m-d'],
            'budget_amount' => [...$this->presence(), ...$this->money()],
        ];
    }
}
