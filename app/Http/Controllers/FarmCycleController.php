<?php

namespace App\Http\Controllers;

use App\Http\Requests\Farms\ListFarmCyclesRequest;
use App\Models\Farm;
use App\Models\FarmCycle;
use App\Models\Team;
use App\Services\FarmCycleService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FarmCycleController extends Controller
{
    public function index(ListFarmCyclesRequest $request, Farm $farm, FarmCycleService $cycles): Response
    {
        Gate::authorize('view', $farm);
        $team = $farm->team;
        abort_unless($team instanceof Team, 403);
        $owner = $request->routeIs('owner.farm-cycles.index');
        if ($owner) {
            abort_unless((int) $request->user()->current_team_id === (int) $team->id, 404);
        }
        Gate::authorize('viewAny', [FarmCycle::class, $team]);

        return Inertia::render('farms/Cycles', [
            'farm' => $farm->only(['id', 'name']),
            'cycles' => $cycles->paginate($farm, $request->user(), $team, (int) $request->validated('page', 1)),
            'backUrl' => route($owner ? 'owner.farms.show' : 'farm-management.show', $farm, false),
        ]);
    }

    public function petugas(ListFarmCyclesRequest $request, FarmCycleService $cycles): Response
    {
        $farm = $request->user()->assignedFarm;
        abort_unless($farm instanceof Farm, 403, 'Anda belum memiliki penugasan lahan.');
        Gate::authorize('view', $farm);
        $team = $farm->team;
        abort_unless($team instanceof Team, 403);
        Gate::authorize('viewAny', [FarmCycle::class, $team]);

        return Inertia::render('petugas/Cycles', [
            'farm' => $farm->only(['id', 'name']),
            'cycles' => $cycles->paginate($farm, $request->user(), $team, (int) $request->validated('page', 1)),
            'backUrl' => route('petugas.kebun', [], false),
        ]);
    }
}
