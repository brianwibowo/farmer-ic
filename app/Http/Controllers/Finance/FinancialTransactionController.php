<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListFinanceRequest;
use App\Http\Requests\Finance\SaveFinancialTransactionRequest;
use App\Http\Resources\Finance\FinancialTransactionResource;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use App\Models\Team;
use App\Services\Finance\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class FinancialTransactionController extends Controller
{
    public function __construct(private readonly FinanceService $finance) {}

    public function index(ListFinanceRequest $request, Team $current_team): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [FinancialTransaction::class, $current_team]);

        return FinancialTransactionResource::collection($this->finance->paginateTransactions($request->user(), $current_team, $request->validated()));
    }

    public function store(SaveFinancialTransactionRequest $request, Team $current_team, Farm $farm): FinancialTransactionResource
    {
        abort_unless($farm->team_id === $current_team->id, 403);
        Gate::authorize('create', [FinancialTransaction::class, $farm]);

        return new FinancialTransactionResource($this->finance->saveTransaction($request->user(), $current_team, $farm, $request->validated()));
    }

    public function show(Request $request, Team $current_team, FinancialTransaction $transaction): FinancialTransactionResource
    {
        abort_unless($transaction->farm?->team_id === $current_team->id, 403);
        Gate::authorize('view', $transaction);

        return (new FinancialTransactionResource($this->finance->transaction($request->user(), $current_team, $transaction)))
            ->additional(['can' => [
                'update' => Gate::allows('update', $transaction),
                'delete' => Gate::allows('delete', $transaction),
            ]]);
    }

    public function update(SaveFinancialTransactionRequest $request, Team $current_team, FinancialTransaction $transaction): FinancialTransactionResource
    {
        abort_unless($transaction->farm?->team_id === $current_team->id, 403);
        Gate::authorize('update', $transaction);

        return new FinancialTransactionResource($this->finance->saveTransaction($request->user(), $current_team, $transaction->farm, $request->validated(), $transaction));
    }

    public function destroy(Request $request, Team $current_team, FinancialTransaction $transaction): Response
    {
        abort_unless($transaction->farm?->team_id === $current_team->id, 403);
        Gate::authorize('delete', $transaction);

        $this->finance->deleteTransaction($request->user(), $current_team, $transaction);

        return response()->noContent();
    }
}
