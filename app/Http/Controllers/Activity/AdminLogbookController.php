<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\ListAdminLogbooksRequest;
use App\Http\Resources\DailyActivityLogResource;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\Team;
use App\Services\Activity\AdminLogbookService;
use App\Support\RolePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminLogbookController extends Controller
{
    public function __construct(private readonly AdminLogbookService $logbooks) {}

    public function index(ListAdminLogbooksRequest $request): Response
    {
        $team = app(RolePermissions::class)->singleTenant($request->user()) ?? $request->user()->currentTeam;
        abort_unless($team instanceof Team, 403, 'Pilih tim aktif untuk membuka logbook.');
        Gate::authorize('viewAny', [Farm::class, $team]);
        Gate::authorize('viewAny', [DailyActivityLog::class, $team]);

        return Inertia::render('logbook/Index', [
            'farms' => $this->logbooks->farms($request->user(), $team, $request->validated()),
            'summary' => $this->logbooks->summary($request->user(), $team),
            'filters' => $request->validated(),
            'team' => $team->only(['id', 'name']),
        ]);
    }

    public function farm(ListAdminLogbooksRequest $request, Farm $farm): Response
    {
        Gate::authorize('view', $farm);
        $team = $farm->team;
        abort_unless($team instanceof Team, 404);
        Gate::authorize('viewAny', [DailyActivityLog::class, $team]);

        return Inertia::render('logbook/Farm', [
            'farm' => $farm->only(['id', 'name', 'city', 'district', 'province']),
            'logs' => $this->logbooks->paginate($farm, $request->user(), $team, $request->validated()),
            'summary' => $this->logbooks->summary($request->user(), $team, $farm),
            'filters' => $request->validated(),
            'recorders' => $this->logbooks->recorders($farm, $request->user(), $team),
        ]);
    }

    public function show(Request $request, Farm $farm, string $dailyLog): Response
    {
        Gate::authorize('view', $farm);
        $team = $farm->team;
        abort_unless($team instanceof Team, 404);
        Gate::authorize('viewAny', [DailyActivityLog::class, $team]);
        $log = $this->logbooks->find($farm, $request->user(), $team, $dailyLog);
        Gate::authorize('view', $log);

        return Inertia::render('logbook/Show', [
            'farm' => $farm->only(['id', 'name', 'city', 'district', 'province']),
            'log' => (new DailyActivityLogResource($log))->resolve($request),
        ]);
    }
}
