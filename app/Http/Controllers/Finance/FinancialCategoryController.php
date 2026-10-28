<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListFinanceRequest;
use App\Http\Requests\Finance\SaveFinancialCategoryRequest;
use App\Http\Resources\Finance\FinancialCategoryResource;
use App\Models\FinancialCategory;
use App\Models\Team;
use App\Services\Finance\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class FinancialCategoryController extends Controller
{
    public function __construct(private readonly FinanceService $finance) {}

    public function index(ListFinanceRequest $request, Team $current_team): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [FinancialCategory::class, $current_team]);

        return FinancialCategoryResource::collection($this->finance->paginateCategories($request->user(), $current_team, $request->validated()));
    }

    public function store(SaveFinancialCategoryRequest $request, Team $current_team): FinancialCategoryResource
    {
        Gate::authorize('create', [FinancialCategory::class, $current_team]);

        return new FinancialCategoryResource($this->finance->saveCategory($request->user(), $current_team, $request->validated()));
    }

    public function show(Request $request, Team $current_team, FinancialCategory $category): FinancialCategoryResource
    {
        Gate::authorize('view', $category);

        return (new FinancialCategoryResource($this->finance->findCategory($request->user(), $current_team, $category)))
            ->additional(['can' => [
                'update' => Gate::allows('update', $category),
                'delete' => Gate::allows('delete', $category),
            ]]);
    }

    public function update(SaveFinancialCategoryRequest $request, Team $current_team, FinancialCategory $category): FinancialCategoryResource
    {
        Gate::authorize('update', $category);

        return new FinancialCategoryResource($this->finance->saveCategory($request->user(), $current_team, $request->validated(), $category));
    }

    public function destroy(Request $request, Team $current_team, FinancialCategory $category): Response
    {
        Gate::authorize('delete', $category);
        $this->finance->deleteCategory($request->user(), $current_team, $category);

        return response()->noContent();
    }
}
