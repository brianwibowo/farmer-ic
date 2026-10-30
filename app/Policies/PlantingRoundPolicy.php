<?php

namespace App\Policies;

use App\Models\FarmBlock;

class PlantingRoundPolicy extends ResourcePolicy
{
    protected string $resource = 'planting_rounds';

    protected ?string $farmPath = 'block.farm';

    protected array $parents = [FarmBlock::class];
}
