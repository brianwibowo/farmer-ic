<?php

namespace App\Policies\Farmer;

use App\Models\Team;
use App\Policies\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends ResourcePolicy
{
    protected string $resource = 'users';

    protected ?string $userPath = '';

    public function viewAny(\App\Models\User $actor, ?Team $team = null): bool
    {
        // A domain-user listing has no provable native-user/team link yet.
        return false;
    }

    public function create(\App\Models\User $actor, ?Model $parent = null): bool
    {
        // Domain user creation is deferred until an identity integration is selected.
        return false;
    }
}
