<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Petugas\StorePlantingActivityRequest;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Services\Activity\ActivityFormLimits;
use App\Services\Activity\ActivitySequenceService;
use App\Services\Activity\PlantingActivityService;
use App\Services\FarmBlockService;
use App\Services\PlantingCycleTaskService;
use App\Support\ActivityInputLimits;
use App\Support\TaskGenerationFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PetugasPlantingController extends Controller
{
    public function index(Request $request, PlantingActivityService $planting, ActivitySequenceService $sequence, FarmBlockService $blockService): Response
    {
        $farm = $request->user()->assignedFarm;
        abort_unless($farm instanceof Farm, 403, 'Anda belum memiliki penugasan lahan.');
        Gate::authorize('view', $farm);
        Gate::authorize('viewAny', [PlantingCycle::class, $farm->team]);
        $blocks = FarmBlock::visibleTo($request->user(), $farm->team)
            ->where('farm_id', $farm->id)
            ->orderByDesc('is_active')
            ->orderBy('code')
            ->orderBy('id')
            ->get();
        $capacities = $blockService->capacitiesForBlocks($farm, $request->user(), $farm->team, $blocks);

        return Inertia::render('petugas/PlantingIndex', [
            'farm' => $farm->only(['id', 'name']),
            'blocks' => $blocks->map(fn (FarmBlock $block): array => [
                ...$block->only(['id', 'code', 'name', 'area_ha', 'is_active']),
                'available_ha' => $capacities[$block->id]['available_ha'] ?? '0.0000',
                'can_create_batch' => Gate::allows('create', [PlantingCycle::class, $block]),
            ])->values()->all(),
            'cycles' => $planting->cycles($request->user(), $farm)->map(fn (PlantingCycle $cycle) => [
                ...$planting->context($request->user(), $cycle),
                'canRecord' => in_array($cycle->status, ['planned', 'active'], true) && Gate::allows('recordPlanting', [FarmActivity::class, $cycle]),
                'canSync' => in_array($cycle->status, ['planned', 'active'], true) && Gate::allows('synchronize', [PlantingCycleTask::class, $cycle]),
                'blockedReason' => $sequence->blockedReason($request->user(), $cycle, 'planting'),
            ])->all(),
        ]);
    }

    public function show(Request $request, PlantingCycle $cycle, PlantingActivityService $planting, ActivitySequenceService $sequence, ActivityFormLimits $limits): Response
    {
        $this->authorizeCycle($request, $cycle);

        return Inertia::render('petugas/PlantingCreate', [
            ...$planting->formData($request->user(), $cycle),
            'areaLimitM2' => $limits->plantingArea($request->user(), $cycle),
            'numericLimits' => ActivityInputLimits::planting(),
            'farm' => $cycle->farm->only(['id', 'name']),
            'canRecord' => in_array($cycle->status, ['planned', 'active'], true) && Gate::allows('recordPlanting', [FarmActivity::class, $cycle]),
            'blockedReason' => $sequence->blockedReason($request->user(), $cycle, 'planting'),
            'today' => now('Asia/Jakarta')->toDateString(),
            'workDate' => $request->validate(['work_date' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()]])['work_date'] ?? now('Asia/Jakarta')->toDateString(),
        ]);
    }

    public function store(StorePlantingActivityRequest $request, PlantingCycle $cycle, PlantingActivityService $planting): RedirectResponse
    {
        $this->authorizeCycle($request, $cycle);
        Gate::authorize('recordPlanting', [FarmActivity::class, $cycle]);
        Gate::authorize('synchronize', [PlantingCycleTask::class, $cycle]);
        $result = $planting->record($request->user(), $cycle, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Realisasi penanaman tersimpan. '.TaskGenerationFeedback::message($result)]);

        return to_route('petugas.planting.show', $cycle);
    }

    public function synchronize(Request $request, PlantingCycle $cycle, PlantingCycleTaskService $tasks): RedirectResponse
    {
        $this->authorizeCycle($request, $cycle);
        Gate::authorize('synchronize', [PlantingCycleTask::class, $cycle]);
        $result = $tasks->generateForSubblok($cycle, $request->user());
        Inertia::flash('toast', ['type' => in_array($result['status'], ['generated', 'already_generated'], true) ? 'success' : 'info', 'message' => TaskGenerationFeedback::message($result)]);

        return to_route('petugas.planting.index');
    }

    private function authorizeCycle(Request $request, PlantingCycle $cycle): void
    {
        Gate::authorize('view', $cycle);
        abort_unless($cycle->farm_id === $request->user()->assigned_farm_id && $cycle->getAttribute('block_id') !== null, 403);
    }
}
