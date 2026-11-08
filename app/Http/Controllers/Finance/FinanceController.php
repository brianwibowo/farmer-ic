<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinancePageRequest;
use App\Models\FinancialTransaction;
use App\Services\Finance\FinanceService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    public function __construct(private readonly FinanceService $finance) {}

    public function index(FinancePageRequest $request): Response
    {
        $team = $request->team();
        Gate::authorize('viewAny', [FinancialTransaction::class, $team]);

        return Inertia::render('farmer/Finance', $this->finance->cashflow($request->user(), $team, $request->validated()));
    }
}
