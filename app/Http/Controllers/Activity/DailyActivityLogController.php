<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\ListDailyActivityLogsRequest;
use App\Http\Requests\Activity\StoreDailyActivityLogRequest;
use App\Http\Resources\DailyActivityLogResource;
use App\Models\Farm;
use App\Services\Activity\DailyActivityLogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DailyActivityLogController extends Controller
{
    public function __construct(private readonly DailyActivityLogService $logs) {}

    public function index(ListDailyActivityLogsRequest $request, Farm $farm): AnonymousResourceCollection
    {
        return DailyActivityLogResource::collection($this->logs->paginate($farm, $request->validated()));
    }

    public function store(StoreDailyActivityLogRequest $request, Farm $farm): DailyActivityLogResource
    {
        return new DailyActivityLogResource($this->logs->create($farm, $request->validated()));
    }

    public function show(Farm $farm, string $dailyLog): DailyActivityLogResource
    {
        return new DailyActivityLogResource($this->logs->find($farm, $dailyLog));
    }
}
