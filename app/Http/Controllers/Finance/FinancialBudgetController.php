<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinanceRequest;
use App\Http\Requests\Finance\ListFinanceRequest;
use App\Http\Requests\Finance\SaveFinancialBudgetRequest;
use App\Http\Resources\Finance\FinancialBudgetResource;
use App\Models\Farm;
use App\Models\FinancialBudget;
use App\Services\Finance\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FinancialBudgetController extends Controller
{
    public function __construct(private readonly FinanceService $finance) {}

    public function index(ListFinanceRequest $request): Response
    {
        $team = $request->team();
        Gate::authorize('viewAny', [FinancialBudget::class, $team]);
        $records = $this->finance->paginateBudgets($request->user(), $team, $request->validated());
        $createFarms = $this->finance->farms($request->user(), $team)
            ->filter(fn (Farm $farm): bool => Gate::allows('create', [FinancialBudget::class, $farm]))
            ->map(fn (Farm $farm): array => $farm->only(['id', 'name']))->values();

        return Inertia::render('farmer/FinanceRecords', [
            'resource' => 'budgets',
            'title' => 'Anggaran',
            'records' => $records->through(fn (FinancialBudget $record): array => [
                ...(new FinancialBudgetResource($record))->resolve($request),
                'can' => [
                    'update' => Gate::allows('update', $record),
                    'delete' => Gate::allows('delete', $record),
                ],
            ]),
            'filters' => $request->validated(),
            'options' => [
                ...$this->finance->formOptions($request->user(), $team),
                'farms' => $createFarms,
            ],
            'can' => ['create' => $createFarms->isNotEmpty()],
        ]);
    }

    public function store(SaveFinancialBudgetRequest $request, Farm $farm): RedirectResponse
    {
        $team = $request->team();
        abort_unless($farm->team_id === $team->id, 403);
        Gate::authorize('create', [FinancialBudget::class, $farm]);
        $this->finance->saveBudget($request->user(), $team, $farm, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Anggaran berhasil ditambahkan.']);

        return to_route('finance.budgets.index');
    }

    public function show(FinanceRequest $request, FinancialBudget $budget): Response
    {
        $team = $request->team();
        abort_unless($budget->farm?->team_id === $team->id, 403);
        Gate::authorize('view', $budget);

        return Inertia::render('farmer/FinanceRecordDetail', [
            'resource' => 'budgets',
            'title' => 'Detail Anggaran',
            'record' => (new FinancialBudgetResource($this->finance->budget($request->user(), $team, $budget)))->resolve($request),
        ]);
    }

    public function update(SaveFinancialBudgetRequest $request, FinancialBudget $budget): RedirectResponse
    {
        $team = $request->team();
        abort_unless($budget->farm?->team_id === $team->id, 403);
        Gate::authorize('update', $budget);
        $this->finance->saveBudget($request->user(), $team, $budget->farm, $request->validated(), $budget);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Anggaran berhasil diperbarui.']);

        return to_route('finance.budgets.index');
    }

    public function destroy(FinanceRequest $request, FinancialBudget $budget): RedirectResponse
    {
        $team = $request->team();
        abort_unless($budget->farm?->team_id === $team->id, 403);
        Gate::authorize('delete', $budget);
        try {
            $this->finance->deleteBudget($request->user(), $team, $budget);
        } catch (ConflictHttpException $exception) {
            throw ValidationException::withMessages(['delete' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Anggaran berhasil dihapus.']);

        return to_route('finance.budgets.index');
    }
}
