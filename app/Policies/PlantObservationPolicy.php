<?php

namespace App\Policies;

use App\Models\PlantingCycle;

class PlantObservationPolicy extends ResourcePolicy
{
    protected string $resource = 'plant_observations';

    protected ?string $farmPath = 'plantingCycle.farm';

    protected array $parents = [PlantingCycle::class];
}
