<?php

namespace App\Policies;

class ActivityTypePolicy extends ResourcePolicy
{
    protected string $resource = 'activity_types';

    protected bool $global = true;
}
