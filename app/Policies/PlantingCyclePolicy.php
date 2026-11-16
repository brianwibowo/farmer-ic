<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PlantingCyclePolicy extends ResourcePolicy
{
    protected string $resource = 'planting_cycles';

    protected ?string $farmPath = 'farm';

    protected array $parents = [Farm::class, FarmBlock::class];

    /** Harvest closure also completes visible tasks within the same farm. */
    public function configure(User $user, Model $record): bool
    {
        if (! $record instanceof PlantingCycle || ! parent::configure($user, $record) || ! $record->farm instanceof Farm) {
            return false;
        }

        foreach (['planting_cycle_tasks.view', 'planting_cycle_tasks.update'] as $permission) {
            $scope = $this->permissions->scope($user, $permission);
            if ($scope === null || ($scope === 'own' && $user->assignedFarmId() !== $record->farm->id)) {
                return false;
            }
        }

        return true;
    }
}
