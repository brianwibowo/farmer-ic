<?php

namespace App\Policies;

use App\Models\FarmActivity;

class LimingDetailPolicy extends ResourcePolicy
{
    protected string $resource = 'liming_details';

    protected ?string $farmPath = 'activity.farm';

    protected array $parents = [FarmActivity::class];
}
