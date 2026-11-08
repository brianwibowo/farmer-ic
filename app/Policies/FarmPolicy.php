<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\Team;
use App\Models\User;

class FarmPolicy extends ResourcePolicy
{
    protected string $resource = 'farms';

    protected ?string $farmPath = '';

    protected array $parents = [Team::class];

    public function forceDelete(User $user, Farm $farm): bool
    {
        // Only the existing developer Superadmin Gate::before may grant this.
        return false;
    }
}
