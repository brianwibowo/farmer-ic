<?php

namespace App\Policies;

class RolePolicy extends ResourcePolicy
{
    protected string $resource = 'roles';

    protected bool $global = true;
}
