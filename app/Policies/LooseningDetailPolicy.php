<?php

namespace App\Policies;

use App\Models\FarmActivity;

class LooseningDetailPolicy extends ResourcePolicy
{
    protected string $resource = 'loosening_details';

    protected ?string $farmPath = 'activity.farm';

    protected array $parents = [FarmActivity::class];
}
