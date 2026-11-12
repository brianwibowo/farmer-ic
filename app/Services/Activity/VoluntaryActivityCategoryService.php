<?php

namespace App\Services\Activity;

use App\Models\ActivityType;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class VoluntaryActivityCategoryService
{
    /**
     * @return list<array{id: string, code: string, name: string, parent_id: string|null, group: string|null}>
     */
    public function forFarm(User $actor, Farm $farm): array
    {
        $team = $farm->team;

        $categories = ActivityType::visibleTo($actor, $team)
            ->with(['parent' => fn ($query) => $query->visibleTo($actor, $team)])
            ->where('is_active', true)
            ->where('is_selectable', true)
            ->where('code', '!=', 'block_mapping')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (ActivityType $type) => $type->code === 'planting'
                ? Gate::allows('create', [FarmActivity::class, $farm])
                : Gate::allows('recordVoluntary', [FarmActivity::class, $farm, $type]))
            ->map(fn (ActivityType $type) => [
                ...$type->only(['id', 'code', 'name', 'parent_id']),
                'group' => $type->parent?->name,
            ])
            ->all();

        return array_values($categories);
    }
}
