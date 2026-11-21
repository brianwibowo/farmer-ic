<?php

namespace App\Services\Notifications;

use App\Models\Farm;
use App\Models\IncidentTicket;
use App\Models\Notification;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Policies\NotificationPolicy;
use App\Support\RolePermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class NotificationInboxService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Notification>
     */
    public function paginate(User $actor, array $filters): LengthAwarePaginator
    {
        $query = $this->query($actor);
        if (($filters['status'] ?? 'unread') === 'unread') {
            $query->where('is_read', false);
        } elseif (($filters['status'] ?? null) === 'read') {
            $query->where('is_read', true);
        }

        if (($filters['category'] ?? 'all') !== 'all') {
            $query->where('type', $filters['category']);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->appends($filters);
    }

    /** @return list<array{id: int, name: string}> */
    public function tenantOptions(User $actor): array
    {
        $permissions = app(RolePermissions::class);

        return $permissions->withMembershipSnapshot($actor, function () use ($actor, $permissions): array {
            $query = null;
            foreach ($permissions->teamMemberships($actor)->where('is_personal', false) as $team) {
                $candidate = Team::visibleTo($actor, $team)->whereKey($team->id)->select(['teams.id', 'teams.name']);
                $query = $query === null ? $candidate : $query->union($candidate);
            }

            return array_values($query?->orderBy('name')->orderBy('id')->get()
                ->map(fn (Team $team): array => ['id' => $team->id, 'name' => $team->name])->all() ?? []);
        });
    }

    public function messageFor(User $actor, Notification $notification, bool $tenantBadge = false): string
    {
        $message = $notification->message ?? '';
        if ($notification->type === 'weekly_report') {
            return $tenantBadge || ! app(NotificationPolicy::class)->showsTenantContext($actor)
                ? preg_replace('/\ATenant:[^\r\n]*(?:\r?\n|$)/u', '', $message) ?? $message
                : $message;
        }

        return $message;
    }

    public function unreadCount(User $actor): int
    {
        return $this->query($actor)->where('is_read', false)->count();
    }

    /** @return Builder<Notification> */
    private function query(User $actor): Builder
    {
        $farm = $actor->assignedFarm;
        $team = $farm instanceof Farm ? $farm->team : null;

        return Notification::visibleTo($actor)->where('user_id', $actor->id)
            ->where(function (Builder $query) use ($actor, $farm, $team): void {
                $query->whereIn('type', ['weekly_report', 'task_reminder', 'sop_deviation', 'incident_report']);
                if ($farm instanceof Farm && $team instanceof Team) {
                    $cycles = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)->selectRaw('planting_cycles.id::text');
                    $tickets = IncidentTicket::visibleTo($actor, $team)->where('farm_id', $farm->id)->selectRaw('incident_tickets.id::text');
                    $query->orWhere(fn (Builder $notice) => $notice->where('type', 'subblock_cancelled')
                        ->whereIn(DB::raw("notifications.content->>'cycle_id'"), $cycles)
                        ->whereIn(DB::raw("notifications.content->>'ticket_id'"), $tickets));
                }
            });
    }

    public function markAllRead(User $actor): void
    {
        $this->query($actor)->where('is_read', false)->update(['is_read' => true, 'read_at' => now()]);
    }

    public function markRead(Notification $notification): void
    {
        if (! $notification->is_read) {
            $notification->forceFill(['is_read' => true, 'read_at' => now()])->save();
        }
    }
}
