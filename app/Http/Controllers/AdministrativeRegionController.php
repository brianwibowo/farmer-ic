<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdministrativeRegionResource;
use App\Models\AdministrativeRegion;
use App\Models\Team;
use App\Services\AdministrativeRegionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AdministrativeRegionController extends Controller
{
    public function __invoke(Request $request, AdministrativeRegionService $regions): AnonymousResourceCollection
    {
        $team = $request->user()->currentTeam;
        Gate::authorize('viewAny', [AdministrativeRegion::class, $team]);
        abort_unless($team instanceof Team, 403);
        if (is_string($request->input('search'))) {
            $request->merge(['search' => trim($request->input('search'))]);
        }
        $data = $request->validate(['search' => ['required', 'string', 'min:2', 'max:100']]);

        return AdministrativeRegionResource::collection($regions->search(trim($data['search']), $request->user(), $team));
    }
}
