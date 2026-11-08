<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinanceRequest;
use App\Http\Requests\Finance\ListFinanceRequest;
use App\Http\Requests\Finance\SaveFinancialCategoryRequest;
use App\Http\Resources\Finance\FinancialCategoryResource;
use App\Models\FinancialCategory;
use App\Services\Finance\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FinancialCategoryController extends Controller
{
    public function __construct(private readonly FinanceService $finance) {}

    public function index(ListFinanceRequest $request): Response
    {
        $team = $request->team();
        Gate::authorize('viewAny', [FinancialCategory::class, $team]);
        $records = $this->finance->paginateCategories($request->user(), $team, $request->validated());

        return Inertia::render('farmer/FinanceRecords', [
            'resource' => 'categories',
            'title' => 'Kategori',
            'records' => $records->through(fn (FinancialCategory $record): array => [
                ...(new FinancialCategoryResource($record))->resolve($request),
                'can' => [
                    'update' => Gate::allows('update', $record),
                    'delete' => Gate::allows('delete', $record),
                ],
            ]),
            'filters' => $request->validated(),
            'options' => [
                ...$this->finance->formOptions($request->user(), $team),
                'farms' => [],
            ],
            'can' => ['create' => Gate::allows('create', [FinancialCategory::class, $team])],
        ]);
    }

    public function store(SaveFinancialCategoryRequest $request): RedirectResponse
    {
        $team = $request->team();
        Gate::authorize('create', [FinancialCategory::class, $team]);
        $this->finance->saveCategory($request->user(), $team, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori berhasil ditambahkan.']);

        return to_route('finance.categories.index');
    }

    public function show(FinanceRequest $request, FinancialCategory $category): Response
    {
        $team = $request->team();
        Gate::authorize('view', $category);

        return Inertia::render('farmer/FinanceRecordDetail', [
            'resource' => 'categories',
            'title' => 'Detail Kategori',
            'record' => (new FinancialCategoryResource($this->finance->findCategory($request->user(), $team, $category)))->resolve($request),
        ]);
    }

    public function update(SaveFinancialCategoryRequest $request, FinancialCategory $category): RedirectResponse
    {
        $team = $request->team();
        Gate::authorize('update', $category);
        $this->finance->saveCategory($request->user(), $team, $request->validated(), $category);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori berhasil diperbarui.']);

        return to_route('finance.categories.index');
    }

    public function destroy(FinanceRequest $request, FinancialCategory $category): RedirectResponse
    {
        $team = $request->team();
        Gate::authorize('delete', $category);
        try {
            $this->finance->deleteCategory($request->user(), $team, $category);
        } catch (ConflictHttpException $exception) {
            throw ValidationException::withMessages(['delete' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori berhasil dihapus.']);

        return to_route('finance.categories.index');
    }
}
