<?php

namespace App\Policies;

use App\Models\Team;

class MembershipPolicy extends ResourcePolicy
{
    protected string $resource = 'memberships';

    protected ?string $teamPath = 'team';

    protected array $parents = [Team::class];
}
