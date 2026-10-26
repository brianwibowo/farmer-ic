<?php

namespace App\Concerns;

use App\Models\Team;
use App\Models\User;
use App\Policies\ResourcePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

trait HasVisibleScope
{
    /** @param Builder<static> $query */
    public function scopeVisibleTo(Builder $query, User $user, Team $team): void
    {
        $policy = Gate::getPolicyFor($this);

        if (! $policy instanceof ResourcePolicy) {
            $query->whereRaw('1 = 0');

            return;
        }

        $policy->applyVisibility($query, $user, $team);
    }
}
