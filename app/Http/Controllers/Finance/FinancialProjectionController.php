<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinanceRequest;
use App\Http\Requests\Finance\ListFinanceRequest;
use App\Http\Requests\Finance\SaveFinancialProjectionRequest;
use App\Http\Resources\Finance\FinancialProjectionResource;
use App\Models\Farm;
use App\Models\FinancialProjection;
use App\Services\Finance\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FinancialProjectionController extends Controller
{
    public function __construct(private readonly FinanceService $finance) {}

    public function index(ListFinanceRequest $request): Response
    {
        $team = $request->team();
        Gate::authorize('viewAny', [FinancialProjection::class, $team]);
        $records = $this->finance->paginateProjections($request->user(), $team, $request->validated());
        $createFarms = $this->finance->farms($request->user(), $team)
            ->filter(fn (Farm $farm): bool => Gate::allows('create', [FinancialProjection::class, $farm]))
            ->map(fn (Farm $farm): array => $farm->only(['id', 'name']))->values();

        return Inertia::render('farmer/FinanceRecords', [
            'resource' => 'projections',
            'title' => 'Proyeksi',
            'records' => $records->through(fn (FinancialProjection $record): array => [
                ...(new FinancialProjectionResource($record))->resolve($request),
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

    public function store(SaveFinancialProjectionRequest $request, Farm $farm): RedirectResponse
    {
        $team = $request->team();
        abort_unless($farm->team_id === $team->id, 403);
        Gate::authorize('create', [FinancialProjection::class, $farm]);
        $this->finance->saveProjection($request->user(), $team, $farm, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proyeksi berhasil ditambahkan.']);

        return to_route('finance.projections.index');
    }

    public function show(FinanceRequest $request, FinancialProjection $projection): Response
    {
        $team = $request->team();
        abort_unless($projection->farm?->team_id === $team->id, 403);
        Gate::authorize('view', $projection);

        return Inertia::render('farmer/FinanceRecordDetail', [
            'resource' => 'projections',
            'title' => 'Detail Proyeksi',
            'record' => (new FinancialProjectionResource($this->finance->projection($request->user(), $team, $projection)))->resolve($request),
        ]);
    }

    public function update(SaveFinancialProjectionRequest $request, FinancialProjection $projection): RedirectResponse
    {
        $team = $request->team();
        abort_unless($projection->farm?->team_id === $team->id, 403);
        Gate::authorize('update', $projection);
        $this->finance->saveProjection($request->user(), $team, $projection->farm, $request->validated(), $projection);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proyeksi berhasil diperbarui.']);

        return to_route('finance.projections.index');
    }

    public function destroy(FinanceRequest $request, FinancialProjection $projection): RedirectResponse
    {
        $team = $request->team();
        abort_unless($projection->farm?->team_id === $team->id, 403);
        Gate::authorize('delete', $projection);
        try {
            $this->finance->deleteProjection($request->user(), $team, $projection);
        } catch (ConflictHttpException $exception) {
            throw ValidationException::withMessages(['delete' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proyeksi berhasil dihapus.']);

        return to_route('finance.projections.index');
    }
}
