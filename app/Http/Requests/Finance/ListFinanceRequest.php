<?php

namespace App\Http\Requests\Finance;

use Illuminate\Validation\Rule;

class ListFinanceRequest extends FinanceRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'farm_id' => ['sometimes', 'nullable', 'uuid'],
            'category_id' => ['sometimes', 'nullable', 'uuid'],
            'planting_cycle_id' => ['sometimes', 'nullable', 'uuid'],
            'transaction_type' => ['sometimes', 'nullable', Rule::in(['income', 'expense'])],
            'from_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', Rule::when($this->filled('from_date'), 'after_or_equal:from_date')],
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', Rule::in($this->sortColumns())],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'team_id' => ['prohibited'],
        ];
    }

    /** @return list<string> */
    private function sortColumns(): array
    {
        return match (true) {
            $this->routeIs('finance.categories.*') => ['name', 'transaction_type'],
            $this->routeIs('finance.budgets.*') => ['period_start', 'period_end', 'budget_amount', 'created_at'],
            $this->routeIs('finance.projections.*') => ['period_start', 'period_end', 'projected_income', 'projected_expense', 'calculated_at'],
            default => ['transaction_date', 'amount', 'created_at'],
        };
    }
}
