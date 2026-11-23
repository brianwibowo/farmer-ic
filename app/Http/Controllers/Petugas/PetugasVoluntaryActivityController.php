<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Petugas\StoreVoluntaryActivityRequest;
use App\Models\ActivityType;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\IncidentTicket;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Services\Activity\ActivityFormLimits;
use App\Services\Activity\ActivitySequenceService;
use App\Services\Activity\PlantingActivityService;
use App\Services\Activity\VoluntaryActivityCategoryService;
use App\Services\Activity\VoluntaryActivityService;
use App\Services\IncidentTicketService;
use App\Support\ActivityInputLimits;
use App\Support\SubblockLifecycle;
use App\Support\VoluntaryActivityFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PetugasVoluntaryActivityController extends Controller
{
    public function create(
        Request $request,
        ActivityType $type,
        PlantingActivityService $planting,
        ActivitySequenceService $sequence,
        ActivityFormLimits $limits,
        IncidentTicketService $tickets,
    ): Response|RedirectResponse {
        $farm = $this->farm($request);
        Gate::authorize('recordVoluntary', [FarmActivity::class, $farm, $type]);
        abort_if(VoluntaryActivityCategoryService::requiresTask($type->code), 404);

        $incidentTicket = null;
        $ticketId = $request->query('incident_ticket_id');
        if (is_string($ticketId) && $ticketId !== '') {
            abort_unless(Str::isUuid($ticketId), 404);
            $ticket = IncidentTicket::visibleTo($request->user(), $farm->team)
                ->where('farm_id', $farm->id)
                ->whereKey($ticketId)
                ->firstOrFail();
            Gate::authorize('resolveByFollowUp', $ticket);

            if ($ticket->status === 'resolved'
                || ! in_array($type->code, $tickets->compatibleCategoryCodes($request->user(), $farm, $ticket), true)) {
                Inertia::flash('toast', ['type' => 'error', 'message' => 'Tiket insiden ini tidak dapat ditindaklanjuti dengan kategori atau status saat ini.']);

                return to_route('petugas.incidents.show', $ticket);
            }

            $incidentTicket = $tickets->followUpContext($request->user(), $farm, $ticket);
        }

        $blocks = FarmBlock::visibleTo($request->user(), $farm->team)
            ->where('farm_id', $farm->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $blocksById = $blocks->keyBy('id');

        return Inertia::render('petugas/VoluntaryCreate', [
            'farm' => $farm->only(['id', 'name']), 'type' => $type->only(['id', 'code', 'name']),
            'fields' => VoluntaryActivityFields::forCode($type->code),
            'numericLimits' => ActivityInputLimits::common(),
            'blocks' => $blocks->map(fn ($block) => [...$block->only(['id', 'name']), 'area_limit_m2' => $limits->voluntaryBlockArea($block), 'blocked_reason' => null])->all(),
            'cycles' => $planting->cycles($request->user(), $farm)->filter(fn ($cycle) => in_array($cycle->status, ['planned', 'active'], true))
                ->map(fn ($cycle) => [...$planting->context($request->user(), $cycle), 'area_limit_m2' => $limits->voluntaryCycleArea($cycle, $cycle->block_id !== null ? $blocksById->get($cycle->block_id) : null, $farm), 'blocked_reason' => $sequence->blockedReason($request->user(), $cycle, $type->code)])->values()->all(),
            'matchingTasks' => PlantingCycleTask::visibleTo($request->user(), $farm->team)
                ->whereHas('plantingCycle', fn ($q) => $q->visibleTo($request->user(), $farm->team)
                    ->where('farm_id', $farm->id)->whereIn('status', SubblockLifecycle::RUNNING))
                ->whereIn('status', ['pending', 'in_progress'])
                ->where('activity_category', $type->code)
                ->get(['id', 'planting_cycle_id', 'name', 'scheduled_date']),
            'today' => now('Asia/Jakarta')->toDateString(),
            'incidentTicket' => $incidentTicket,
        ]);
    }

    public function store(StoreVoluntaryActivityRequest $request, ActivityType $type, VoluntaryActivityService $activities): RedirectResponse
    {
        $farm = $this->farm($request);
        Gate::authorize('recordVoluntary', [FarmActivity::class, $farm, $type]);
        abort_if(VoluntaryActivityCategoryService::requiresTask($type->code), 404);
        $data = $request->validated();
        if (! empty($data['incident_ticket_id'])) {
            $ticket = IncidentTicket::visibleTo($request->user(), $farm->team)
                ->where('farm_id', $farm->id)
                ->whereKey($data['incident_ticket_id'])
                ->firstOrFail();
            Gate::authorize('resolveByFollowUp', $ticket);
        }
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
        if (! empty($data['incident_ticket_id'])) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Tindak lanjut tercatat dan tiket insiden ditandai selesai.']);

            return to_route('petugas.incidents.show', $data['incident_ticket_id']);
        }
        if ($type->code === 'incident') {
            $createdTicket = $activity->incidentTicket;
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Tiket laporan insiden tersimpan.']);

            return $createdTicket instanceof IncidentTicket
                ? to_route('petugas.incidents.show', $createdTicket)
                : to_route('petugas.laporan.show', $activity);
        }
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
