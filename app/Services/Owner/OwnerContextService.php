<?php

namespace App\Services\Owner;

use App\Models\Team;
use App\Models\User;

class OwnerContextService
{
    public function selectTeam(User $actor, Team $team): void
    {
        $actor->switchTeam($team);
    }
}
