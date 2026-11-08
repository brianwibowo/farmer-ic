<?php

namespace App\Policies;

class FinancialCategoryPolicy extends ResourcePolicy
{
    protected string $resource = 'financial_categories';

    protected bool $global = true;
}
