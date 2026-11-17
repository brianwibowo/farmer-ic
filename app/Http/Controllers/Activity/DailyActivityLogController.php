<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\ListDailyActivityLogsRequest;
use App\Http\Requests\Activity\StoreDailyActivityLogRequest;
use App\Http\Resources\DailyActivityLogResource;
use App\Http\Resources\FarmResource;
use App\Models\ActivityPhoto;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\Team;
use App\Services\Activity\DailyActivityLogService;
use App\Services\Activity\DailyActivityReportService;
use App\Support\ActivityInputLimits;
use App\Support\ActivityPhotoFile;
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
            'numericLimits' => ActivityInputLimits::mapping(),
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
            'canRecord' => Gate::allows('petugas.access') && $request->user()->assigned_farm_id === $farm->id && Gate::allows('create', [DailyActivityLog::class, $farm]),
        ]);
    }

    public function photo(Request $request, Farm $farm, FarmActivity $activity, ActivityPhoto $photo): StreamedResponse
    {
        Gate::authorize('view', $farm);
        Gate::authorize('view', $activity);
        Gate::authorize('view', $photo);
        abort_unless($activity->farm_id === $farm->id && $photo->activity_id === $activity->id, 404);
        $photo = ActivityPhoto::visibleTo($request->user(), $farm->team)->where('activity_id', $activity->id)->findOrFail($photo->id);
        abort_unless(ActivityPhotoFile::available($photo), 404);

        if ($photo->image_url === '/images/kebun-placeholder.svg') {
            return response()->stream(fn () => print file_get_contents(public_path('images/kebun-placeholder.svg')), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, no-store']);
        }

        return Storage::disk('local')->response($photo->image_url, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
