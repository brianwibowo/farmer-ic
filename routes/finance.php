<?php

use App\Http\Controllers\Finance\FinanceSummaryController;
use App\Http\Controllers\Finance\FinancialBudgetController;
use App\Http\Controllers\Finance\FinancialCategoryController;
use App\Http\Controllers\Finance\FinancialProjectionController;
use App\Http\Controllers\Finance\FinancialTransactionController;
use Illuminate\Support\Facades\Route;

// Included inside the authenticated, verified, team-membership web group.
// JSON endpoints use the normal Laravel session and CSRF protection.
Route::prefix('finance')->name('finance.')->group(function (): void {
    Route::get('summary', [FinanceSummaryController::class, 'summary'])->name('summary');
    Route::get('transactions/export', [FinanceSummaryController::class, 'export'])->name('transactions.export');

    Route::apiResource('categories', FinancialCategoryController::class)->whereUuid('category');
    Route::apiResource('transactions', FinancialTransactionController::class)->except('store')->whereUuid('transaction');
    Route::apiResource('budgets', FinancialBudgetController::class)->except('store')->whereUuid('budget');
    Route::apiResource('projections', FinancialProjectionController::class)->except('store')->whereUuid('projection');

    Route::prefix('farms/{farm}')->whereUuid('farm')->group(function (): void {
        Route::post('transactions', [FinancialTransactionController::class, 'store'])->name('transactions.store');
        Route::post('budgets', [FinancialBudgetController::class, 'store'])->name('budgets.store');
        Route::post('projections', [FinancialProjectionController::class, 'store'])->name('projections.store');
    });
});
