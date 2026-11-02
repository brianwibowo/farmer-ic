<?php

namespace App\Policies;

use App\Models\PlantingCycle;

class PlantingCycleTaskPolicy extends ResourcePolicy
{
    protected string $resource = 'planting_cycle_tasks';

    protected ?string $farmPath = 'plantingCycle.farm';

    protected array $parents = [PlantingCycle::class];
}
