<?php

use App\Http\Controllers\FarmController;
use Illuminate\Support\Facades\Route;

Route::apiResource('kebun', FarmController::class)
    ->parameters(['kebun' => 'farm'])
    ->names('farms')
    ->whereUuid('farm');
