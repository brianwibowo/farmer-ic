<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class UserPolicy extends ResourcePolicy
{
    protected string $resource = 'users';

    protected ?string $userPath = '';

    protected array $parents = [Team::class];

    public function viewAny(\App\Models\User $user, ?Team $team = null, ?Role $role = null): bool
    {
        if ($team === null) {
            return Gate::forUser($user)->allows('users.viewAcrossTeams');
        }

        return $this->validTenant($user, $team)
            && $this->allowedRole($user, $role)
            && parent::viewAny($user, $team);
    }

    public function viewDeletedAny(\App\Models\User $user, ?Team $team = null): bool
    {
        return $this->permissions->roleName($user) === 'admin'
            && $team !== null && $this->validTenant($user, $team)
            && $this->permissions->scope($user, 'users.view') === 'any'
            && $this->permissions->withinTeam($user, $team)
            && $this->permissions->scope($user, 'users.restore') === 'any';
    }

    public function restore(\App\Models\User $user, \App\Models\User $record): bool
    {
        $team = $this->permissions->singleTenant($user);
        $role = $record->role()->first();

        return $this->permissions->roleName($user) === 'admin'
            && $record->trashed() && $team !== null && $role !== null
            && $this->validTenant($user, $team) && $this->allowedRole($user, $role)
            && $record->teams()->where('teams.is_personal', false)->count() === 1
            && $record->belongsToTeam($team)
            && $this->permissions->scope($user, 'users.view') === 'any'
            && $this->permissions->withinTeam($user, $team)
            && $this->permissions->scope($user, 'users.restore') === 'any';
    }

    public function view(\App\Models\User $user, Model $record, bool $management = false): bool
    {
        if ($this->permissions->roleName($user) === 'owner' && $record instanceof \App\Models\User
            && $this->permissions->roleName($record) === 'superadmin') {
            return false;
        }
        // User Management only includes shared non-personal tenants.
        if ($management && $this->permissions->roleName($user) === 'owner'
            && (! $record instanceof \App\Models\User
                || ! $record->teams()->where('teams.is_personal', false)
                    ->whereHas('members', fn ($members) => $members->whereKey($user->getKey()))->exists())) {
            return false;
        }

        if ($this->permissions->roleName($user) === 'admin') {
            $team = $this->permissions->singleTenant($user);
            $role = $record instanceof \App\Models\User ? $record->role()->first() : null;
            if (! $record instanceof \App\Models\User || $team === null
                || $role === null || ! $this->allowedRole($user, $role)
                || $record->teams()->where('teams.is_personal', false)->count() !== 1
                || ! $record->belongsToTeam($team)) {
                return false;
            }
        }

        return parent::view($user, $record);
    }

    /** @param Collection<int, Team>|null $teams */
    public function create(\App\Models\User $user, ?Model $parent = null, ?Role $role = null, ?Collection $teams = null, ?Farm $farm = null): bool
    {
        return $this->allowedRole($user, $role)
            && $this->validTenant($user, $parent instanceof Team ? $parent : null)
            && $this->validAssignments($user, $role, $teams, $farm)
            && parent::create($user, $parent);
    }

    public function update(\App\Models\User $user, Model $record, ?Role $role = null): bool
    {
        return $this->unchangedRole($user, $record, $role) && parent::update($user, $record);
    }

    /** @param Collection<int, Team>|null $teams */
    public function configure(\App\Models\User $user, Model $record, ?Role $role = null, ?Collection $teams = null, ?Farm $farm = null): bool
    {
        return $this->unchangedRole($user, $record, $role)
            && $this->validAssignments($user, $role, $teams, $farm)
            && parent::configure($user, $record);
    }

    /** @template TModel of Model
     * @param  Builder<TModel>  $query
     */
    public function applyVisibility(Builder $query, \App\Models\User $user, ?Team $team = null): void
    {
        parent::applyVisibility($query, $user, $team);
        if ($this->permissions->roleName($user) === 'owner') {
            $query->whereDoesntHave('role', fn ($roles) => $roles->where('name', 'superadmin'));
        }
        if ($this->permissions->roleName($user) !== 'admin') {
            return;
        }
        if (! $this->validTenant($user, $team)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('role', fn ($roles) => $roles->whereIn('name', ['admin', 'petugas']))
            ->whereHas('teams', fn ($teams) => $teams->where('teams.is_personal', false), '=', 1);
    }

    private function allowedRole(\App\Models\User $user, ?Role $role): bool
    {
        return $this->permissions->roleName($user) !== 'admin'
            || $role === null
            || in_array($role->name, ['admin', 'petugas'], true);
    }

    private function unchangedRole(\App\Models\User $user, Model $record, ?Role $role): bool
    {
        return $this->permissions->roleName($user) !== 'admin'
            || $role === null
            || ($record instanceof \App\Models\User && $record->role_id === $role->id);
    }

    private function validTenant(\App\Models\User $user, ?Team $team): bool
    {
        if ($this->permissions->roleName($user) !== 'admin') {
            return true;
        }
        $tenant = $this->permissions->singleTenant($user);

        return $tenant !== null && $team !== null && $tenant->is($team);
    }

    /** @param Collection<int, Team>|null $teams */
    private function validAssignments(\App\Models\User $user, ?Role $role, ?Collection $teams, ?Farm $farm): bool
    {
        if ($this->permissions->roleName($user) !== 'admin') {
            return true;
        }
        $tenant = $this->permissions->singleTenant($user);

        return $tenant !== null
            && ($teams === null || ($teams->count() === 1 && $teams->first()?->is($tenant)))
            && ($farm === null || ($role?->name === 'petugas' && (int) $farm->team_id === $tenant->id
                && Gate::forUser($user)->allows('view', $farm)));
    }
}
