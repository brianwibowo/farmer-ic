<?php

namespace App\Policies;

use App\Contracts\KebunAssignments;
use App\Models\User;
use App\Support\Access\ResourceAccess;
use App\Support\RolePermissions;

final class ResourceAccessPolicy
{
    public function __construct(
        private RolePermissions $permissions,
        private KebunAssignments $assignments,
    ) {}

    /** Listing admission only: future queries must still filter their rows. */
    public function viewAny(User $user, ResourceAccess $resource): bool
    {
        return $this->permissions->scope($user, $resource->resource.'.view') !== null
            && $this->permissions->withinTeam($user, $resource->team);
    }

    public function view(User $user, ResourceAccess $resource): bool
    {
        return $this->allows($user, $resource, 'view');
    }

    public function create(User $user, ResourceAccess $resource): bool
    {
        // An own-scoped creation needs the persisted parent/target kebun.
        return $this->allows($user, $resource, 'create');
    }

    public function update(User $user, ResourceAccess $resource): bool
    {
        return $this->view($user, $resource) && $this->allows($user, $resource, 'update');
    }

    public function delete(User $user, ResourceAccess $resource): bool
    {
        return $this->view($user, $resource) && $this->allows($user, $resource, 'delete');
    }

    public function configure(User $user, ResourceAccess $resource): bool
    {
        return $this->view($user, $resource) && $this->allows($user, $resource, 'configure');
    }

    private function allows(User $user, ResourceAccess $resource, string $action): bool
    {
        $scope = $this->permissions->scope($user, $resource->resource.'.'.$action);

        if ($scope === null || ! $this->permissions->withinTeam($user, $resource->team)) {
            return false;
        }

        if ($scope === 'any') {
            return true;
        }

        if ($resource->kebunId === null || $resource->kebunId === '') {
            return false;
        }

        $ids = array_values(array_unique($this->assignments->idsFor($user)));

        return $this->permissions->withinAssignmentLimit($user, count($ids))
            && in_array($resource->kebunId, $ids, true);
    }
}
