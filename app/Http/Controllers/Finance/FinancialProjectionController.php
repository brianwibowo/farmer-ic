<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListFinanceRequest;
use App\Http\Requests\Finance\SaveFinancialProjectionRequest;
use App\Http\Resources\Finance\FinancialProjectionResource;
use App\Models\Farm;
use App\Models\FinancialProjection;
use App\Models\Team;
use App\Services\Finance\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class FinancialProjectionController extends Controller
{
    public function __construct(private readonly FinanceService $finance) {}

    public function index(ListFinanceRequest $request, Team $current_team): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [FinancialProjection::class, $current_team]);

        return FinancialProjectionResource::collection($this->finance->paginateProjections($request->user(), $current_team, $request->validated()));
    }

    public function store(SaveFinancialProjectionRequest $request, Team $current_team, Farm $farm): FinancialProjectionResource
    {
        abort_unless($farm->team_id === $current_team->id, 403);
        Gate::authorize('create', [FinancialProjection::class, $farm]);

        return new FinancialProjectionResource($this->finance->saveProjection($request->user(), $current_team, $farm, $request->validated()));
    }

    public function show(Request $request, Team $current_team, FinancialProjection $projection): FinancialProjectionResource
    {
        abort_unless($projection->farm?->team_id === $current_team->id, 403);
        Gate::authorize('view', $projection);

        return (new FinancialProjectionResource($this->finance->projection($request->user(), $current_team, $projection)))
            ->additional(['can' => [
                'update' => Gate::allows('update', $projection),
                'delete' => Gate::allows('delete', $projection),
            ]]);
    }

    public function update(SaveFinancialProjectionRequest $request, Team $current_team, FinancialProjection $projection): FinancialProjectionResource
    {
        abort_unless($projection->farm?->team_id === $current_team->id, 403);
        Gate::authorize('update', $projection);

        return new FinancialProjectionResource($this->finance->saveProjection($request->user(), $current_team, $projection->farm, $request->validated(), $projection));
    }

    public function destroy(Request $request, Team $current_team, FinancialProjection $projection): Response
    {
        abort_unless($projection->farm?->team_id === $current_team->id, 403);
        Gate::authorize('delete', $projection);

        $this->finance->deleteProjection($request->user(), $current_team, $projection);

        return response()->noContent();
    }
}
