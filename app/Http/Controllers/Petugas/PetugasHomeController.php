<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\IncidentDetail;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Services\Petugas\PetugasHomeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PetugasHomeController extends Controller
{
    public function __invoke(Request $request, PetugasHomeService $home): Response
    {
        $farm = $request->user()->assignedFarm;
        $team = null;
        if ($farm instanceof Farm) {
            Gate::authorize('view', $farm);
            $team = $farm->team;
            abort_unless($team instanceof Team, 403);
            Gate::authorize('viewAny', [PlantingCycle::class, $team]);
            Gate::authorize('viewAny', [PlantingCycleTask::class, $team]);
            Gate::authorize('viewAny', [FarmActivity::class, $team]);
            Gate::authorize('viewAny', [IncidentDetail::class, $team]);
        }

        return Inertia::render('petugas/Home', [
            'home' => $home->overview($request->user(), $farm, $team),
        ]);
    }
}
