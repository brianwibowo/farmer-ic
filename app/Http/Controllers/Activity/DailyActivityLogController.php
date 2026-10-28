<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\ListDailyActivityLogsRequest;
use App\Http\Requests\Activity\StoreDailyActivityLogRequest;
use App\Http\Resources\DailyActivityLogResource;
use App\Http\Resources\FarmResource;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\Team;
use App\Services\Activity\DailyActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

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

    public function show(Farm $farm, string $dailyLog): Response|DailyActivityLogResource
    {
        $log = $this->logs->find($farm, $dailyLog);
        Gate::authorize('view', $log);

        if (request()->expectsJson()) {
            return new DailyActivityLogResource($log);
        }

        return Inertia::render('farmer/DailyActivityLogDetail', [
            'farm' => FarmResource::make($farm)->resolve(request()),
            'log' => DailyActivityLogResource::make($log)->resolve(request()),
        ]);
    }
}
