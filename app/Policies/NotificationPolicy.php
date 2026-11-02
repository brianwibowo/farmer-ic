<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class NotificationPolicy extends ResourcePolicy
{
    protected string $resource = 'notifications';

    protected ?string $userPath = 'user';

    protected bool $personal = true;

    // Presentation rule, not an ability: Superadmin's Gate bypass must not turn on Owner-only copy.
    public function showsTenantContext(User $user): bool
    {
        return $this->permissions->roleName($user) === 'owner' && $this->viewAny($user);
    }

    public function viewAny(User $user, ?Team $team = null): bool
    {
        return $user->is_active && $this->permissions->scope($user, 'notifications.view') !== null
            && ($team !== null ? $this->permissions->withinTeam($user, $team)
                : $this->permissions->teamMemberships($user)->contains(fn (Team $candidate) => $this->permissions->withinTeam($user, $candidate)));
    }

    public function view(User $user, Model $record): bool
    {
        return $record->exists && (string) $record->getAttribute('user_id') === $user->id && $this->viewAny($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->view($user, $record) && $this->permissions->scope($user, 'notifications.update') === 'own';
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function applyVisibility(Builder $query, User $user, ?Team $team = null): void
    {
        $query->where('user_id', $user->id);
        if (! Gate::forUser($user)->allows('viewAny', [\App\Models\Notification::class, $team])) {
            $query->whereRaw('1 = 0');
        }
    }

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
