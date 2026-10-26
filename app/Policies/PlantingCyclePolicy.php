<?php

namespace App\Policies;

use App\Models\Farm;

class PlantingCyclePolicy extends ResourcePolicy
{
    protected string $resource = 'planting_cycles';

    protected ?string $farmPath = 'farm';

    protected array $parents = [Farm::class];
}
