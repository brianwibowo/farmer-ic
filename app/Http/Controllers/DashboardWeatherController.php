<?php

namespace App\Http\Controllers;

use App\Http\Resources\FarmWeatherResource;
use App\Models\Farm;
use App\Models\Team;
use App\Services\FarmWeatherService;
use App\Support\RolePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardWeatherController extends Controller
{
    public function __invoke(Request $request, Farm $farm, FarmWeatherService $weather): FarmWeatherResource
    {
        $actor = $request->user();
        $team = Gate::allows('access.global')
            ? $actor->currentTeam
            : (app(RolePermissions::class)->singleTenant($actor) ?? $actor->currentTeam);

        abort_unless($team instanceof Team, 403);
        Gate::authorize('viewAny', [Farm::class, $team]);
        Gate::authorize('view', $farm);

        return new FarmWeatherResource($weather->forecast($actor, $team, $farm));
    }
}
