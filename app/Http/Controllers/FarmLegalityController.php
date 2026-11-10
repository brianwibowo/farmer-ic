<?php

namespace App\Http\Controllers;

use App\Http\Requests\Farms\SaveFarmLegalityRequest;
use App\Models\Farm;
use App\Models\FarmLegality;
use App\Models\FarmLegalityDocument;
use App\Models\Team;
use App\Services\FarmLegalityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FarmLegalityController extends Controller
{
    public function __construct(private readonly FarmLegalityService $legalities) {}

    public function show(Request $request, Farm $farm): Response
    {
        Gate::authorize('view', $farm);
        $team = $this->team($request, $farm);
        Gate::authorize('viewAny', [FarmLegality::class, $team]);
        $farm = $this->legalities->visibleFarm($request->user(), $team, $farm);
        $legality = $this->legalities->record($request->user(), $team, $farm);

        return Inertia::render('farms/Legality', [
            'farm' => $farm->only(['id', 'name', 'land_tenure', 'land_tenure_party', 'total_area_ha', 'city', 'district', 'province']),
            'backUrl' => route('farm-management.show', $farm, false),
            'legality' => $this->legalities->payload($request->user(), $team, $farm, $legality),
            'legalityPermissions' => [
                'create' => $legality === null && Gate::allows('create', [FarmLegality::class, $farm]),
                'update' => $legality !== null && Gate::allows('update', $legality),
                'delete' => $legality !== null && Gate::allows('delete', $legality),
                'upload' => Gate::allows('create', [FarmLegalityDocument::class, $farm]),
                'removeDocuments' => $legality !== null && $this->legalities->documents($request->user(), $team, $legality)->every(fn (FarmLegalityDocument $document): bool => Gate::allows('delete', $document)),
            ],
            'legalityUrls' => [
                'store' => route('farm-management.legality.store', $farm, false),
                'update' => $legality ? route('farm-management.legality.update', [$farm, $legality], false) : null,
                'destroy' => $legality ? route('farm-management.legality.destroy', [$farm, $legality], false) : null,
            ],
            'legalityLimits' => FarmLegalityService::limits(),
        ]);
    }

    public function store(SaveFarmLegalityRequest $request, Farm $farm): RedirectResponse
    {
        Gate::authorize('create', [FarmLegality::class, $farm]);
        $team = $this->team($request, $farm);
        if ($request->hasFile('documents')) {
            Gate::authorize('create', [FarmLegalityDocument::class, $farm]);
        }
        $this->legalities->save($request->user(), $team, $farm, null, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Legalitas kebun berhasil ditambahkan.']);

        return to_route('farm-management.legality.show', $farm);
    }

    public function update(SaveFarmLegalityRequest $request, Farm $farm, FarmLegality $legality): RedirectResponse
    {
        Gate::authorize('update', $legality);
        $team = $this->team($request, $farm);
        abort_unless($legality->farm_id === $farm->id, 404);
        if ($request->hasFile('documents')) {
            Gate::authorize('create', [FarmLegalityDocument::class, $legality]);
        }
        $remove = $request->validated('remove_document_ids', []);
        foreach ($this->legalities->documents($request->user(), $team, $legality)->whereIn('id', $remove) as $document) {
            Gate::authorize('delete', $document);
        }
        $this->legalities->save($request->user(), $team, $farm, $legality, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perubahan legalitas berhasil disimpan.']);

        return to_route('farm-management.legality.show', $farm);
    }

    public function destroy(Request $request, Farm $farm, FarmLegality $legality): RedirectResponse
    {
        Gate::authorize('delete', $legality);
        $team = $this->team($request, $farm);
        abort_unless($legality->farm_id === $farm->id, 404);
        foreach ($this->legalities->documents($request->user(), $team, $legality) as $document) {
            Gate::authorize('delete', $document);
        }
        $this->legalities->delete($request->user(), $team, $farm, $legality);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Legalitas dan draft PDF berhasil dihapus.']);

        return to_route('farm-management.legality.show', $farm);
    }

    private function team(Request $request, Farm $farm): Team
    {
        $team = $request->user()->currentTeam;
        abort_unless($team instanceof Team && (int) $farm->team_id === (int) $team->id, 403);

        return $team;
    }
}
