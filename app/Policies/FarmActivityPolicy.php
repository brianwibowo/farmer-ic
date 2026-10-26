<?php

namespace App\Policies;

use App\Models\Farm;

class FarmActivityPolicy extends ResourcePolicy
{
    protected string $resource = 'farm_activities';

    protected ?string $farmPath = 'farm';

    protected array $parents = [Farm::class];
}
