<?php

namespace App\Http\Controllers;

use App\Http\Requests\Farms\GeocodeFarmRequest;
use App\Http\Resources\FarmGeocodingResource;
use App\Models\Farm;
use App\Models\Team;
use App\Services\FarmGeocodingService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class FarmGeocodingController extends Controller
{
    public function __invoke(GeocodeFarmRequest $request, FarmGeocodingService $geocoding, ?Farm $farm = null): AnonymousResourceCollection
    {
        $team = $farm ? $farm->team : $request->user()->currentTeam;
        if ($farm) {
            Gate::authorize('update', $farm);
        } else {
            Gate::authorize('create', [Farm::class, $team]);
        }
        abort_unless($team instanceof Team, 403);

        return FarmGeocodingResource::collection($geocoding->search(
            $request->validated('address'), $request->validated('district_code'), $request->user(), $team,
        ));
    }
}
