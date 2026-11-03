<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\ListDailyActivityLogsRequest;
use App\Http\Requests\Activity\StoreDailyActivityLogRequest;
use App\Http\Requests\Activity\UpdateDailyActivityLogRequest;
use App\Http\Resources\DailyActivityHeaderResource;
use App\Http\Resources\DailyActivityLogResource;
use App\Http\Resources\FarmResource;
use App\Models\ActivityPhoto;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\Team;
use App\Services\Activity\DailyActivityHeaderService;
use App\Services\Activity\DailyActivityLogService;
use App\Services\Activity\DailyActivityReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyActivityLogController extends Controller
{
    public function __construct(private readonly DailyActivityLogService $logs) {}

    public function index(ListDailyActivityLogsRequest $request, Farm $farm): Response|AnonymousResourceCollection
    {
        Gate::authorize('view', $farm);
        $team = $farm->team;
        abort_unless($team instanceof Team, 404);
        $logs = $this->logs->paginate($farm, $request->user(), $team, $request->validated());

        if ($request->expectsJson()) {
            return DailyActivityLogResource::collection($logs);
        }

        return Inertia::render('farmer/DailyActivityLog', [
            'farm' => FarmResource::make($farm)->resolve($request),
            'logs' => DailyActivityLogResource::collection($logs)->toResponse($request)->getData(true),
            'filters' => $request->validated(),
            'recorder' => [
                'id' => $request->user()->getKey(),
                'name' => $request->user()->name,
            ],
            'today' => now('Asia/Jakarta')->format('Y-m-d'),
            'blocks' => Gate::allows('viewAny', [\App\Models\FarmBlock::class, $team]) ? $this->logs->blockOptions($farm, $request->user(), $team) : [],
            'can' => [
                'create' => Gate::allows('create', [DailyActivityLog::class, $farm]),
            ],
        ]);
    }

    public function store(StoreDailyActivityLogRequest $request, Farm $farm): DailyActivityLogResource|RedirectResponse
    {
        Gate::authorize('create', [DailyActivityLog::class, $farm]);
        $data = $request->validated();
        $data['recorded_by'] = $request->user()->getKey();
        $log = $this->logs->create($farm, $data);

        if ($request->expectsJson()) {
            return new DailyActivityLogResource($log);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logbook harian berhasil disimpan.']);

        return to_route('daily-logs.index', ['farm' => $farm]);
    }

    public function show(Request $request, Farm $farm, string $dailyLog, DailyActivityReportService $reports): Response|DailyActivityLogResource
    {
        Gate::authorize('view', $farm);
        $log = $this->logs->find($farm, $dailyLog, $request->user());
        Gate::authorize('view', $log);

        if (request()->expectsJson()) {
            return new DailyActivityLogResource($log);
        }

        return Inertia::render('farmer/DailyActivityLogDetail', [
            'farm' => FarmResource::make($farm)->resolve(request()),
            'log' => $reports->report($request->user(), $farm, $log),
            'canUpdate' => Gate::allows('update', $log),
            'canRecord' => Gate::allows('petugas.access') && $request->user()->assigned_farm_id === $farm->id && Gate::allows('create', [DailyActivityLog::class, $farm]),
        ]);
    }

    public function context(Request $request, Farm $farm, DailyActivityHeaderService $headers): DailyActivityHeaderResource|\Illuminate\Http\JsonResponse
    {
        Gate::authorize('view', $farm);
        Gate::authorize('viewAny', [DailyActivityLog::class, $farm->team]);
        $data = $request->validate(['work_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()]]);
        $log = $headers->forDate($farm, $request->user(), $data['work_date']);

        return $log === null ? response()->json(['data' => null]) : new DailyActivityHeaderResource($log);
    }

    public function update(UpdateDailyActivityLogRequest $request, Farm $farm, string $dailyLog, DailyActivityHeaderService $headers): RedirectResponse
    {
        Gate::authorize('view', $farm);
        $log = $this->logs->find($farm, $dailyLog, $request->user());
        Gate::authorize('update', $log);
        $headers->update($log, $request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Informasi harian bersama diperbarui. Aktivitas dan pembuat pertama tidak diubah.']);

        return back();
    }

    public function photo(Request $request, Farm $farm, FarmActivity $activity, ActivityPhoto $photo): StreamedResponse
    {
        Gate::authorize('view', $farm);
        Gate::authorize('view', $activity);
        Gate::authorize('view', $photo);
        abort_unless($activity->farm_id === $farm->id && $photo->activity_id === $activity->id, 404);
        $photo = ActivityPhoto::visibleTo($request->user(), $farm->team)->where('activity_id', $activity->id)->findOrFail($photo->id);
        if ($photo->image_url === '/images/kebun-placeholder.svg') {
            return response()->stream(fn () => print file_get_contents(public_path('images/kebun-placeholder.svg')), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, no-store']);
        }
        abort_unless(str_starts_with($photo->image_url, 'task-activities/'.$activity->id.'/') && ! str_contains($photo->image_url, '..'), 404);

        return Storage::disk('local')->response($photo->image_url, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
