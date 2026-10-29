<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinanceRequest;
use App\Http\Requests\Finance\ListFinanceRequest;
use App\Http\Requests\Finance\SaveFinancialTransactionRequest;
use App\Http\Resources\Finance\FinancialTransactionResource;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use App\Services\Finance\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FinancialTransactionController extends Controller
{
    public function __construct(private readonly FinanceService $finance) {}

    public function index(ListFinanceRequest $request): Response
    {
        $team = $request->team();
        Gate::authorize('viewAny', [FinancialTransaction::class, $team]);
        $records = $this->finance->paginateTransactions($request->user(), $team, $request->validated());
        $createFarms = $this->finance->farms($request->user(), $team)
            ->filter(fn (Farm $farm): bool => Gate::allows('create', [FinancialTransaction::class, $farm]))
            ->map(fn (Farm $farm): array => $farm->only(['id', 'name']))->values();

        return Inertia::render('farmer/FinanceRecords', [
            'resource' => 'transactions',
            'title' => 'Transaksi',
            'records' => $records->through(fn (FinancialTransaction $record): array => [
                ...(new FinancialTransactionResource($record))->resolve($request),
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

    public function store(SaveFinancialTransactionRequest $request, Farm $farm): RedirectResponse
    {
        $team = $request->team();
        abort_unless($farm->team_id === $team->id, 403);
        Gate::authorize('create', [FinancialTransaction::class, $farm]);
        $this->finance->saveTransaction($request->user(), $team, $farm, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaksi berhasil ditambahkan.']);

        return to_route('finance.transactions.index');
    }

    public function show(FinanceRequest $request, FinancialTransaction $transaction): Response
    {
        $team = $request->team();
        abort_unless($transaction->farm?->team_id === $team->id, 403);
        Gate::authorize('view', $transaction);

        return Inertia::render('farmer/FinanceRecordDetail', [
            'resource' => 'transactions',
            'title' => 'Detail Transaksi',
            'record' => (new FinancialTransactionResource($this->finance->transaction($request->user(), $team, $transaction)))->resolve($request),
        ]);
    }

    public function update(SaveFinancialTransactionRequest $request, FinancialTransaction $transaction): RedirectResponse
    {
        $team = $request->team();
        abort_unless($transaction->farm?->team_id === $team->id, 403);
        Gate::authorize('update', $transaction);
        $this->finance->saveTransaction($request->user(), $team, $transaction->farm, $request->validated(), $transaction);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaksi berhasil diperbarui.']);

        return to_route('finance.transactions.index');
    }

    public function destroy(FinanceRequest $request, FinancialTransaction $transaction): RedirectResponse
    {
        $team = $request->team();
        abort_unless($transaction->farm?->team_id === $team->id, 403);
        Gate::authorize('delete', $transaction);
        try {
            $this->finance->deleteTransaction($request->user(), $team, $transaction);
        } catch (ConflictHttpException $exception) {
            throw ValidationException::withMessages(['delete' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaksi berhasil dihapus.']);

        return to_route('finance.transactions.index');
    }
}
