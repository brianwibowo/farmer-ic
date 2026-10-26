<?php

namespace App\Policies;

use App\Models\FarmActivity;

class ActivityPhotoPolicy extends ResourcePolicy
{
    protected string $resource = 'activity_photos';

    protected ?string $farmPath = 'activity.farm';

    protected array $parents = [FarmActivity::class];
}
