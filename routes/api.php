<?php

use App\Http\Controllers\Activity\DailyActivityLogController;
use App\Http\Controllers\FarmController;
use Illuminate\Support\Facades\Route;

Route::apiResource('kebun', FarmController::class)
    ->parameters(['kebun' => 'farm'])
    ->names('farms')
    ->whereUuid('farm')
    ->middleware('auth');

// JSON compatibility routes remain for the backend test harness. The Vue page
// uses the authenticated Inertia routes in routes/web.php instead.
Route::prefix('kebun/{farm}/daily-logs')->whereUuid('farm')->middleware('auth')->group(function (): void {
    Route::get('/', [DailyActivityLogController::class, 'index'])->name('api.daily-logs.index');
    Route::post('/', [DailyActivityLogController::class, 'store'])->name('api.daily-logs.store');
    Route::get('{dailyLog}', [DailyActivityLogController::class, 'show'])->whereUuid('dailyLog')->name('api.daily-logs.show');
});
