<?php

namespace App\Policies;

class CommodityPolicy extends ResourcePolicy
{
    protected string $resource = 'commodities';

    protected bool $global = true;
}
