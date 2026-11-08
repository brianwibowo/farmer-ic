<?php

namespace App\Policies;

use App\Models\FarmActivity;

class IrrigationDetailPolicy extends ResourcePolicy
{
    protected string $resource = 'irrigation_details';

    protected ?string $farmPath = 'activity.farm';

    protected array $parents = [FarmActivity::class];
}
