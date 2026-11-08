<?php

namespace App\Policies;

use App\Models\FarmActivity;

class PesticideDetailPolicy extends ResourcePolicy
{
    protected string $resource = 'pesticide_details';

    protected ?string $farmPath = 'activity.farm';

    protected array $parents = [FarmActivity::class];
}
