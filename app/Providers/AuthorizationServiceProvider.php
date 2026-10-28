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
        Gate::before(function (User $user): ?bool {
            return app(RolePermissions::class)->roleName($user) === 'superadmin' ? true : null;
        });

        // Superadmin dont reach this point, so we can safely deny all permissions for other users
        Gate::define('teams.manageAll', fn (User $user): bool => false);

        // Entering the admin portal is separate from viewing a resource as Owner.
        // Gate::define('admin.access', function (User $user): bool {
        //     $permissions = app(RolePermissions::class);
        //     $team = $user->currentTeam;

        //     return $permissions->roleName($user) === 'admin'
        //         && $team instanceof Team
        //         && $permissions->withinTeam($user, $team);
        // });

        Gate::define('ai.access', function (User $user, ?Team $team = null): bool {
            $permissions = app(RolePermissions::class);

            return $team !== null
                && $permissions->scope($user, 'ai.access') === 'any'
                && $permissions->withinTeam($user, $team);
        });
    }
}
