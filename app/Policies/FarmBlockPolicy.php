<?php

namespace App\Policies;

use App\Models\Farm;

class FarmBlockPolicy extends ResourcePolicy
{
    protected string $resource = 'farm_blocks';

    protected ?string $farmPath = 'farm';

    protected array $parents = [Farm::class];
}
