<?php

namespace App\Http\Controllers;

use App\Http\Requests\Blocks\ListFarmBlocksRequest;
use App\Http\Requests\Blocks\SaveFarmBlockRequest;
use App\Http\Requests\Blocks\StorePreparationBatchRequest;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Services\Activity\HarvestClosureService;
use App\Services\FarmBlockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FarmBlockController extends Controller
{
    public function __construct(private readonly FarmBlockService $blocks) {}

    public function farms(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        Gate::authorize('viewAny', [FarmBlock::class, $team]);
        Gate::authorize('viewAny', [Farm::class, $team]);

        return Inertia::render('farmer/blocks/Farms', [
            'farms' => $this->blocks->farms($request->user(), $team),
            'team' => $team->only(['name']),
        ]);
    }

    public function index(ListFarmBlocksRequest $request, Farm $farm): Response
    {
        Gate::authorize('view', $farm);
        $team = $this->team($farm);
        Gate::authorize('viewAny', [FarmBlock::class, $team]);

        return Inertia::render('farmer/blocks/Index', [
            'farm' => $farm->only(['id', 'name', 'total_area_ha']),
            'blocks' => $this->blocks->paginate($farm, $request->user(), $team, $request->validated())->through(fn (FarmBlock $block) => [...$block->toArray(), 'can' => ['update' => Gate::allows('update', $block), 'delete' => Gate::allows('delete', $block), 'createBatch' => Gate::allows('create', [PlantingCycle::class, $block])]]),
            'batchPermissions' => PlantingCycle::visibleTo($request->user(), $farm->team)->where('farm_id', $farm->id)->whereNotNull('block_id')
                ->with(['farm.team', 'block'])->get()->mapWithKeys(fn (PlantingCycle $batch) => [$batch->id => Gate::allows('update', $batch) && $batch->block !== null && $this->blocks->isBatchEditable($batch->block, $batch)]),
            'harvestPermissions' => PlantingCycle::visibleTo($request->user(), $farm->team)->where('farm_id', $farm->id)->whereNotNull('block_id')
                ->with('farm.team')->get()->mapWithKeys(fn (PlantingCycle $batch) => [$batch->id => Gate::allows('configure', $batch)]),
            'summary' => $this->blocks->summary($farm, $request->user(), $team),
            'blockCapacity' => $this->blocks->formCapacity($farm, $request->user(), $team),
            'filters' => array_replace(['search' => '', 'status' => 'all', 'per_page' => 15], $request->validated()),
            'can' => ['create' => Gate::allows('create', [FarmBlock::class, $farm])],
        ]);
    }

    public function create(Request $request, Farm $farm): Response
    {
        Gate::authorize('create', [FarmBlock::class, $farm]);

        return Inertia::render('farmer/blocks/Form', ['farm' => $farm->only(['id', 'name', 'total_area_ha']), 'block' => null,
            'capacity' => $this->blocks->formCapacity($farm, $request->user(), $this->team($farm))]);
    }

    public function store(SaveFarmBlockRequest $request, Farm $farm): RedirectResponse
    {
        Gate::authorize('create', [FarmBlock::class, $farm]);
        $block = $this->blocks->create($farm, $request->user(), $this->team($farm), $request->safe()->except(['area_value', 'area_unit']));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Blok dibuat. Catat pembersihan lahan melalui laporan aktivitas.']);

        return $this->returnToFarm($request, $farm) ?? to_route('blocks.show', [$farm, $block]);
    }

    public function show(Request $request, Farm $farm, FarmBlock $block): RedirectResponse
    {
        $this->matchFarm($farm, $block);
        Gate::authorize('view', $block);

        return to_route('blocks.index', $farm);
    }

    public function edit(Request $request, Farm $farm, FarmBlock $block): Response
    {
        $this->matchFarm($farm, $block);
        Gate::authorize('update', $block);

        return Inertia::render('farmer/blocks/Form', [
            'farm' => $farm->only(['id', 'name', 'total_area_ha']),
            'block' => $this->blocks->detail($block, $request->user(), $this->team($farm))['block'],
            'capacity' => $this->blocks->formCapacity($farm, $request->user(), $this->team($farm), $block),
        ]);
    }

    public function update(SaveFarmBlockRequest $request, Farm $farm, FarmBlock $block): RedirectResponse
    {
        $this->matchFarm($farm, $block);
        Gate::authorize('update', $block);
        $this->blocks->update($block, $request->user(), $this->team($farm), $request->safe()->except(['area_value', 'area_unit']));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perubahan blok disimpan.']);

        return $this->returnToFarm($request, $farm) ?? to_route('blocks.show', [$farm, $block]);
    }

    public function destroy(Request $request, Farm $farm, FarmBlock $block): RedirectResponse
    {
        $this->matchFarm($farm, $block);
        Gate::authorize('delete', $block);
        $this->blocks->delete($block, $request->user(), $this->team($farm));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Blok dihapus.']);

        return $this->returnToFarm($request, $farm) ?? to_route('blocks.index', $farm);
    }

    public function storeBatch(StorePreparationBatchRequest $request, Farm $farm, FarmBlock $block): RedirectResponse
    {
        $this->matchFarm($farm, $block);
        Gate::authorize('create', [PlantingCycle::class, $block]);
        $result = $this->blocks->prepareBatch($block, $request->user(), $this->team($farm), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Subblok/batch dibuat. '.\App\Support\TaskGenerationFeedback::message($result['generation'])]);

        return $this->returnToFarm($request, $farm) ?? to_route('blocks.index', $farm);
    }

    public function updateBatch(StorePreparationBatchRequest $request, Farm $farm, FarmBlock $block, PlantingCycle $batch): RedirectResponse
    {
        $this->matchFarm($farm, $block);
        abort_unless($batch->farm_id === $farm->id && $batch->block_id === $block->id, 404);
        Gate::authorize('update', $batch);
        $this->blocks->updateBatch($block, $batch, $request->user(), $this->team($farm), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perubahan sub blok disimpan.']);

        return $this->returnToFarm($request, $farm) ?? to_route('blocks.index', $farm);
    }

    public function closeHarvest(Request $request, Farm $farm, FarmBlock $block, PlantingCycle $batch, HarvestClosureService $harvest): RedirectResponse
    {
        $this->matchFarm($farm, $block);
        abort_unless($batch->farm_id === $farm->id && $batch->block_id === $block->id, 404);
        Gate::authorize('configure', $batch);
        $harvest->close($request->user(), $this->team($farm), $batch);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Panen ditutup dan subblok selesai.']);

        return $this->returnToFarm($request, $farm) ?? to_route('blocks.index', $farm);
    }

    private function returnToFarm(Request $request, Farm $farm): ?RedirectResponse
    {
        // A fixed destination retains dialog context without accepting redirect URLs.
        return match ($request->input('return_to')) {
            'farm' => to_route('farm-management.show', $farm),
            'blocks' => to_route('blocks.index', $farm),
            default => null,
        };
    }

    private function matchFarm(Farm $farm, FarmBlock $block): void
    {
        abort_unless($block->farm_id === $farm->id, 404);
    }

    private function team(Farm $farm): Team
    {
        $team = $farm->team;
        abort_unless($team instanceof Team, 403);

        return $team;
    }
}
