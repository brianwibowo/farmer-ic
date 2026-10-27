<?php

namespace App\Http\Controllers;

use App\Http\Requests\Farms\ListFarmsRequest;
use App\Http\Requests\Farms\SaveFarmRequest;
use App\Http\Resources\FarmResource;
use App\Models\Farm;
use App\Services\FarmService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class FarmController extends Controller
{
    public function __construct(private readonly FarmService $farms) {}

    public function index(ListFarmsRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $team = $user->currentTeam;
        Gate::forUser($user)->authorize('viewAny', [Farm::class, $team]);

        return FarmResource::collection($this->farms->paginate($user, $team, $request->validated()));
    }

    public function store(SaveFarmRequest $request): FarmResource
    {
        $user = $request->user();
        $team = $user->currentTeam;
        Gate::forUser($user)->authorize('create', [Farm::class, $team]);

        return new FarmResource($this->farms->create($request->validated(), $user, $team));
    }

    public function show(Farm $farm): FarmResource
    {
        Gate::authorize('view', $farm);

        return new FarmResource($farm);
    }

    public function update(SaveFarmRequest $request, Farm $farm): FarmResource
    {
        Gate::authorize('update', $farm);

        return new FarmResource($this->farms->update($farm, $request->validated()));
    }

    public function destroy(Farm $farm): Response
    {
        Gate::authorize('delete', $farm);

        $this->farms->delete($farm);

        return response()->noContent();
    }
}
