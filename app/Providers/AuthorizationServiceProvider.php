<?php

namespace App\Providers;

use App\Models\ActivityType;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\Notification;
use App\Models\PlantingCycle;
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
        Gate::before(function (User $user, string $ability): ?bool {
            // Interactive AI remains Owner-only, including developer accounts.
            return $ability !== 'ai.access' && app(RolePermissions::class)->roleName($user) === 'superadmin' ? true : null;
        });

        // Superadmin dont reach this point, so we can safely deny all permissions for other users
        Gate::define('teams.manageAll', fn (User $user): bool => false);

        Gate::define('users.manageAll', fn (User $user): bool => false);

        // Sidebar visibility only; switching teams still requires membership.
        Gate::define('navigation.teamSwitcher', function (User $user): bool {
            $permissions = app(RolePermissions::class);

            return $permissions->roleName($user) === 'owner'
                && $permissions->teamMemberships($user)->where('is_personal', false)->unique('id')->count() > 1;
        });

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

        // Admission to the existing owner dashboard, not authority over farm records.
        Gate::define('owner.access', function (User $user): bool {
            $permissions = app(RolePermissions::class);

            return $permissions->isKnownRole('owner') && $permissions->roleName($user) === 'owner';
        });

        // Admission to an empty mobile portal, not authority over farm records.
        Gate::define('petugas.access', function (User $user): bool {
            $permissions = app(RolePermissions::class);

            return $permissions->isKnownRole('petugas') && $permissions->roleName($user) === 'petugas';
        });

        Gate::define('ai.access', function (User $user, ?Team $team = null): bool {
            $permissions = app(RolePermissions::class);

            return $team !== null
                && $permissions->roleName($user) === 'owner'
                && $permissions->scope($user, 'ai.access') === 'any'
                && $permissions->withinTeam($user, $team);
        });

        Gate::define('notifications.dailyReport', function (User $user, Team $team): bool {
            $permissions = app(RolePermissions::class);

            return $user->is_active && in_array($permissions->roleName($user), ['owner', 'admin'], true)
                && ! $team->is_personal && $permissions->withinTeam($user, $team)
                && Gate::forUser($user)->allows('viewAny', [Notification::class, $team])
                && Gate::forUser($user)->allows('viewAny', [Farm::class, $team])
                && Gate::forUser($user)->allows('viewAny', [FarmActivity::class, $team])
                && Gate::forUser($user)->allows('viewAny', [ActivityType::class, $team])
                && Gate::forUser($user)->allows('viewAny', [User::class, $team]);
        });
        Gate::define('notifications.harvestReminder', function (User $user, Farm $farm): bool {
            $permissions = app(RolePermissions::class);
            $team = $farm->team;

            return $user->is_active && $permissions->roleName($user) === 'petugas'
                && $team instanceof Team && ! $team->is_personal && $permissions->withinTeam($user, $team)
                && Gate::forUser($user)->allows('viewAny', [Notification::class, $team])
                && Gate::forUser($user)->allows('view', $farm)
                && Gate::forUser($user)->allows('viewAny', [PlantingCycle::class, $team]);
        });
    }
}
