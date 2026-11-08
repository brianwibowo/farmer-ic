<?php

namespace App\Http\Requests\Finance;

use Illuminate\Validation\Rule;

class SaveFinancialTransactionRequest extends FinanceRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            ...$this->serverOwned(),
            'category_id' => [...$this->presence(), 'uuid'],
            'planting_cycle_id' => ['sometimes', 'nullable', 'uuid'],
            'transaction_date' => [...$this->presence(), 'date_format:Y-m-d'],
            'transaction_type' => [...$this->presence(), Rule::in(['income', 'expense'])],
            'amount' => [...$this->presence(), ...$this->money(true)],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
