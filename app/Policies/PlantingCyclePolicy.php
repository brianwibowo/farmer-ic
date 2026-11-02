<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\FarmBlock;

class PlantingCyclePolicy extends ResourcePolicy
{
    protected string $resource = 'planting_cycles';

    protected ?string $farmPath = 'farm';

    protected array $parents = [Farm::class, FarmBlock::class];
}
