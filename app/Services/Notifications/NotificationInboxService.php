<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use App\Policies\NotificationPolicy;
use App\Support\RolePermissions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class NotificationInboxService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Notification>
     */
    public function paginate(User $actor, array $filters): LengthAwarePaginator
    {
        $query = $this->query($actor);
        if ($filters['status'] === 'unread') {
            $query->where('is_read', false);
        } else {
            $from = CarbonImmutable::parse($filters['date_from'], config('app.timezone'))->startOfDay();
            $until = CarbonImmutable::parse($filters['date_to'], config('app.timezone'))->addDay()->startOfDay();
            $query->where('created_at', '>=', $from->toIso8601String())->where('created_at', '<', $until->toIso8601String());
            if (! empty($filters['team_id'])) {
                $query->where('team_id', $filters['team_id']);
            }
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
        if ($notification->type !== 'daily_report') {
            return $message;
        }
        $showTenant = app(NotificationPolicy::class)->showsTenantContext($actor);
        if (! $showTenant || $tenantBadge) {
            // Only remove the known header, never a tenant-like line inside an activity note.
            $message = preg_replace('/\A((?:Report Harian Kebun dari Petugas[^\r\n]*\r?\n)?)Tenant:[^\r\n]*(?:\r?\n|$)/u', '$1', $message) ?? $message;
        }
        if (! $showTenant) {
            $message = str_replace('Satu rekap seluruh kebun dalam tenant, per penerima dan tanggal.',
                'Satu rekap seluruh kebun, per penerima dan tanggal.', $message);
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
        return Notification::visibleTo($actor)->where('user_id', $actor->id)
            ->whereIn('type', ['daily_report', 'harvest_reminder']);
    }

    public function markRead(Notification $notification): void
    {
        if (! $notification->is_read) {
            $notification->forceFill(['is_read' => true, 'read_at' => now()])->save();
        }
    }
}
