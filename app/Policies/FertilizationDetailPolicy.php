<?php

namespace App\Policies;

use App\Models\FarmActivity;

class FertilizationDetailPolicy extends ResourcePolicy
{
    protected string $resource = 'fertilization_details';

    protected ?string $farmPath = 'activity.farm';

    protected array $parents = [FarmActivity::class];
}
