<?php

namespace App\Policies;

use App\Models\Team;
use Illuminate\Database\Eloquent\Model;

class NotificationPolicy extends ResourcePolicy
{
    protected string $resource = 'notifications';

    protected ?string $userPath = 'user';

    protected bool $personal = true;

    public function viewAny(\App\Models\User $actor, ?Team $team = null): bool
    {
        // Recipients are Farmer\User records with no persisted link to native users.
        return false;
    }

    public function create(\App\Models\User $actor, ?Model $parent = null): bool
    {
        return false;
    }
}
