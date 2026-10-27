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

        Gate::define('ai.access', function (User $user, ?Team $team = null): bool {
            $permissions = app(RolePermissions::class);

            return $team !== null
                && $permissions->scope($user, 'ai.access') === 'any'
                && $permissions->withinTeam($user, $team);
        });
    }
}
