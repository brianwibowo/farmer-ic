<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Petugas\ShowPetugasGardenRequest;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\Team;
use App\Services\Petugas\PetugasGardenService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PetugasGardenController extends Controller
{
    public function __invoke(ShowPetugasGardenRequest $request, PetugasGardenService $gardens): Response
    {
        $actor = $request->user();
        $farm = $actor->assignedFarm;
        $team = null;
        $selected = null;
        if ($farm instanceof Farm) {
            Gate::authorize('view', $farm);
            $team = $farm->team;
            abort_unless($team instanceof Team, 403);
            foreach ([FarmBlock::class, PlantingCycle::class, PlantingCycleStage::class] as $model) {
                Gate::authorize('viewAny', [$model, $team]);
            }
            if ($request->validated('block') !== null) {
                $selected = FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)
                    ->whereKey($request->validated('block'))->first();
                abort_unless($selected instanceof FarmBlock, 403);
                Gate::authorize('view', $selected);
            }
        } else {
            abort_if($request->validated('block') !== null, 403);
        }

        return Inertia::render('petugas/Kebun', ['garden' => $gardens->overview($actor, $farm, $team, $selected, (int) $request->validated('page', 1))]);
    }
}
