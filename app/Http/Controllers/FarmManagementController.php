<?php

namespace App\Http\Controllers;

use App\Http\Requests\Farms\ListFarmsRequest;
use App\Http\Requests\Farms\SaveFarmRequest;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\Team;
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
                ...$farm->only(['id', 'name', 'city', 'district', 'province', 'total_area_ha']),
                'can' => [
                    'update' => Gate::allows('update', $farm),
                    'delete' => Gate::allows('delete', $farm),
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
}
