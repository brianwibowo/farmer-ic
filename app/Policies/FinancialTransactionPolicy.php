<?php

namespace App\Policies;

use App\Models\Farm;

class FinancialTransactionPolicy extends ResourcePolicy
{
    protected string $resource = 'financial_transactions';

    protected ?string $farmPath = 'farm';

    protected array $parents = [Farm::class];
}
