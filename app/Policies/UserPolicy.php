<?php

namespace App\Policies;

use App\Models\Team;

class UserPolicy extends ResourcePolicy
{
    protected string $resource = 'users';

    protected ?string $userPath = '';

    protected array $parents = [Team::class];
}
