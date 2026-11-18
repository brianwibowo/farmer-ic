<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\ListPetugasReportsRequest;
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
    public function history(ListPetugasReportsRequest $request, PetugasReportReadService $reports, VoluntaryActivityCategoryService $categories): Response
    {
        $filters = $request->validated();
        $filters['mine'] = $request->boolean('mine');
        $filters['per_page'] = (int) ($filters['per_page'] ?? 10);
        $farm = $request->user()->assignedFarm;
        if (! $farm instanceof Farm) {
            return Inertia::render('petugas/Laporan', ['farm' => null, 'reports' => null, 'stats' => ['sop' => 0, 'mandiri' => 0], 'filters' => $filters, 'types' => [], 'categories' => []]);
        }
        Gate::authorize('view', $farm);
        Gate::authorize('viewAny', [FarmActivity::class, $farm->team]);

        return Inertia::render('petugas/Laporan', ['farm' => $farm->only(['id', 'name']), 'filters' => $filters,
            'stats' => $reports->sourceCounts($request->user(), $farm, $filters),
            'reports' => $reports->listing($request->user(), $farm, $filters),
            'types' => ActivityType::visibleTo($request->user(), $farm->team)->where('is_active', true)->where('is_selectable', true)->orderBy('sort_order')->get(['code', 'name']),
            'categories' => $categories->forFarm($request->user(), $farm)]);
    }

    public function show(Request $request, FarmActivity $activity, DailyActivityReportService $reports): Response
    {
        Gate::authorize('view', $activity);
        abort_unless($activity->farm_id === $request->user()->assigned_farm_id, 403);
        $farm = $activity->farm;
        if ($activity->daily_activity_log_id === null) {
            return Inertia::render('petugas/ReportShow', [
                'farm' => $farm->only(['id', 'name']), 'log' => null,
                'legacyActivity' => $reports->activity($request->user(), $farm, $activity),
                'date' => $activity->activity_date->setTimezone('Asia/Jakarta')->toDateString(),
            ]);
        }
        $log = DailyActivityLog::visibleTo($request->user(), $farm->team)->findOrFail($activity->daily_activity_log_id);
        Gate::authorize('view', $log);
        $data = $reports->report($request->user(), $farm, $log);
        $data['activities'] = array_values(array_filter($data['activities'], fn ($row) => $row['id'] === $activity->id));

        return Inertia::render('petugas/ReportShow', ['farm' => $farm->only(['id', 'name']), 'log' => $data,
            'date' => $log->work_date->toDateString()]);
    }

    public function index(Request $request, DailyActivityHeaderService $headers, DailyActivityReportService $reports): Response
    {
        $farm = $request->user()->assignedFarm;
        if (! $farm instanceof Farm) {
            return Inertia::render('petugas/DailyReport', [
                'farm' => null, 'log' => null, 'date' => now('Asia/Jakarta')->toDateString(),
                'today' => now('Asia/Jakarta')->toDateString(), 'canRecord' => false,
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
            'canRecord' => Gate::allows('create', [DailyActivityLog::class, $farm]),
        ]);
    }
}
