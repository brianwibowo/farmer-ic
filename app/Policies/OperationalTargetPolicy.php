<?php

namespace App\Policies;

use App\Models\Farm;

class OperationalTargetPolicy extends ResourcePolicy
{
    protected string $resource = 'operational_targets';

    protected ?string $farmPath = 'farm';

    protected array $parents = [Farm::class];
}
