<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Petugas\StorePetugasSubblokRequest;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Services\Activity\ActivitySequenceService;
use App\Services\FarmBlockService;
use App\Support\TaskGenerationFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PetugasSubblokController extends Controller
{
    public function __invoke(StorePetugasSubblokRequest $request, FarmBlock $block, FarmBlockService $blocks, ActivitySequenceService $sequence): RedirectResponse
    {
        $farm = $request->user()->assignedFarm;
        abort_unless($farm instanceof Farm, 403, 'Anda belum memiliki penugasan lahan.');
        Gate::authorize('view', $farm);
        $team = $farm->team;
        abort_unless($team instanceof Team, 403);
        abort_unless($block->farm_id === $farm->id, 403);
        Gate::authorize('create', [PlantingCycle::class, $block]);

        $result = $blocks->prepareBatch($block, $request->user(), $team, $request->validated());
        $workDate = $request->validated('work_date');

        if ($request->validated('return_to') === 'penanaman') {
            $batch = $result['batch'];
            if ($this->shouldRedirectToPlantingForm($request->user(), $team, $block, $batch, $sequence)) {
                Inertia::flash('toast', [
                    'type' => 'success',
                    'message' => 'Subblok dibuat. Silakan catat realisasi penanaman.',
                ]);

                return to_route('petugas.planting.show', [
                    'cycle' => $batch->id,
                    ...($workDate !== null ? ['work_date' => $workDate] : []),
                ]);
            }

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Subblok dibuat. '.TaskGenerationFeedback::message($result['generation']),
            ]);

            return to_route('petugas.planting.index', $workDate !== null ? ['work_date' => $workDate] : []);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Subblok dibuat. '.TaskGenerationFeedback::message($result['generation']),
        ]);

        return to_route('petugas.kebun', ['block' => $block->id]);
    }

    private function shouldRedirectToPlantingForm(User $actor, Team $team, FarmBlock $block, PlantingCycle $batch, ActivitySequenceService $sequence): bool
    {
        $plannedAt = $batch->planned_planting_at?->toDateString();
        if ($plannedAt !== null && $plannedAt > now('Asia/Jakarta')->toDateString()) {
            return false;
        }
        if (! Gate::forUser($actor)->allows('recordPlanting', [FarmActivity::class, $batch])) {
            return false;
        }
        if ($sequence->blockedReason($actor, $batch, 'planting') !== null) {
            return false;
        }
        $commodityId = $block->getAttribute('commodity_id');

        return $commodityId !== null
            && CropVariety::visibleTo($actor, $team)->where('commodity_id', $commodityId)->exists();
    }
}
