<?php

namespace App\Policies;

use App\Models\Farm;

class FinancialBudgetPolicy extends ResourcePolicy
{
    protected string $resource = 'financial_budgets';

    protected ?string $farmPath = 'farm';

    protected array $parents = [Farm::class];
}
