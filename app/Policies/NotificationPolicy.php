<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class NotificationPolicy extends ResourcePolicy
{
    protected string $resource = 'notifications';

    protected ?string $userPath = 'user';

    protected bool $personal = true;

    public function create(\App\Models\User $actor, ?Model $parent = null): bool
    {
        if (! $parent instanceof User || ! $parent->exists || $parent->getKey() === null) {
            return false;
        }

        $team = $actor->teams()
            ->whereHas('members', fn ($members) => $members->whereKey($parent->getKey()))
            ->first();

        return $team instanceof Team
            && ($this->permissions->scope($actor, 'notifications.create') === 'any'
                || ($this->permissions->scope($actor, 'notifications.create') === 'own' && $parent->is($actor)))
            && $this->permissions->withinTeam($actor, $team);
    }
}
