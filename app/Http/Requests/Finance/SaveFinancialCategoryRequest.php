<?php

namespace App\Http\Requests\Finance;

use Illuminate\Validation\Rule;

class SaveFinancialCategoryRequest extends FinanceRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            ...$this->serverOwned(),
            'name' => [...$this->presence(), 'string', 'max:100'],
            'transaction_type' => [...$this->presence(), Rule::in(['income', 'expense'])],
        ];
    }
}
