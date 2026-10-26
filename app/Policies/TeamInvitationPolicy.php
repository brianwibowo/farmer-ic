<?php

namespace App\Policies;

use App\Models\Team;

class TeamInvitationPolicy extends ResourcePolicy
{
    protected string $resource = 'team_invitations';

    protected ?string $teamPath = 'team';

    protected array $parents = [Team::class];
}
