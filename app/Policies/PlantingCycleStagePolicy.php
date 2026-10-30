<?php

namespace App\Policies;

use App\Models\PlantingCycle;

class PlantingCycleStagePolicy extends ResourcePolicy
{
    protected string $resource = 'planting_cycle_stages';

    protected ?string $farmPath = 'plantingCycle.farm';

    protected array $parents = [PlantingCycle::class];
}
