<?php

namespace App\Services;

use App\Enums\IncidentSeverity;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\IncidentTicket;
use App\Models\Notification;
use App\Models\PlantingCycle;
use App\Models\User;
use App\Support\SubblockLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubblockCancellationService
{
    /** Caller authorizes cancelSubblock on the persisted incident ticket. */
    public function cancel(IncidentTicket $ticket, User $actor, string $reason): void
    {
        DB::transaction(function () use ($ticket, $actor, $reason): void {
            $team = $ticket->farm?->team;
            if ($team === null || $ticket->planting_cycle_id === null) {
                throw ValidationException::withMessages(['status' => 'Tiket tidak terhubung ke subblok.']);
            }

            // All operational writers lock farm before cycle and ticket.
            $farm = Farm::visibleTo($actor, $team)->whereKey($ticket->farm_id)->lockForUpdate()->firstOrFail();
            $cycle = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)
                ->whereKey($ticket->planting_cycle_id)->lockForUpdate()->firstOrFail();
            if ($cycle->block_id === null || ! in_array($cycle->status, SubblockLifecycle::RUNNING, true)) {
                throw ValidationException::withMessages(['status' => 'Subblok sudah ditutup atau tidak tersedia.']);
            }

            $tickets = IncidentTicket::visibleTo($actor, $team)->where('farm_id', $farm->id)
                ->where('planting_cycle_id', $cycle->id)->whereIn('status', ['open', 'in_progress'])
                ->orderBy('id')->lockForUpdate()->get();
            $trigger = $tickets->firstWhere('id', $ticket->id);
            if (! $trigger instanceof IncidentTicket || $trigger->severity !== IncidentSeverity::Emergency->value) {
                throw ValidationException::withMessages(['status' => 'Tiket harus belum selesai dan berstatus Darurat.']);
            }

            $activities = FarmActivity::visibleTo($actor, $team)->where('farm_id', $farm->id)
                ->where('planting_cycle_id', $cycle->id)->whereIn('id', $tickets->pluck('activity_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($activities->count() !== $tickets->count()) {
                throw ValidationException::withMessages(['status' => 'Laporan insiden terkait tidak tersedia.']);
            }

            $now = now();
            $reason = trim($reason);
            foreach ($tickets as $related) {
                $related->forceFill([
                    'status' => 'resolved', 'resolution_note' => $reason,
                    'resolved_by' => $actor->id, 'resolved_at' => $now, 'resolved_activity_id' => null,
                    'reopen_reason' => null, 'reopened_by' => null, 'reopened_at' => null,
                    'cancellation_role' => $related->id === $trigger->id ? 'trigger' : 'related',
                ])->save();
                $activities[$related->activity_id]->forceFill(['status' => 'resolved', 'action_taken' => $reason])->save();
            }

            $cycle->forceFill([
                'status' => 'cancelled', 'cancellation_reason' => $reason,
                'cancelled_at' => $now, 'cancelled_by' => $actor->id,
            ])->save();

            $recipients = User::visibleTo($actor, $team)->where('is_active', true)
                ->where('assigned_farm_id', $farm->id)
                ->whereHas('role', fn ($roles) => $roles->where('name', 'petugas'))
                ->whereHas('teams', fn ($teams) => $teams->whereKey($team->id))->get();
            foreach ($recipients as $recipient) {
                if (! Gate::forUser($recipient)->allows('viewAny', [Notification::class, $team])
                    || ! Gate::forUser($recipient)->allows('view', $cycle)
                    || ! Gate::forUser($recipient)->allows('view', $trigger)) {
                    continue;
                }
                $notification = new Notification;
                $notification->forceFill([
                    'id' => (string) Str::uuid(), 'user_id' => $recipient->id, 'team_id' => $team->id,
                    'type' => 'subblock_cancelled', 'title' => 'Subblok dibatalkan',
                    'message' => 'Subblok pada lahan penugasan Anda dibatalkan. Lihat detail insiden untuk informasi.',
                    'content' => ['version' => 1, 'kind' => 'subblock_cancelled', 'cycle_id' => $cycle->id, 'ticket_id' => $trigger->id],
                    'is_read' => false,
                ])->save();
            }
        });
    }
}
