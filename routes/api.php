<?php

use App\Http\Controllers\Activity\DailyActivityLogController;
use App\Http\Controllers\FarmController;
use Illuminate\Support\Facades\Route;

Route::apiResource('kebun', FarmController::class)
    ->parameters(['kebun' => 'farm'])
    ->names('farms')
    ->whereUuid('farm');

// The existing EnsureLocalFarmApi middleware covers every /api/kebun/* route.
// Authentication and Policies are intentionally deferred for this local slice.
Route::prefix('kebun/{farm}/daily-logs')->whereUuid('farm')->group(function (): void {
    Route::get('/', [DailyActivityLogController::class, 'index'])->name('daily-logs.index');
    Route::post('/', [DailyActivityLogController::class, 'store'])->name('daily-logs.store');
    Route::get('{dailyLog}', [DailyActivityLogController::class, 'show'])->whereUuid('dailyLog')->name('daily-logs.show');
});
