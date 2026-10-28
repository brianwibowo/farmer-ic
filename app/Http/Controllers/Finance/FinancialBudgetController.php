<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListFinanceRequest;
use App\Http\Requests\Finance\SaveFinancialBudgetRequest;
use App\Http\Resources\Finance\FinancialBudgetResource;
use App\Models\Farm;
use App\Models\FinancialBudget;
use App\Models\Team;
use App\Services\Finance\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class FinancialBudgetController extends Controller
{
    public function __construct(private readonly FinanceService $finance) {}

    public function index(ListFinanceRequest $request, Team $current_team): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [FinancialBudget::class, $current_team]);

        return FinancialBudgetResource::collection($this->finance->paginateBudgets($request->user(), $current_team, $request->validated()));
    }

    public function store(SaveFinancialBudgetRequest $request, Team $current_team, Farm $farm): FinancialBudgetResource
    {
        abort_unless($farm->team_id === $current_team->id, 403);
        Gate::authorize('create', [FinancialBudget::class, $farm]);

        return new FinancialBudgetResource($this->finance->saveBudget($request->user(), $current_team, $farm, $request->validated()));
    }

    public function show(Request $request, Team $current_team, FinancialBudget $budget): FinancialBudgetResource
    {
        abort_unless($budget->farm?->team_id === $current_team->id, 403);
        Gate::authorize('view', $budget);

        return (new FinancialBudgetResource($this->finance->budget($request->user(), $current_team, $budget)))
            ->additional(['can' => [
                'update' => Gate::allows('update', $budget),
                'delete' => Gate::allows('delete', $budget),
            ]]);
    }

    public function update(SaveFinancialBudgetRequest $request, Team $current_team, FinancialBudget $budget): FinancialBudgetResource
    {
        abort_unless($budget->farm?->team_id === $current_team->id, 403);
        Gate::authorize('update', $budget);

        return new FinancialBudgetResource($this->finance->saveBudget($request->user(), $current_team, $budget->farm, $request->validated(), $budget));
    }

    public function destroy(Request $request, Team $current_team, FinancialBudget $budget): Response
    {
        abort_unless($budget->farm?->team_id === $current_team->id, 403);
        Gate::authorize('delete', $budget);

        $this->finance->deleteBudget($request->user(), $current_team, $budget);

        return response()->noContent();
    }
}
