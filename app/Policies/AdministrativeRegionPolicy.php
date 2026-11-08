<?php

namespace App\Policies;

class AdministrativeRegionPolicy extends ResourcePolicy
{
    protected string $resource = 'administrative_regions';

    protected bool $global = true;
}
