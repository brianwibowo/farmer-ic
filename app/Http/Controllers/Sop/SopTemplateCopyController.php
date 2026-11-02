<?php

namespace App\Http\Controllers\Sop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sop\CopySopTemplateRequest;
use App\Models\Farm;
use App\Models\SopTemplate;
use App\Models\SopTemplateVersion;
use App\Models\Team;
use App\Services\SopTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SopTemplateCopyController extends Controller
{
    public function __construct(private readonly SopTemplateService $sops) {}

    public function store(CopySopTemplateRequest $request, SopTemplate $sopTemplate, SopTemplateVersion $version): RedirectResponse
    {
        abort_unless($version->sop_template_id === $sopTemplate->id, 404);
        Gate::authorize('copy', $sopTemplate);

        $team = $request->user()->currentTeam;
        abort_unless($team instanceof Team, 403);

        $ids = $request->validated('target_farm_ids');
        $farms = Farm::query()
            ->visibleTo($request->user(), $team)
            ->where('team_id', $team->id)
            ->whereKey($ids)
            ->orderBy('name')
            ->get();

        if ($farms->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'target_farm_ids' => 'Satu atau lebih kebun tujuan tidak ditemukan di tenant aktif.',
            ]);
        }

        foreach ($farms as $farm) {
            Gate::authorize('create', [SopTemplate::class, $farm]);
        }

        $created = $this->sops->copyToFarms($request->user(), $version, $farms->all());
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Draft SOP berhasil dibuat di '.count($created).' kebun.',
        ]);

        return to_route('sops.show', $sopTemplate);
    }
}
