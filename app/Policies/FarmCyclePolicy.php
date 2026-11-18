<?php

namespace App\Policies;

use App\Models\Farm;

class FarmCyclePolicy extends ResourcePolicy
{
    protected string $resource = 'farm_cycles';

    protected ?string $farmPath = 'farm';

    protected array $parents = [Farm::class];
}
