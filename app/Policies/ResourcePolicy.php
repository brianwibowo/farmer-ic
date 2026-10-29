<?php

namespace App\Policies;

use App\Models\AiConversation;
use App\Models\AiKnowledgeDocument;
use App\Models\AiMessage;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Support\RolePermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

abstract class ResourcePolicy
{
    protected string $resource;

    protected ?string $farmPath = null;

    protected ?string $teamPath = null;

    protected ?string $userPath = null;

    protected bool $global = false;

    protected bool $personal = false;

    protected bool $ai = false;

    /** @var list<class-string<Model>> */
    protected array $parents = [Team::class];

    public function __construct(protected RolePermissions $permissions) {}

    public function viewAny(\App\Models\User $user, ?Team $team = null): bool
    {
        return $team !== null && $this->admitted($user, $team, 'view');
    }

    public function view(\App\Models\User $user, Model $record): bool
    {
        return $this->allowsRecord($user, $record, 'view');
    }

    public function create(\App\Models\User $user, ?Model $parent = null): bool
    {
        if ($parent === null || ! $parent->exists || $parent->getKey() === null) {
            return false;
        }

        $accepted = false;
        foreach ($this->parents as $class) {
            $accepted = $accepted || $parent instanceof $class;
        }

        if (! $accepted) {
            return false;
        }

        $farm = match (true) {
            $parent instanceof Farm => $parent,
            $parent instanceof FarmActivity, $parent instanceof PlantingCycle => $parent->farm,
            default => null,
        };

        $team = match (true) {
            $parent instanceof Team => $parent,
            $farm instanceof Farm => $farm->team,
            $parent instanceof AiConversation, $parent instanceof AiKnowledgeDocument => $parent->team,
            $parent instanceof AiMessage => $parent->conversation->team,
            $this->global => $user->currentTeam,
            default => null,
        };

        if (! $team instanceof Team || ! $this->admitted($user, $team, 'create')) {
            return false;
        }

        return $this->permissions->scope($user, $this->resource.'.create') === 'any'
            || ($farm instanceof Farm && $this->assigned($user, $farm));
    }

    public function update(\App\Models\User $user, Model $record): bool
    {
        return $this->view($user, $record) && $this->allowsRecord($user, $record, 'update');
    }

    public function delete(\App\Models\User $user, Model $record): bool
    {
        return $this->view($user, $record) && $this->allowsRecord($user, $record, 'delete');
    }

    public function configure(\App\Models\User $user, Model $record): bool
    {
        return $this->view($user, $record) && $this->allowsRecord($user, $record, 'configure');
    }

    protected function admitted(\App\Models\User $user, Team $team, string $action): bool
    {
        return $this->permissions->scope($user, $this->resource.'.'.$action) !== null
            && (! $this->ai || $this->permissions->scope($user, 'ai.access') === 'any')
            && $this->permissions->withinTeam($user, $team);
    }

    protected function allowsRecord(\App\Models\User $user, Model $record, string $action): bool
    {
        if (! $record->exists || $record->getKey() === null) {
            return false;
        }

        $farm = $this->farmPath === '' ? $record : ($this->farmPath === null ? null : data_get($record, $this->farmPath));
        $team = $this->teamFor($user, $record, $farm instanceof Farm ? $farm : null);

        if ($team === null || ! $this->admitted($user, $team, $action)) {
            return false;
        }

        if ($this->permissions->scope($user, $this->resource.'.'.$action) === 'any') {
            return true;
        }

        if ($this->personal) {
            return (string) $record->getAttribute('user_id') === (string) $user->getKey();
        }

        return $farm instanceof Farm && $this->assigned($user, $farm);
    }

    private function teamFor(\App\Models\User $user, Model $record, ?Farm $farm): ?Team
    {
        if ($farm !== null) {
            return $farm->team;
        }

        if ($this->teamPath === '') {
            return $record instanceof Team ? $record : null;
        }

        if ($this->teamPath !== null) {
            $team = data_get($record, $this->teamPath);

            return $team instanceof Team ? $team : null;
        }

        if ($this->userPath !== null) {
            $target = $this->userPath === '' ? $record : data_get($record, $this->userPath);
            if (! $target instanceof \App\Models\User || ! $target->exists) {
                return null;
            }

            return $this->teamForUserTarget($user, $target);
        }

        // Shared reference/system records require real membership, not an active-team pointer.
        return $this->global ? $user->teams()->first() : null;
    }

    private function teamForUserTarget(
        \App\Models\User $actor,
        \App\Models\User $target,
    ): ?Team {
        return $actor->teams()->whereHas('members', fn ($members) => $members->whereKey($target->getKey()))->first();
    }

    private function assigned(\App\Models\User $user, Farm $farm): bool
    {
        $assignedFarmId = $user->assignedFarmId();

        return $farm->exists && $farm->getKey() !== null
            && $this->permissions->withinAssignmentLimit($user, $assignedFarmId === null ? 0 : 1)
            && $assignedFarmId === (string) $farm->getKey();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function applyVisibility(Builder $query, \App\Models\User $user, ?Team $team = null): void
    {
        if (Gate::forUser($user)->allows('access.global')) {
            return;
        }

        if ($team === null || ! $this->admitted($user, $team, 'view')) {
            $query->whereRaw('1 = 0');

            return;
        }

        $own = $this->permissions->scope($user, $this->resource.'.view') === 'own';
        $assignedFarmId = $own && ! $this->personal ? $user->assignedFarmId() : null;

        if ($own && ! $this->personal && ($this->farmPath === null
            || ! $this->permissions->withinAssignmentLimit($user, $assignedFarmId === null ? 0 : 1)
            || $assignedFarmId === null)) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($this->farmPath !== null) {
            $filter = function ($farms) use ($team, $own, $assignedFarmId): void {
                $farms->whereHas('team', fn ($teams) => $teams->whereKey($team->getKey()));
                if ($own) {
                    $farms->whereKey($assignedFarmId);
                }
            };

            if ($this->farmPath === '') {
                $filter($query);
            } else {
                $query->whereHas($this->farmPath, $filter);
            }
        } elseif ($this->teamPath !== null) {
            if ($this->teamPath === '') {
                $query->whereKey($team->getKey());
            } else {
                $query->whereHas($this->teamPath, fn ($teams) => $teams->whereKey($team->getKey()));
            }
        } elseif ($this->userPath !== null) {
            $target = $query->getModel();
            if ($this->userPath !== '') {
                $relation = $target->{$this->userPath}();
                $target = $relation instanceof \Illuminate\Database\Eloquent\Relations\Relation ? $relation->getRelated() : null;
            }
            if (! $target instanceof \App\Models\User) {
                $query->whereRaw('1 = 0');

                return;
            }
            $path = $this->userPath === '' ? 'teams' : $this->userPath.'.teams';
            $query->whereHas($path, fn ($teams) => $teams->whereKey($team->getKey()));
        } elseif (! $this->global) {
            $query->whereRaw('1 = 0');
        }

        if ($own && $this->personal) {
            $query->where($query->qualifyColumn('user_id'), $user->getKey());
        }
    }
}
