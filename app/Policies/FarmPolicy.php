<?php

namespace App\Policies;

use App\Models\Team;

class FarmPolicy extends ResourcePolicy
{
    protected string $resource = 'farms';

    protected ?string $farmPath = '';

    protected array $parents = [Team::class];
}
