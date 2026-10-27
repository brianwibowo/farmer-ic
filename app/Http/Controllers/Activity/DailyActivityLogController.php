<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\ListDailyActivityLogsRequest;
use App\Http\Requests\Activity\StoreDailyActivityLogRequest;
use App\Http\Resources\DailyActivityLogResource;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\Team;
use App\Services\Activity\DailyActivityLogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DailyActivityLogController extends Controller
{
    public function __construct(private readonly DailyActivityLogService $logs) {}

    public function index(ListDailyActivityLogsRequest $request, Farm $farm): AnonymousResourceCollection
    {
        Gate::authorize('view', $farm);
        $team = $farm->team;
        abort_unless($team instanceof Team, 404);

        return DailyActivityLogResource::collection($this->logs->paginate($farm, $request->user(), $team, $request->validated()));
    }

    public function store(StoreDailyActivityLogRequest $request, Farm $farm): DailyActivityLogResource
    {
        Gate::authorize('create', [DailyActivityLog::class, $farm]);

        return new DailyActivityLogResource($this->logs->create($farm, $request->validated()));
    }

    public function show(Farm $farm, string $dailyLog): DailyActivityLogResource
    {
        $log = $this->logs->find($farm, $dailyLog);
        Gate::authorize('view', $log);

        return new DailyActivityLogResource($log);
    }
}
