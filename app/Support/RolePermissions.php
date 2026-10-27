<?php

namespace App\Support;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;

final class RolePermissions
{
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
        return $user->exists && $team->exists
            && $user->getKey() !== null && $team->getKey() !== null
            && ! $team->trashed()
            && $this->withinTeamMembershipLimit($user)
            && $user->belongsToTeam($team);
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

        $count = $user->relationLoaded('teams')
            ? $user->getRelation('teams')->where('is_personal', false)->unique('id')->count()
            : $user->teams()->where('teams.is_personal', false)->distinct('teams.id')->count('teams.id');

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
