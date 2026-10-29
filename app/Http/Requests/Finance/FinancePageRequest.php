<?php

namespace App\Http\Requests\Finance;

use Illuminate\Validation\Rule;

class FinancePageRequest extends ListFinanceRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('farm_id') && $this->filled('kebun') && $this->input('kebun') !== 'all') {
            $this->merge(['farm_id' => $this->input('kebun')]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'year' => ['sometimes', 'integer', 'between:2000,2100'],
            'period' => ['sometimes', Rule::in(['Q1', 'Q2', 'Q3', 'Q4', 'YTD'])],
            'tab' => ['sometimes', Rule::in(['chart', 'transactions'])],
        ];
    }
}
