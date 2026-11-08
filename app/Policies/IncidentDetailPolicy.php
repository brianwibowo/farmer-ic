<?php

namespace App\Policies;

use App\Models\FarmActivity;

class IncidentDetailPolicy extends ResourcePolicy
{
    protected string $resource = 'incident_details';

    protected ?string $farmPath = 'activity.farm';

    protected array $parents = [FarmActivity::class];
}
