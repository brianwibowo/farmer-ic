<?php

use App\Http\Controllers\Reference\LegacyMobileReferenceController;
use Illuminate\Support\Facades\Route;

Route::prefix('referensi')->name('reference.')->middleware(['auth', 'verified'])->group(function (): void {
    foreach ([
        '/' => ['home', 'Dashboard'],
        'kebun' => ['kebun', 'Kebun'],
        'kebun/detail' => ['kebun.detail', 'KebunDetail'],
        'kebun/aktivitas' => ['kebun.aktivitas', 'KebunAktivitas'],
        'aktivitas' => ['aktivitas', 'Aktivitas'],
        'rincian-aktivitas' => ['rincian-aktivitas', 'RincianAktivitas'],
        'laporan' => ['laporan', 'Laporan'],
        'finance' => ['finance', 'Finance'],
        'ai' => ['ai', 'Index'],
    ] as $path => [$name, $component]) {
        Route::get($path, LegacyMobileReferenceController::class)
            ->defaults('referencePage', $component)->name($name);
    }

    // Reserve the entire snapshot namespace so a team slug cannot turn legacy
    // links into live AI/API operations through the generic {current_team} routes.
    Route::any('{unavailable?}', fn () => abort(404))
        ->where('unavailable', '.*')->name('unavailable');
});
