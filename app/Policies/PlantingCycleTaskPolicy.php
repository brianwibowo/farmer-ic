<?php

namespace App\Policies;

use App\Models\PlantingCycle;
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
}
