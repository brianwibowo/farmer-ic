<?php

namespace App\Policies;

use App\Models\Farm;

class FarmLegalityPolicy extends ResourcePolicy
{
    protected string $resource = 'farm_legalities';

    protected ?string $farmPath = 'farm';

    protected array $parents = [Farm::class];
}
