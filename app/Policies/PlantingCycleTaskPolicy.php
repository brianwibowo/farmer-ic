<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class PlantingCycleTaskPolicy extends ResourcePolicy
{
    protected string $resource = 'planting_cycle_tasks';

    protected ?string $farmPath = 'plantingCycle.farm';

    protected array $parents = [PlantingCycle::class];

    /** Harvest scheduling also maintains the persisted parent-cycle estimate. */
    public function update(User $user, Model $record): bool
    {
        if (! parent::update($user, $record)) {
            return false;
        }
        if (! $record instanceof PlantingCycleTask || $record->activity_category !== 'harvest') {
            return true;
        }

        $cycle = $record->plantingCycle;

        return $cycle instanceof PlantingCycle && $cycle->farm instanceof Farm
            && Gate::forUser($user)->allows('view', $cycle->farm)
            && Gate::forUser($user)->allows('update', $cycle);
    }

    /** Derived SOP scheduling, not arbitrary task creation/editing. */
    public function synchronize(User $user, PlantingCycle $cycle): bool
    {
        $farm = $cycle->farm;
        if (! $farm instanceof Farm || ! $farm->team
            || ! Gate::forUser($user)->allows('update', $cycle)
            || ! $this->viewAny($user, $farm->team)) {
            return false;
        }

        $scope = $this->permissions->scope($user, 'planting_cycle_tasks.view');
        $assignedFarmId = $user->assignedFarmId();

        return $scope === 'any' || ($scope === 'own' && $assignedFarmId === (string) $farm->id
            && $this->permissions->withinAssignmentLimit($user, 1));
    }

    /** Harvest is completed through the authorized cycle closure instead. */
    public function complete(User $user, PlantingCycleTask $task): bool
    {
        $farm = $task->plantingCycle?->farm;
        if ($task->activity_category === 'harvest' || ! $farm instanceof Farm || ! $this->view($user, $task)) {
            return false;
        }

        foreach (['farm_activities.view', 'farm_activities.create'] as $permission) {
            $scope = $this->permissions->scope($user, $permission);
            if ($scope === null || ! $this->permissions->withinTeam($user, $farm->team)) {
                return false;
            }
            if ($scope === 'own' && $user->assignedFarmId() !== $farm->id) {
                return false;
            }
        }

        return true;
    }
}
