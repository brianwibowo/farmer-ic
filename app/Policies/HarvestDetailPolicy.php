<?php

namespace App\Policies;

use App\Models\FarmActivity;

class HarvestDetailPolicy extends ResourcePolicy
{
    protected string $resource = 'harvest_details';

    protected ?string $farmPath = 'activity.farm';

    protected array $parents = [FarmActivity::class];
}
