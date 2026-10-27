<?php

use App\Http\Controllers\Activity\DailyActivityLogController;
use App\Http\Controllers\FarmController;
use Illuminate\Support\Facades\Route;

Route::apiResource('kebun', FarmController::class)
    ->parameters(['kebun' => 'farm'])
    ->names('farms')
    ->whereUuid('farm')
    ->middleware('auth');

// The existing EnsureLocalFarmApi middleware covers every /api/kebun/* route;
// authentication and model-policy checks are enforced by the requests/controllers.
Route::prefix('kebun/{farm}/daily-logs')->whereUuid('farm')->middleware('auth')->group(function (): void {
    Route::get('/', [DailyActivityLogController::class, 'index'])->name('daily-logs.index');
    Route::post('/', [DailyActivityLogController::class, 'store'])->name('daily-logs.store');
    Route::get('{dailyLog}', [DailyActivityLogController::class, 'show'])->whereUuid('dailyLog')->name('daily-logs.show');
});
