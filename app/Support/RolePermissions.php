<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;

final class RolePermissions
{
    public function scope(User $user, string $permission): ?string
    {
        $role = $user->getAttribute('role');
        $roles = config('access.roles', []);

        if (! is_string($role) || $role === '' || ! is_array($roles)) {
            return null;
        }

        $grants = $roles[$role] ?? null;
        $scope = is_array($grants) ? ($grants[$permission] ?? null) : null;

        return in_array($scope, ['own', 'any'], true) ? $scope : null;
    }

    public function withinTeam(User $user, Team $team): bool
    {
        return $user->exists && $team->exists
            && $user->getKey() !== null && $team->getKey() !== null
            && ! $team->trashed()
            && $user->belongsToTeam($team);
    }

    public function withinAssignmentLimit(User $user, int $count): bool
    {
        $limits = config('access.kebun_assignment_limits', []);

        if (! is_array($limits) || ! is_string($user->role)) {
            return false;
        }

        if (! array_key_exists($user->role, $limits)) {
            return true;
        }

        $limit = $limits[$user->role];

        return is_int($limit) && $limit > 0 && $count <= $limit;
    }
}
