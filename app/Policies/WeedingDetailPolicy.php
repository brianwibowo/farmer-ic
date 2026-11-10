<?php

namespace App\Policies;

use App\Models\FarmActivity;

class WeedingDetailPolicy extends ResourcePolicy
{
    protected string $resource = 'weeding_details';

    protected ?string $farmPath = 'activity.farm';

    protected array $parents = [FarmActivity::class];
}
