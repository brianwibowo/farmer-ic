<?php

namespace App\Policies;

use App\Models\Farm;

class FinancialProjectionPolicy extends ResourcePolicy
{
    protected string $resource = 'financial_projections';

    protected ?string $farmPath = 'farm';

    protected array $parents = [Farm::class];
}
