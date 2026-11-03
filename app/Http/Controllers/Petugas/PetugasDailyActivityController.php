<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Services\Activity\DailyActivityHeaderService;
use App\Services\Activity\DailyActivityReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PetugasDailyActivityController extends Controller
{
    public function index(Request $request, DailyActivityHeaderService $headers, DailyActivityReportService $reports): Response
    {
        $farm = $request->user()->assignedFarm;
        if (! $farm instanceof Farm) {
            return Inertia::render('petugas/Aktivitas', [
                'farm' => null, 'log' => null, 'date' => now('Asia/Jakarta')->toDateString(),
                'today' => now('Asia/Jakarta')->toDateString(), 'canUpdate' => false, 'canRecord' => false,
            ]);
        }
        Gate::authorize('view', $farm);
        Gate::authorize('viewAny', [DailyActivityLog::class, $farm->team]);
        $date = $request->validate(['work_date' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()]]);
        $date = $date['work_date'] ?? now('Asia/Jakarta')->toDateString();
        $log = $headers->forDate($farm, $request->user(), $date);

        return Inertia::render('petugas/Aktivitas', [
            'farm' => $farm->only(['id', 'name']), 'date' => $date, 'today' => now('Asia/Jakarta')->toDateString(),
            'log' => $log === null ? null : $reports->report($request->user(), $farm, $log),
            'canUpdate' => $log !== null && Gate::allows('update', $log),
            'canRecord' => Gate::allows('create', [DailyActivityLog::class, $farm]),
        ]);
    }
}
