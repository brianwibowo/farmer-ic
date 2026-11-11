<?php

namespace App\Policies;

use App\Models\FarmActivity;

class OtherDetailPolicy extends ResourcePolicy
{
    protected string $resource = 'other_details';

    protected ?string $farmPath = 'activity.farm';

    protected array $parents = [FarmActivity::class];
}
