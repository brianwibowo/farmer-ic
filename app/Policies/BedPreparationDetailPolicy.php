<?php

namespace App\Policies;

use App\Models\FarmActivity;

class BedPreparationDetailPolicy extends ResourcePolicy
{
    protected string $resource = 'bed_preparation_details';

    protected ?string $farmPath = 'activity.farm';

    protected array $parents = [FarmActivity::class];
}
