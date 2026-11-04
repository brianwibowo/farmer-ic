<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PlantingCycleTaskPolicy extends ResourcePolicy
{
    protected string $resource = 'planting_cycle_tasks';

    protected ?string $farmPath = 'plantingCycle.farm';

    protected array $parents = [PlantingCycle::class];

    /** Derived SOP scheduling, not arbitrary task creation/editing. */
    public function synchronize(User $user, PlantingCycle $cycle): bool
    {
        return Gate::forUser($user)->allows('update', $cycle)
            && $this->viewAny($user, $cycle->farm->team);
    }

    /** Direct completion after at least one linked activity report. */
    public function complete(User $user, PlantingCycleTask $task): bool
    {
        $farm = $task->plantingCycle?->farm;
        if (! $farm instanceof Farm || ! $this->view($user, $task)) {
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
