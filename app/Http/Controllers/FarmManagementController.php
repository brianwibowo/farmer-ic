<?php

namespace App\Http\Controllers;

use App\Http\Requests\Farms\ForceDeleteFarmRequest;
use App\Http\Requests\Farms\ListFarmsRequest;
use App\Http\Requests\Farms\SaveFarmRequest;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\Team;
use App\Services\FarmDeletionService;
use App\Services\FarmService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FarmManagementController extends Controller
{
    public function __construct(private readonly FarmService $farms) {}

    public function index(ListFarmsRequest $request): Response
    {
        $team = $request->user()->currentTeam;
        Gate::authorize('viewAny', [Farm::class, $team]);
        abort_unless($team instanceof Team, 403, 'Pilih tim aktif untuk mengelola kebun.');
        $farms = $this->farms->paginate($request->user(), $team, $request->validated());

        return Inertia::render('farms/Index', [
            'farms' => $farms->through(fn (Farm $farm): array => [
                ...$farm->only(['id', 'name', 'land_tenure', 'city', 'district', 'province', 'total_area_ha']),
                'can' => [
                    'update' => Gate::allows('update', $farm),
                    'delete' => Gate::allows('delete', $farm),
                    'forceDelete' => Gate::allows('forceDelete', $farm),
                ],
            ]),
            'summary' => $this->farms->summary($request->user(), $team),
            'filters' => array_replace([
                'search' => '', 'city' => '', 'province' => '',
                'sort' => 'name', 'direction' => 'asc', 'per_page' => 15,
            ], $request->validated()),
            'team' => $team->only(['id', 'name']),
            'can' => ['create' => Gate::allows('create', [Farm::class, $team])],
        ]);
    }

    public function create(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        Gate::authorize('create', [Farm::class, $team]);
        abort_unless($team instanceof Team, 403);

        return Inertia::render('farms/Form', [
            'farm' => null,
            'owners' => $this->farms->owners($request->user(), $team),
            'team' => $team->only(['id', 'name']),
        ]);
    }

    public function store(SaveFarmRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        Gate::authorize('create', [Farm::class, $team]);
        abort_unless($team instanceof Team, 403);
        $farm = $this->farms->create($request->validated(), $request->user(), $team);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kebun berhasil ditambahkan.']);

        return to_route('farm-management.show', $farm);
    }

    public function show(Request $request, Farm $farm): Response
    {
        Gate::authorize('view', $farm);

        return Inertia::render('farms/Show', [
            'farm' => $this->farms->detail($farm, $request->user()),
            'can' => [
                'update' => Gate::allows('update', $farm),
                'delete' => Gate::allows('delete', $farm),
                'forceDelete' => Gate::allows('forceDelete', $farm),
                'dailyLogs' => Gate::allows('viewAny', [DailyActivityLog::class, $farm->team]),
            ],
        ]);
    }

    public function edit(Request $request, Farm $farm): Response
    {
        Gate::authorize('update', $farm);
        $team = $farm->team;
        abort_unless($team instanceof Team, 403);

        return Inertia::render('farms/Form', [
            'farm' => $this->farms->detail($farm, $request->user()),
            'owners' => $this->farms->owners($request->user(), $team),
            'team' => $team->only(['id', 'name']),
        ]);
    }

    public function update(SaveFarmRequest $request, Farm $farm): RedirectResponse
    {
        Gate::authorize('update', $farm);
        $this->farms->update($farm, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perubahan kebun berhasil disimpan.']);

        return to_route('farm-management.show', $farm);
    }

    public function destroy(Farm $farm): RedirectResponse
    {
        Gate::authorize('delete', $farm);
        try {
            $this->farms->delete($farm);
        } catch (ConflictHttpException $exception) {
            throw ValidationException::withMessages(['delete' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kebun berhasil dihapus.']);

        return to_route('farm-management.index');
    }

    public function confirmForceDelete(Request $request, Farm $farm, FarmDeletionService $deletion): Response
    {
        Gate::authorize('forceDelete', $farm);

        return Inertia::render('farms/ForceDelete', [
            'farm' => $farm->only(['id', 'name']),
            'summary' => $deletion->summary($request->user(), $farm),
            'backUrl' => $farm->team_id !== null ? route('farm-management.show', $farm, false) : route('admin.dashboard', absolute: false),
        ]);
    }

    public function forceDestroy(ForceDeleteFarmRequest $request, Farm $farm, FarmDeletionService $deletion): RedirectResponse
    {
        // The request's early Policy check rejects forbidden actors before validation;
        // the controller remains the explicit authorization boundary for this action.
        Gate::authorize('forceDelete', $farm);
        try {
            $deletion->forceDelete($request->user(), $farm, $request->validated('confirmation_name'), $request->validated('reason'), $request->validated('summary_token'));
        } catch (ConflictHttpException $exception) {
            throw ValidationException::withMessages(['force_delete' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kebun dan data operasionalnya dihapus permanen. Akun pengguna tetap disimpan.']);

        return to_route($request->user()->currentTeam instanceof Team ? 'farm-management.index' : 'admin.dashboard');
    }
}
