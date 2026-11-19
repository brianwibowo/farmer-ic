<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IncidentSeverity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListIncidentsRequest;
use App\Http\Requests\Admin\ResolveIncidentTicketRequest;
use App\Http\Requests\Admin\RespondIncidentTicketRequest;
use App\Http\Requests\Incident\ReopenIncidentTicketRequest;
use App\Models\IncidentTicket;
use App\Models\Team;
use App\Services\IncidentTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IncidentController extends Controller
{
    public function __construct(private readonly IncidentTicketService $tickets) {}

    public function index(ListIncidentsRequest $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam;
        abort_unless($team instanceof Team, 403);
        Gate::authorize('viewAny', [IncidentTicket::class, $team]);

        $filters = array_replace([
            'search' => '',
            'farm_id' => '',
            'severity' => '',
            'view' => 'unresolved',
            'per_page' => 15,
        ], $request->validated());

        return Inertia::render('incidents/Index', [
            ...$this->tickets->adminIndex($user, $team, $filters),
            'filters' => $filters,
            'severities' => collect(IncidentSeverity::cases())->map(fn (IncidentSeverity $severity): array => [
                'value' => $severity->value,
                'label' => $severity->label(),
            ])->all(),
        ]);
    }

    public function show(Request $request, IncidentTicket $incidentTicket): Response
    {
        Gate::authorize('view', $incidentTicket);

        return Inertia::render('incidents/Show', [
            'ticket' => $this->tickets->detail($request->user(), $incidentTicket, 'admin'),
            'severities' => collect(IncidentSeverity::cases())->map(fn (IncidentSeverity $severity): array => [
                'value' => $severity->value,
                'label' => $severity->label(),
            ])->all(),
        ]);
    }

    public function respond(RespondIncidentTicketRequest $request, IncidentTicket $incidentTicket): RedirectResponse
    {
        Gate::authorize('respond', $incidentTicket);
        $this->tickets->respond($incidentTicket, $request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tanggapan insiden disimpan.']);

        return back();
    }

    public function resolve(ResolveIncidentTicketRequest $request, IncidentTicket $incidentTicket): RedirectResponse
    {
        Gate::authorize('resolveDirectly', $incidentTicket);
        $this->tickets->resolveDirectly($incidentTicket, $request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tiket insiden ditandai selesai.']);

        return back();
    }

    public function reopen(ReopenIncidentTicketRequest $request, IncidentTicket $incidentTicket): RedirectResponse
    {
        Gate::authorize('reopen', $incidentTicket);
        $this->tickets->reopen($incidentTicket, $request->user(), $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tiket insiden dibuka kembali.']);

        return back();
    }
}
