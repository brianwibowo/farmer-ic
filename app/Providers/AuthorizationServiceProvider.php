<?php

namespace App\Providers;

use App\Models\Team;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthorizationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Developer-level account: superadmin is intentionally unrestricted.
        // This bypasses resource scopes, team membership, and undefined ability
        // checks. Owner remains governed by the explicit static matrix below.
        Gate::before(function (User $user): ?bool {
            return $user->getAttribute('role') === 'superadmin' ? true : null;
        });

        Gate::define('ai.access', function (User $user, ?Team $team = null): bool {
            $permissions = app(RolePermissions::class);

            return $team !== null
                && $permissions->scope($user, 'ai.access') === 'any'
                && $permissions->withinTeam($user, $team);
        });
    }
}
