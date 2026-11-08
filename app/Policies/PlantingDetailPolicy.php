<?php

namespace App\Policies;

use App\Models\FarmActivity;

class PlantingDetailPolicy extends ResourcePolicy
{
    protected string $resource = 'planting_details';

    protected ?string $farmPath = 'activity.farm';

    protected array $parents = [FarmActivity::class];
}
