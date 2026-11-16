<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Petugas\StorePetugasSubblokRequest;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Services\FarmBlockService;
use App\Support\TaskGenerationFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PetugasSubblokController extends Controller
{
    public function __invoke(StorePetugasSubblokRequest $request, FarmBlock $block, FarmBlockService $blocks): RedirectResponse
    {
        $farm = $request->user()->assignedFarm;
        abort_unless($farm instanceof Farm, 403, 'Anda belum memiliki penugasan kebun.');
        Gate::authorize('view', $farm);
        $team = $farm->team;
        abort_unless($team instanceof Team, 403);
        abort_unless($block->farm_id === $farm->id, 403);
        Gate::authorize('create', [PlantingCycle::class, $block]);

        $result = $blocks->prepareBatch($block, $request->user(), $team, $request->validated());
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Subblok dibuat. '.TaskGenerationFeedback::message($result['generation']),
        ]);

        return $request->validated('return_to') === 'penanaman'
            ? to_route('petugas.planting.index')
            : to_route('petugas.kebun', ['block' => $block->id]);
    }
}
