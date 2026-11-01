<?php

namespace App\Support;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use LogicException;
use WeakMap;

final class RolePermissions
{
    /** @var WeakMap<User, Collection<int, Team>>|null */
    private static ?WeakMap $membershipSnapshots = null;

    /**
     * Reuse persisted memberships only during one read operation. No access
     * decision survives this callback or a subsequent queue validation.
     * $reload=false is reserved for users freshly eager-loaded by a batch query.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function withMembershipSnapshot(User $user, Closure $callback, bool $reload = true): mixed
    {
        self::$membershipSnapshots ??= new WeakMap;
        if (isset(self::$membershipSnapshots[$user])) {
            return $callback();
        }

        if (! $reload && ! $user->relationLoaded('teams')) {
            throw new LogicException('A fresh eager-loaded teams relation is required.');
        }
        $teams = $reload ? $user->teams()->get() : $user->teams;
        self::$membershipSnapshots[$user] = $teams;
        try {
            return $callback();
        } finally {
            unset(self::$membershipSnapshots[$user]);
        }
    }

    /** @return Collection<int, Team>|null */
    private function membershipSnapshot(User $user): ?Collection
    {
        return self::$membershipSnapshots[$user] ?? null;
    }

    /** @return Collection<int, Team> */
    public function teamMemberships(User $user): Collection
    {
        return $this->membershipSnapshot($user) ?? $user->teams()->get();
    }

    /** @return array<string, mixed> */
    public function roles(): array
    {
        $roles = config('access.roles', []);

        return is_array($roles) ? $roles : [];
    }

    public function isKnownRole(string $role): bool
    {
        return $role === 'superadmin'
            || (isset($this->roles()[$role]) && is_array($this->roles()[$role]));
    }

    public function scope(User $user, string $permission): ?string
    {
        $role = $this->roleName($user);
        $roles = $this->roles();

        if (! is_string($role) || $role === '') {
            return null;
        }

        $grants = $roles[$role] ?? null;
        $scope = is_array($grants) ? ($grants[$permission] ?? null) : null;

        return in_array($scope, ['own', 'any'], true) ? $scope : null;
    }

    public function roleName(User $user): ?string
    {
        $user->loadMissing('role');
        $role = $user->getRelation('role');

        if ($role instanceof Role) {
            return $role->name;
        }

        return null;
    }

    public function withinTeam(User $user, Team $team): bool
    {
        $memberships = $this->membershipSnapshot($user);

        return $user->exists && $team->exists
            && $user->getKey() !== null && $team->getKey() !== null
            && ! $team->trashed()
            && $this->withinTeamMembershipLimit($user)
            && ($memberships !== null
                ? $memberships->contains(fn (Team $candidate): bool => $candidate->is($team))
                : $user->belongsToTeam($team));
    }

    public function singleTenant(User $user): ?Team
    {
        $teams = $this->membershipSnapshot($user)?->where('is_personal', false)->take(2)
            ?? $user->teams()->where('teams.is_personal', false)->limit(2)->get();
        $team = $teams->count() === 1 ? $teams->first() : null;

        return $team instanceof Team && $this->withinTeam($user, $team) ? $team : null;
    }

    public function withinTeamMembershipLimit(User $user): bool
    {
        $role = $this->roleName($user);
        if ($role === null || ! $this->isKnownRole($role)) {
            return false;
        }

        if ($role === 'owner' || $role === 'superadmin') {
            return true;
        }

        $memberships = $this->membershipSnapshot($user);
        $count = $memberships !== null ? $memberships->where('is_personal', false)->unique('id')->count()
            : ($user->relationLoaded('teams')
            ? $user->getRelation('teams')->where('is_personal', false)->unique('id')->count()
            : $user->teams()->where('teams.is_personal', false)->distinct('teams.id')->count('teams.id'));

        return $count <= 1;
    }

    public function withinAssignmentLimit(User $user, int $count): bool
    {
        $limits = config('access.farm_assignment_limits', []);

        if (! is_array($limits)) {
            return false;
        }

        $role = $this->roleName($user);
        if ($role === null || ! $this->isKnownRole($role)) {
            return false;
        }

        if (! array_key_exists($role, $limits)) {
            return true;
        }

        $limit = $limits[$role];

        return is_int($limit) && $limit > 0 && $count <= $limit;
    }
}
