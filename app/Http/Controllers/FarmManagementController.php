<?php

namespace App\Http\Controllers;

use App\Http\Requests\Farms\ForceDeleteFarmRequest;
use App\Http\Requests\Farms\ListFarmsRequest;
use App\Http\Requests\Farms\SaveFarmRequest;
use App\Models\Commodity;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\FarmLegality;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmDeletionService;
use App\Services\FarmService;
use App\Services\SopTemplateService;
use App\Support\RolePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FarmManagementController extends Controller
{
    public function __construct(
        private readonly FarmService $farms,
        private readonly SopTemplateService $sops,
        private readonly \App\Services\FarmBlockService $blocks,
    ) {}

    public function index(ListFarmsRequest $request): Response
    {
        return app(RolePermissions::class)->withMembershipSnapshot($request->user(), function () use ($request): Response {
            $team = $request->user()->currentTeam;
            Gate::authorize('viewAny', [Farm::class, $team]);
            abort_unless($team instanceof Team, 403, 'Pilih tim aktif untuk mengelola kebun.');
            $farms = $this->farms->paginate($request->user(), $team, $request->validated());

            return Inertia::render('farms/Index', [
                'farms' => $farms->through(fn (Farm $farm): array => [
                    ...$farm->only(['id', 'name', 'land_tenure', 'land_tenure_party', 'city', 'district', 'province', 'total_area_ha']),
                    'can' => [
                        'update' => Gate::allows('update', $farm),
                        'delete' => Gate::allows('delete', $farm),
                        'forceDelete' => Gate::allows('forceDelete', $farm),
                    ],
                ]),
                'summary' => $this->farms->summary($request->user(), $team),
                'locationOptions' => $this->farms->locationOptions($request->user(), $team),
                'filters' => array_replace([
                    'search' => '', 'city' => '', 'province' => '',
                    'sort' => 'name', 'direction' => 'asc', 'per_page' => 15,
                ], $request->validated()),
                'team' => $team->only(['id', 'name']),
                'can' => ['create' => Gate::allows('create', [Farm::class, $team])],
            ]);
        });
    }

    public function create(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        Gate::authorize('create', [Farm::class, $team]);
        abort_unless($team instanceof Team, 403);

        return Inertia::render('farms/Form', [
            'farm' => null,
            'commodities' => Commodity::visibleTo($request->user(), $team)->orderBy('name')->get(['id', 'name']),
            'sops' => $this->sops->options($request->user(), $team),
            'picOptions' => $this->farms->picOptions($request->user(), $team),
            'team' => $team->only(['id', 'name']),
        ]);
    }

    public function store(SaveFarmRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        Gate::authorize('create', [Farm::class, $team]);
        abort_unless($team instanceof Team, 403);
        $this->authorizePic($request, $team);
        $farm = $this->farms->create($request->validated(), $request->user(), $team);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kebun berhasil ditambahkan.']);

        return to_route('farm-management.show', $farm);
    }

    public function show(Request $request, Farm $farm): Response
    {
        Gate::authorize('view', $farm);
        $team = $request->user()->currentTeam;
        $inActiveTeam = $team instanceof Team && (int) $farm->team_id === (int) $team->id;
        $canViewLegality = $inActiveTeam && Gate::allows('viewAny', [FarmLegality::class, $team]);

        return Inertia::render('farms/Show', [
            'farm' => $this->farms->detail($farm, $request->user()),
            'legalityUrl' => $canViewLegality ? route('farm-management.legality.show', $farm, false) : null,
            'blocks' => Gate::allows('viewAny', [FarmBlock::class, $farm->team]) ? $this->blocks->overview($farm, $request->user(), $farm->team, includeGrowth: true)->map(fn (FarmBlock $block) => [...$block->toArray(), 'can' => ['update' => Gate::allows('update', $block), 'delete' => Gate::allows('delete', $block), 'createBatch' => Gate::allows('create', [PlantingCycle::class, $block])]]) : [],
            'batchPermissions' => PlantingCycle::visibleTo($request->user(), $farm->team)->where('farm_id', $farm->id)->whereNotNull('block_id')
                ->with(['farm.team', 'block'])->get()->mapWithKeys(fn (PlantingCycle $batch) => [$batch->id => Gate::allows('update', $batch) && $batch->block !== null && $this->blocks->isBatchEditable($batch->block, $batch)]),
            'blockCapacity' => Gate::allows('create', [FarmBlock::class, $farm]) ? $this->blocks->formCapacity($farm, $request->user(), $farm->team) : null,
            'deletionBlockers' => Gate::allows('delete', $farm) ? $this->farms->deletionBlockers($farm, $request->user(), $farm->team) : [],
            'can' => [
                'update' => Gate::allows('update', $farm),
                'delete' => Gate::allows('delete', $farm),
                'forceDelete' => Gate::allows('forceDelete', $farm),
                'dailyLogs' => Gate::allows('viewAny', [DailyActivityLog::class, $farm->team]),
                'blocks' => Gate::allows('viewAny', [FarmBlock::class, $farm->team]),
                'createBlock' => Gate::allows('create', [FarmBlock::class, $farm]),
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
            'commodities' => Commodity::visibleTo($request->user(), $team)->orderBy('name')->get(['id', 'name']),
            'sops' => $this->sops->options($request->user(), $team),
            'picOptions' => $this->farms->picOptions($request->user(), $team, $farm),
            'team' => $team->only(['id', 'name']),
        ]);
    }

    public function update(SaveFarmRequest $request, Farm $farm): RedirectResponse
    {
        Gate::authorize('update', $farm);
        $this->authorizePic($request, $farm->team, $farm);
        $this->farms->update($farm, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perubahan kebun berhasil disimpan.']);

        return to_route('farm-management.show', $farm);
    }

    private function authorizePic(SaveFarmRequest $request, Team $team, ?Farm $farm = null): void
    {
        $id = $request->validated('pic_user_id');
        if (! $id) {
            return;
        }
        $pic = User::visibleTo($request->user(), $team)->whereKey($id)->first();
        if (! $pic) {
            throw ValidationException::withMessages(['pic_user_id' => 'Petugas tidak tersedia.']);
        }
        Gate::authorize('configure', [$pic, $pic->role, $pic->teams()->where('teams.is_personal', false)->get(), $farm]);
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

    public function deletionCheck(Request $request, Farm $farm): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('delete', $farm);

        return response()->json(['blockers' => $this->farms->deletionBlockers($farm, $request->user(), $farm->team)]);
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
