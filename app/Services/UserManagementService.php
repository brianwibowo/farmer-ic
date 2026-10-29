<?php

namespace App\Services;

use App\Enums\TeamRole;
use App\Models\Farm;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    public function __construct(private RolePermissions $permissions) {}

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(User $actor, array $filters, ?Team $tenant = null): LengthAwarePaginator
    {
        $query = $this->profiles($actor, $tenant);
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(fn ($query) => $query->where('name', 'ilike', $pattern)->orWhere('email', 'ilike', $pattern));
        }
        if (! empty($filters['role'])) {
            $query->whereHas('role', fn ($roles) => $roles->where('name', $filters['role']));
        }
        if (! empty($filters['team_id'])) {
            $query->whereHas('teams', function ($teams) use ($actor, $tenant, $filters): void {
                if ($tenant === null) {
                    $teams->whereIn('teams.id', $this->visibleAcrossTeams(Team::query(), $actor, null)->select('teams.id'));
                }
                $teams->whereKey($filters['team_id']);
            });
        }
        if (! empty($filters['farm_id'])) {
            $query->whereHas('assignedFarm', fn ($farms) => $this->visibleAcrossTeams($farms, $actor, $tenant)->whereKey($filters['farm_id']));
        }

        return $query->orderBy('name')->orderBy('id')->paginate($filters['per_page'] ?? 15)->appends($filters);
    }

    /** @return array<string, int> */
    public function summary(User $actor, ?Team $tenant = null): array
    {
        $query = $this->profiles($actor, $tenant);
        $result = ['total' => (clone $query)->count()];
        foreach (['owner', 'admin', 'petugas'] as $role) {
            $result[$role] = (clone $query)->whereHas('role', fn ($roles) => $roles->where('name', $role))->count();
        }

        return $result;
    }

    /** @return LengthAwarePaginator<int, User> */
    public function deleted(User $actor, ?Team $tenant = null): LengthAwarePaginator
    {
        return $this->profiles($actor, $tenant, true)->orderByDesc('deleted_at')->orderBy('id')->paginate(15);
    }

    public function restore(User $actor, User $target, ?Team $tenant = null): void
    {
        DB::transaction(function () use ($actor, $target, $tenant): void {
            $user = User::onlyTrashed()->visibleTo($actor, $tenant)->lockForUpdate()->findOrFail($target->id);
            \Illuminate\Support\Facades\Gate::forUser($actor)->authorize('restore', $user);
            $user->restore();
        });
    }

    /** @return array<string, mixed> */
    public function options(User $actor, bool $includeFarms = true, ?Team $tenant = null): array
    {
        return [
            'teams' => $tenant === null
                ? $this->visibleAcrossTeams(Team::query(), $actor, null)->where('is_personal', false)->orderBy('name')->get(['id', 'name'])
                : [$tenant->only(['id', 'name'])],
            'farms' => $includeFarms
                ? $this->visibleAcrossTeams(Farm::query(), $actor, $tenant)->whereHas('team', fn ($teams) => $teams->where('is_personal', false))
                    ->orderBy('name')->get(['id', 'name', 'team_id'])
                : [],
        ];
    }

    /** @return Collection<int, Role> */
    public function roles(User $actor, ?Team $tenant = null): Collection
    {
        return $this->visibleAcrossTeams(Role::query(), $actor, $tenant)->orderBy('name')->get(['id', 'name'])
            ->filter(fn (Role $role) => $this->permissions->isKnownRole($role->name)
                && ($this->permissions->roleName($actor) !== 'owner' || $role->name !== 'superadmin'))->values();
    }

    /** @return array<string, mixed> */
    public function detail(User $actor, User $target, ?Team $tenant = null): array
    {
        return $this->serialize($this->profiles($actor, $tenant)->findOrFail($target->id));
    }

    /** @return array<string, mixed> */
    public function serialize(User $user): array
    {
        $teams = $user->teams->where('is_personal', false)->values();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $this->permissions->roleName($user),
            'team_ids' => $teams->pluck('id')->all(),
            'teams' => $teams->map(fn (Team $team): array => $team->only(['id', 'name', 'slug']))->all(),
            'assigned_farm_id' => $user->assignedFarm?->id,
            'assigned_farm' => $user->assignedFarm === null ? null : [
                ...$user->assignedFarm->only(['id', 'name', 'team_id']),
                'team_name' => $user->assignedFarm->team?->name,
            ],
            'is_active' => $user->is_active,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }

    /** @param array<string, mixed> $data Validated profile and assignment values. */
    public function save(User $actor, array $data, ?User $target = null, ?Team $tenant = null): User
    {
        if (! is_string($data['role'] ?? null) || ! $this->permissions->isKnownRole($data['role'])) {
            throw ValidationException::withMessages(['role' => 'Role aplikasi tidak dikenal.']);
        }

        try {
            return DB::transaction(function () use ($actor, $data, $target, $tenant): User {
                // Serialize developer-account changes, including concurrent cross-deletions.
                Role::query()->where('name', 'superadmin')->lockForUpdate()->first();
                $user = $target === null ? new User : User::visibleTo($actor, $tenant)->lockForUpdate()->findOrFail($target->id);
                if ($target !== null && $user->role_id !== $target->role_id) {
                    throw ValidationException::withMessages(['role' => 'Role akun baru saja berubah. Muat ulang halaman sebelum menyimpan.']);
                }
                $previousRole = $user->exists ? $this->permissions->roleName($user) : null;
                $role = Role::query()->where('name', $data['role'])->first();
                if ($role === null) {
                    throw ValidationException::withMessages(['role' => 'Role belum tersedia di database. Jalankan seeder role aplikasi.']);
                }
                if ($actor->is($user) && $data['role'] !== $previousRole) {
                    throw ValidationException::withMessages(['role' => 'Role akun yang sedang digunakan tidak dapat diturunkan.']);
                }
                if ($previousRole === 'superadmin' && $role->name !== 'superadmin') {
                    $this->ensureAnotherDeveloper($user);
                }

                $ids = array_values(array_unique(array_map('intval', $data['team_ids'])));
                if ($tenant !== null && $ids !== [$tenant->id]) {
                    throw ValidationException::withMessages(['team_ids' => 'User harus tetap berada di tenant yang sedang dikelola.']);
                }
                $teams = Team::query()->whereIn('id', $ids)->where('is_personal', false)->orderBy('id')->lockForUpdate()->get();
                if ($teams->count() !== count($ids)) {
                    throw ValidationException::withMessages(['team_ids' => 'Pilih tenant aktif yang masih tersedia.']);
                }
                $user->role()->associate($role);
                $user->setRelation('teams', $teams);
                if (! $this->permissions->withinTeamMembershipLimit($user)) {
                    throw ValidationException::withMessages(['team_ids' => 'Admin dan petugas hanya dapat diasosiasikan dengan satu tenant.']);
                }

                $farmId = $data['assigned_farm_id'] ?? null;
                if ($farmId !== null && $farmId !== '') {
                    $farm = Farm::query()->whereKey($farmId)->lockForUpdate()->first();
                    if ($role->name !== 'petugas') {
                        throw ValidationException::withMessages(['assigned_farm_id' => 'Penugasan farm hanya berlaku untuk petugas.']);
                    }
                    if ($farm === null || $teams->count() !== 1 || $farm->team_id === null
                        || (int) $farm->team_id !== (int) $teams->first()->id
                        || ! $this->permissions->withinAssignmentLimit($user, 1)) {
                        throw ValidationException::withMessages(['assigned_farm_id' => 'Farm harus berada di tenant yang dipilih untuk petugas.']);
                    }
                }

                $existing = $user->exists ? $user->teamMemberships()->get()->keyBy('team_id') : collect();
                $personalIds = $user->exists ? $user->teams()->where('teams.is_personal', true)->pluck('teams.id')->all() : [];
                $membershipIds = array_values(array_unique([...$ids, ...$personalIds]));
                $memberships = [];
                foreach ($membershipIds as $id) {
                    // Membership roles belong to the starter's team lifecycle, not application RBAC.
                    $memberships[$id] = ['role' => $existing->has($id) ? $existing->get($id)->role->value : TeamRole::Member->value];
                }

                $user->fill(['name' => $data['name'], 'email' => $data['email']]);
                if ($user->isDirty('email')) {
                    $user->forceFill(['email_verified_at' => null]);
                }
                if (! empty($data['password'])) {
                    $user->password = $data['password'];
                    $user->remember_token = Str::random(60);
                }
                $currentId = in_array($user->current_team_id, $membershipIds, true)
                    ? $user->current_team_id
                    : ($ids[0] ?? $personalIds[0] ?? null);
                $user->forceFill(['assigned_farm_id' => $farmId ?: null, 'current_team_id' => $currentId]);
                $user->save();
                $user->teams()->sync($memberships);
                if (! empty($data['password']) && $target !== null && ! $actor->is($user)) {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }

                // Re-read persisted relationships so a previous assignment cannot remain cached.
                return $user->refresh()->load(['role', 'teams', 'assignedFarm.team']);
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }

            throw ValidationException::withMessages(['email' => 'Email tersebut sudah digunakan.']);
        }
    }

    public function delete(User $actor, User $target, ?Team $tenant = null): void
    {
        if ($actor->is($target)) {
            throw ValidationException::withMessages(['delete' => 'Akun yang sedang digunakan tidak dapat dihapus.']);
        }

        DB::transaction(function () use ($actor, $target, $tenant): void {
            Role::query()->where('name', 'superadmin')->lockForUpdate()->first();
            $user = User::visibleTo($actor, $tenant)->lockForUpdate()->findOrFail($target->id);
            if ($this->permissions->roleName($user) === 'superadmin') {
                $this->ensureAnotherDeveloper($user);
            }
            $user->forceFill(['remember_token' => Str::random(60)])->save();
            $user->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }

    private function ensureAnotherDeveloper(User $target): void
    {
        if (! User::query()->whereKeyNot($target->id)->whereHas('role', fn ($roles) => $roles->where('name', 'superadmin'))->exists()) {
            throw ValidationException::withMessages(['role' => 'Minimal satu akun superadmin harus tetap tersedia.']);
        }
    }

    /** @return Builder<User> */
    private function profiles(User $actor, ?Team $tenant, bool $deleted = false): Builder
    {
        $query = $deleted ? User::onlyTrashed()->visibleTo($actor, $tenant) : $this->visibleAcrossTeams(User::query(), $actor, $tenant);

        return $query->with([
            'role',
            'teams' => function ($teams) use ($actor, $tenant): void {
                if ($tenant !== null) {
                    $teams->whereKey($tenant->id);
                } else {
                    $teams->whereIn('teams.id', $this->visibleAcrossTeams(Team::query(), $actor, null)->select('teams.id'));
                }
            },
            'assignedFarm' => fn ($farms) => $farms->whereIn('farms.id', $this->visibleAcrossTeams(Farm::query(), $actor, $tenant)->select('farms.id'))->with('team'),
        ]);
    }

    /**
     * Combine existing policy-scoped queries for each persisted tenant.
     * The null-team query remains empty for ordinary roles and is global only
     * for the existing superadmin Gate; no permission is granted here.
     *
     * @template TModel of User|Team|Farm|Role
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function visibleAcrossTeams(Builder $query, User $actor, ?Team $tenant): Builder
    {
        if ($tenant !== null) {
            return $query->visibleTo($actor, $tenant);
        }

        $model = $query->getModel();
        $key = $model->getQualifiedKeyName();
        $teams = $actor->teams()->where('teams.is_personal', false)->get();

        return $query->where(function (Builder $scoped) use ($actor, $model, $key, $teams): void {
            $scoped->whereIn($key, $model->newQuery()->visibleTo($actor)->select($key));
            foreach ($teams as $team) {
                $scoped->orWhereIn($key, $model->newQuery()->visibleTo($actor, $team)->select($key));
            }
        });
    }

    /** @param array<string, mixed> $data */
    public function assignmentsChanged(User $target, array $data): bool
    {
        $current = $target->teams()->where('teams.is_personal', false)->pluck('teams.id')->sort()->values()->all();
        $desired = array_map('intval', $data['team_ids']);
        sort($desired);

        return $this->permissions->roleName($target) !== $data['role']
            || $current !== $desired
            || $target->assignedFarmId() !== (($data['assigned_farm_id'] ?? null) ?: null);
    }
}
