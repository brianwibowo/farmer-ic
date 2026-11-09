<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Petugas\StoreVoluntaryActivityRequest;
use App\Models\ActivityType;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Services\Activity\ActivitySequenceService;
use App\Services\Activity\PlantingActivityService;
use App\Services\Activity\VoluntaryActivityService;
use App\Support\VoluntaryActivityFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PetugasVoluntaryActivityController extends Controller
{
    public function create(Request $request, ActivityType $type, PlantingActivityService $planting, ActivitySequenceService $sequence): Response
    {
        $farm = $this->farm($request);
        Gate::authorize('recordVoluntary', [FarmActivity::class, $farm, $type]);

        return Inertia::render('petugas/VoluntaryCreate', [
            'farm' => $farm->only(['id', 'name']), 'type' => $type->only(['id', 'code', 'name']),
            'fields' => VoluntaryActivityFields::forCode($type->code),
            'blocks' => FarmBlock::visibleTo($request->user(), $farm->team)->where('farm_id', $farm->id)->where('is_active', true)->orderBy('code')->get()->map(fn ($block) => [...$block->only(['id', 'code', 'name']), 'blocked_reason' => $sequence->blockReason($request->user(), $block, $type->code)])->all(),
            'cycles' => $planting->cycles($request->user(), $farm)->filter(fn ($cycle) => in_array($cycle->status, ['planned', 'active'], true))
                ->map(fn ($cycle) => [...$planting->context($request->user(), $cycle), 'blocked_reason' => $sequence->blockedReason($request->user(), $cycle, $type->code)])->values()->all(),
            'matchingTasks' => PlantingCycleTask::visibleTo($request->user(), $farm->team)
                ->whereHas('plantingCycle', fn ($q) => $q->visibleTo($request->user(), $farm->team)->where('farm_id', $farm->id))
                ->whereIn('status', ['pending', 'in_progress'])
                ->where(fn ($q) => $type->code === 'harvest' ? $q->where('activity_category', 'harvest') : $q->where('activity_subcategory', $type->code))
                ->get(['id', 'planting_cycle_id', 'name', 'scheduled_date']),
            'today' => now('Asia/Jakarta')->toDateString(),
        ]);
    }

    public function store(StoreVoluntaryActivityRequest $request, ActivityType $type, VoluntaryActivityService $activities): RedirectResponse
    {
        $farm = $this->farm($request);
        Gate::authorize('recordVoluntary', [FarmActivity::class, $farm, $type]);
        $data = $request->validated();
        $cycle = empty($data['cycle_id']) ? null : PlantingCycle::query()->whereKey($data['cycle_id'])->firstOrFail();
        if ($cycle !== null) {
            Gate::authorize('view', $cycle);
            abort_unless($cycle->farm_id === $farm->id, 403);
        }
        $block = empty($data['block_id']) ? null : FarmBlock::query()->whereKey($data['block_id'])->firstOrFail();
        if ($block !== null) {
            Gate::authorize('view', $block);
            abort_unless($block->farm_id === $farm->id, 403);
        }
        $activity = $activities->record($request->user(), $farm, $type, $cycle, $data, $block);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan aktivitas mandiri tersimpan.']);

        return to_route('petugas.laporan.show', $activity);
    }

    private function farm(Request $request): Farm
    {
        $farm = $request->user()->assignedFarm;
        abort_unless($farm instanceof Farm, 403);
        Gate::authorize('view', $farm);

        return $farm;
    }
}
