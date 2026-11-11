<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Models\ActivityType;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Services\Activity\DailyActivityHeaderService;
use App\Services\Activity\DailyActivityReportService;
use App\Services\Activity\PetugasReportReadService;
use App\Services\Activity\VoluntaryActivityCategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PetugasDailyActivityController extends Controller
{
    public function history(Request $request, PetugasReportReadService $reports, VoluntaryActivityCategoryService $categories): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'type' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'in:sop,mandiri'], 'date' => ['nullable', 'date_format:Y-m-d'], 'mine' => ['nullable', 'boolean']]);
        $filters['mine'] = $request->boolean('mine');
        $farm = $request->user()->assignedFarm;
        if (! $farm instanceof Farm) {
            return Inertia::render('petugas/Laporan', ['farm' => null, 'reports' => null, 'filters' => $filters, 'types' => [], 'categories' => []]);
        }
        Gate::authorize('view', $farm);
        Gate::authorize('viewAny', [FarmActivity::class, $farm->team]);

        return Inertia::render('petugas/Laporan', ['farm' => $farm->only(['id', 'name']), 'filters' => $filters,
            'reports' => $reports->listing($request->user(), $farm, $filters),
            'types' => ActivityType::visibleTo($request->user(), $farm->team)->where('is_selectable', true)->orderBy('sort_order')->get(['code', 'name']),
            'categories' => $categories->forFarm($request->user(), $farm)]);
    }

    public function show(Request $request, FarmActivity $activity, DailyActivityReportService $reports): Response
    {
        Gate::authorize('view', $activity);
        abort_unless($activity->farm_id === $request->user()->assigned_farm_id, 403);
        $farm = $activity->farm;
        $log = DailyActivityLog::visibleTo($request->user(), $farm->team)->findOrFail($activity->daily_activity_log_id);
        Gate::authorize('view', $log);
        $data = $reports->report($request->user(), $farm, $log);
        $data['activities'] = array_values(array_filter($data['activities'], fn ($row) => $row['id'] === $activity->id));

        return Inertia::render('petugas/ReportShow', ['farm' => $farm->only(['id', 'name']), 'log' => $data,
            'canUpdate' => Gate::allows('update', $log), 'date' => $log->work_date->toDateString()]);
    }

    public function index(Request $request, DailyActivityHeaderService $headers, DailyActivityReportService $reports): Response
    {
        $farm = $request->user()->assignedFarm;
        if (! $farm instanceof Farm) {
            return Inertia::render('petugas/DailyReport', [
                'farm' => null, 'log' => null, 'date' => now('Asia/Jakarta')->toDateString(),
                'today' => now('Asia/Jakarta')->toDateString(), 'canUpdate' => false, 'canRecord' => false,
            ]);
        }
        Gate::authorize('view', $farm);
        Gate::authorize('viewAny', [DailyActivityLog::class, $farm->team]);
        $date = $request->validate(['work_date' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()]]);
        $date = $date['work_date'] ?? now('Asia/Jakarta')->toDateString();
        $log = $headers->forDate($farm, $request->user(), $date);

        return Inertia::render('petugas/DailyReport', [
            'farm' => $farm->only(['id', 'name']), 'date' => $date, 'today' => now('Asia/Jakarta')->toDateString(),
            'log' => $log === null ? null : $reports->report($request->user(), $farm, $log),
            'canUpdate' => $log !== null && Gate::allows('update', $log),
            'canRecord' => Gate::allows('create', [DailyActivityLog::class, $farm]),
        ]);
    }
}
