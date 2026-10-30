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

        Gate::define('users.manageAll', fn (User $user): bool => false);

        // Sidebar visibility only; switching teams still requires membership.
        Gate::define('navigation.teamSwitcher', fn (User $user): bool => app(RolePermissions::class)->roleName($user) === 'owner');

        // Owner may list users across their own non-personal teams, not globally.
        Gate::define('users.viewAcrossTeams', function (User $user): bool {
            $permissions = app(RolePermissions::class);
            if ($permissions->roleName($user) !== 'owner' || $permissions->scope($user, 'users.view') !== 'any') {
                return false;
            }

            return $user->teams()->where('teams.is_personal', false)->get()
                ->contains(fn (Team $team) => Gate::forUser($user)->allows('viewAny', [User::class, $team]));
        });

        Gate::define('users.manage', function (User $user): bool {
            if (Gate::forUser($user)->allows('users.viewAcrossTeams')) {
                return true;
            }
            $permissions = app(RolePermissions::class);
            $team = $permissions->singleTenant($user);

            return $permissions->roleName($user) === 'admin'
                && $team !== null
                && Gate::forUser($user)->allows('viewAny', [User::class, $team]);
        });

        // Query scopes use this signal; only the existing Gate::before can grant it.
        Gate::define('access.global', fn (User $user): bool => false);

        Gate::define('admin.access', function (User $user): bool {
            $permissions = app(RolePermissions::class);

            return $permissions->roleName($user) === 'admin'
                && $permissions->singleTenant($user) !== null;
        });

        // Admission to an empty mobile portal, not authority over farm records.
        Gate::define('petugas.access', function (User $user): bool {
            $permissions = app(RolePermissions::class);

            return $permissions->isKnownRole('petugas') && $permissions->roleName($user) === 'petugas';
        });

        Gate::define('ai.access', function (User $user, ?Team $team = null): bool {
            $permissions = app(RolePermissions::class);

            return $team !== null
                && $permissions->scope($user, 'ai.access') === 'any'
                && $permissions->withinTeam($user, $team);
        });
    }
}
