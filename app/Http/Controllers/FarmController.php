<?php

namespace App\Http\Controllers;

use App\Http\Requests\Farms\ListFarmsRequest;
use App\Http\Requests\Farms\SaveFarmRequest;
use App\Http\Resources\FarmResource;
use App\Models\Farm;
use App\Services\FarmService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FarmController extends Controller
{
    public function __construct(private readonly FarmService $farms) {}

    public function index(ListFarmsRequest $request): AnonymousResourceCollection
    {
        return FarmResource::collection($this->farms->paginate($request->validated()));
    }

    public function store(SaveFarmRequest $request): FarmResource
    {
        $data = $request->validated();
        $createdBy = $data['created_by'];
        unset($data['created_by']);

        return new FarmResource($this->farms->create($data, $createdBy));
    }

    public function show(string $farm): FarmResource
    {
        return new FarmResource($this->farms->find($farm));
    }

    public function update(SaveFarmRequest $request, Farm $farm): FarmResource
    {
        return new FarmResource($this->farms->update($farm, $request->validated()));
    }

    public function destroy(Farm $farm): Response
    {
        $this->farms->delete($farm);

        return response()->noContent();
    }
}
