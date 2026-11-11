<?php

namespace App\Policies;

use App\Models\FarmActivity;

class LandCleaningDetailPolicy extends ResourcePolicy
{
    protected string $resource = 'land_cleaning_details';

    protected ?string $farmPath = 'activity.farm';

    protected array $parents = [FarmActivity::class];
}
