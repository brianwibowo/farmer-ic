<?php

namespace App\Policies;

use App\Models\ActivityType;
use App\Models\Farm;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\User;
use App\Support\ActivityDetailMap;
use App\Support\TaskActivityFields;
use Illuminate\Support\Facades\Gate;

class FarmActivityPolicy extends ResourcePolicy
{
    protected string $resource = 'farm_activities';

    protected ?string $farmPath = 'farm';

    protected array $parents = [Farm::class];

    /** Category is a persisted reference; farm is the trusted assigned parent. */
    public function recordVoluntary(User $user, Farm $farm, ActivityType $type): bool
    {
        if (! $this->create($user, $farm) || ! Gate::forUser($user)->allows('view', $type)
            || ! $type->is_active || ! $type->is_selectable || in_array($type->code, ['planting', 'block_mapping'], true)) {
            return false;
        }
        $detail = ActivityDetailMap::table($type->detail_key);
        foreach (array_filter(['daily_activity_logs', 'activity_photos', $detail]) as $prefix) {
            foreach (['view', 'create'] as $ability) {
                $scope = $this->permissions->scope($user, $prefix.'.'.$ability);
                if ($scope === null || ($scope === 'own' && $user->assignedFarmId() !== $farm->id)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** Recording planting also updates its trusted cycle and derived schedule. */
    public function recordPlanting(User $user, PlantingCycle $cycle): bool
    {
        $farm = $cycle->farm;
        if (! Gate::forUser($user)->allows('update', $cycle) || ! $this->create($user, $farm)) {
            return false;
        }
        foreach (['daily_activity_logs', 'activity_photos', 'planting_details'] as $prefix) {
            foreach (['view', 'create'] as $ability) {
                $scope = $this->permissions->scope($user, $prefix.'.'.$ability);
                if ($scope === null || ($scope === 'own' && $user->assignedFarmId() !== $farm->id)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** Composite task-report use case; all writes share this persisted farm boundary. */
    public function recordTask(User $user, PlantingCycleTask $task): bool
    {
        $farm = $task->plantingCycle->farm;
        if (! Gate::forUser($user)->allows('view', $task) || ! $this->create($user, $farm)) {
            return false;
        }
        if (TaskActivityFields::activityCode($task) === null) {
            return false;
        }
        $detail = ActivityDetailMap::table(TaskActivityFields::detailKey($task));
        foreach (array_filter(['daily_activity_logs', 'activity_photos', $detail]) as $prefix) {
            foreach (['view', 'create'] as $ability) {
                $scope = $this->permissions->scope($user, $prefix.'.'.$ability);
                if ($scope === null || ($scope === 'own' && $user->assignedFarmId() !== $farm->id)) {
                    return false;
                }
            }
        }

        return true;
    }
}
