<?php

namespace App\Policies;

use App\Models\PlantingCycle;

class FarmRiskPolicy extends ResourcePolicy
{
    protected string $resource = 'farm_risks';

    protected ?string $farmPath = 'plantingCycle.farm';

    protected array $parents = [PlantingCycle::class];
}
