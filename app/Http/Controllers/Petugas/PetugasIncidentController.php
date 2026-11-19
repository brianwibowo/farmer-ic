<?php

namespace App\Http\Controllers\Petugas;

use App\Enums\IncidentSeverity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\ReopenIncidentTicketRequest;
use App\Models\Farm;
use App\Models\IncidentTicket;
use App\Services\IncidentTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PetugasIncidentController extends Controller
{
    public function __construct(private readonly IncidentTicketService $tickets) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'severity' => ['nullable', Rule::in(IncidentSeverity::values())],
            'view' => ['nullable', Rule::in(['unresolved', 'open', 'in_progress', 'resolved', 'all'])],
        ]);

        $filters = array_replace([
            'search' => '',
            'severity' => '',
            'view' => 'unresolved',
        ], $validated);

        $farm = $request->user()->assignedFarm;
        if (! $farm instanceof Farm) {
            return Inertia::render('petugas/Incidents', [
                'farm' => null,
                'tickets' => [],
                'summary' => ['total' => 0, 'open' => 0, 'in_progress' => 0, 'resolved' => 0, 'emergency_open' => 0],
                'filters' => $filters,
            ]);
        }

        Gate::authorize('view', $farm);
        Gate::authorize('viewAny', [IncidentTicket::class, $farm->team]);

        return Inertia::render('petugas/Incidents', [
            ...$this->tickets->petugasIndex($request->user(), $farm, $filters),
            'farm' => $farm->only(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, IncidentTicket $incidentTicket): Response
    {
        Gate::authorize('view', $incidentTicket);
        abort_unless($incidentTicket->farm_id === $request->user()->assigned_farm_id, 403);

        return Inertia::render('petugas/IncidentShow', [
            'ticket' => $this->tickets->detail($request->user(), $incidentTicket, 'petugas'),
        ]);
    }

    public function reopen(ReopenIncidentTicketRequest $request, IncidentTicket $incidentTicket): RedirectResponse
    {
        Gate::authorize('view', $incidentTicket);
        abort_unless($incidentTicket->farm_id === $request->user()->assigned_farm_id, 403);
        Gate::authorize('reopen', $incidentTicket);

        $this->tickets->reopen($incidentTicket, $request->user(), $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tiket insiden dibuka kembali.']);

        return to_route('petugas.incidents.show', $incidentTicket);
    }
}
