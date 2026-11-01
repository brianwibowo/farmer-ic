<?php

namespace App\Services\Notifications;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Notifications\DailyReportNotification;
use App\Notifications\HarvestReminderNotification;
use App\Policies\NotificationPolicy;
use App\Support\RolePermissions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class NotificationService
{
    public function dailyReports(): int
    {
        $now = $this->now();
        if (! config('operational_notifications.enabled')) {
            return 0;
        }

        $count = 0;
        $permissions = app(RolePermissions::class);
        $recipients = User::query()->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['owner', 'admin']))
            ->with(['role', 'teams'])->lazyById(100);
        foreach ($recipients as $recipient) {
            $count += $permissions->withMembershipSnapshot($recipient, function () use ($recipient, $now): int {
                $reports = 0;
                foreach ($recipient->teams->where('is_personal', false) as $team) {
                    if (! Gate::forUser($recipient)->allows('notifications.dailyReport', $team)) {
                        continue;
                    }
                    Gate::forUser($recipient)->authorize('viewAny', [Farm::class, $team]);
                    Gate::forUser($recipient)->authorize('viewAny', [FarmActivity::class, $team]);
                    $farms = Farm::visibleTo($recipient, $team)->orderBy('name')->orderBy('id')->get();
                    if ($farms->isEmpty()) {
                        continue;
                    }
                    // One scoped batch for all farms, rather than queries inside the farm loop.
                    $activities = FarmActivity::visibleTo($recipient, $team)->whereIn('farm_id', $farms->modelKeys())
                        ->whereHas('performer', fn ($users) => $users->withTrashed()
                            ->whereHas('role', fn ($roles) => $roles->where('name', 'petugas')))
                        ->where('activity_date', '>=', $now->startOfDay()->toIso8601String())
                        ->where('activity_date', '<=', $now->setTime(20, 0)->toIso8601String())
                        ->with('activityType:id,name')->orderBy('activity_date')->orderBy('id')->get();
                    // Preserve the separate visibility rules for performer names, including
                    // the existing fallback for deleted or no-longer-visible performers.
                    $performers = User::visibleTo($recipient, $team)
                        ->whereKey($activities->pluck('performed_by')->unique())->pluck('name', 'id');
                    $byFarm = $activities->groupBy('farm_id');
                    $farmReports = $farms->map(fn (Farm $farm): array => [
                        'id' => $farm->id, 'name' => $farm->name,
                        'activities' => $byFarm->get($farm->id, collect())->map(fn (FarmActivity $activity): array => [
                            'occurred_at' => $activity->activity_date->toIso8601String(),
                            'performer_name' => $performers->get($activity->performed_by),
                            'activity_type' => $activity->activityType?->name,
                            'description' => $activity->description,
                        ])->all(),
                    ])->all();
                    $this->dispatch($recipient, [
                        'kind' => 'daily_report', 'user_id' => $recipient->id,
                        'team_id' => $team->id, 'team_name' => $team->name,
                        'show_tenant' => app(NotificationPolicy::class)->showsTenantContext($recipient),
                        'event_date' => $now->toDateString(), 'farms' => $farmReports,
                    ]);
                    $reports++;
                }

                return $reports;
            }, reload: false);
        }

        return $count;
    }

    public function harvestReminders(): int
    {
        $now = $this->now();
        if (! config('operational_notifications.enabled')) {
            return 0;
        }
        $dates = [];
        foreach ([7, 3, 0] as $days) {
            $dates[$now->addDays($days)->toDateString()] = $days;
        }
        $count = 0;
        $permissions = app(RolePermissions::class);
        $recipients = User::query()->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('name', 'petugas'))
            ->with(['role', 'teams', 'assignedFarm.team'])->lazyById(100);
        foreach ($recipients as $recipient) {
            $count += $permissions->withMembershipSnapshot($recipient, function () use ($recipient, $dates, $now): int {
                $farm = $recipient->assignedFarm;
                $team = $farm?->team;
                if (! $farm instanceof Farm || ! $team instanceof Team
                    || ! Gate::forUser($recipient)->allows('notifications.harvestReminder', $farm)) {
                    return 0;
                }
                Gate::forUser($recipient)->authorize('viewAny', [PlantingCycle::class, $team]);
                $cycles = PlantingCycle::visibleTo($recipient, $team)->where('farm_id', $farm->id)
                    ->where('status', 'active')->whereIn('estimated_harvest_at', array_keys($dates))
                    ->orderByDesc('estimated_harvest_at')->orderBy('id')->get();
                foreach ($cycles as $cycle) {
                    // The query is restricted to this persisted farm. Reuse that exact
                    // parent for record authorization instead of lazy-loading it per cycle.
                    $cycle->setRelation('farm', $farm);
                    Gate::forUser($recipient)->authorize('view', $cycle);
                    $date = $cycle->estimated_harvest_at->toDateString();
                    $this->dispatch($recipient, [
                        'kind' => 'harvest_reminder', 'user_id' => $recipient->id, 'team_id' => $team->id,
                        'farm_id' => $farm->id, 'cycle_id' => $cycle->id, 'harvest_date' => $date,
                        'days_before' => $dates[$date], 'event_date' => $now->toDateString(),
                        'farm_name' => $farm->name, 'recipient_name' => $recipient->name,
                    ]);
                }

                return $cycles->count();
            }, reload: false);
        }

        return $count;
    }

    /** @param array<string, mixed> $data */
    private function dispatch(User $recipient, array $data): void
    {
        // A stable inbox ID prevents repeated in-app jobs from creating duplicate rows.
        // WhatsApp has no durable deduplication after removing the outbox.
        $identity = $data['kind'] === 'daily_report'
            ? [$data['kind'], $data['user_id'], $data['team_id'], $data['event_date']]
            : [$data['kind'], $data['user_id'], $data['cycle_id'], $data['harvest_date'], $data['days_before']];
        $hash = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
        $data['id'] = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-5'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
        $notification = match ($data['kind']) {
            'daily_report' => new DailyReportNotification($data),
            'harvest_reminder' => new HarvestReminderNotification($data),
            default => throw new \LogicException('Unsupported operational notification type.'),
        };
        DB::afterCommit(function () use ($recipient, $notification): void {
            $recipient->notify($notification);
            SendWhatsAppNotification::dispatch($notification);
        });
    }

    /** @param array<string, mixed> $data */
    public function validRecipient(array $data): ?User
    {
        $now = $this->now();
        if (! config('operational_notifications.enabled') || $now->toDateString() !== $data['event_date']) {
            return null;
        }
        if (! is_string($data['user_id'] ?? null)) {
            return null;
        }
        $user = User::with('role')->find($data['user_id']);
        if (! $user || ! $user->is_active) {
            return null;
        }

        return app(RolePermissions::class)->withMembershipSnapshot($user, function () use ($user, $data): ?User {
            if ($data['kind'] === 'daily_report') {
                $team = Team::query()->whereKey($data['team_id'])->first();
                $farms = $data['farms'] ?? null;
                if (! $team || ! Gate::forUser($user)->allows('notifications.dailyReport', $team)
                    || ! is_array($farms) || $farms === []) {
                    return null;
                }
                $farmIds = [];
                foreach ($farms as $farmReport) {
                    if (! is_array($farmReport) || ! is_string($farmReport['id'] ?? null)) {
                        return null;
                    }
                    $farmIds[] = $farmReport['id'];
                }
                if (count(array_unique($farmIds)) !== count($farmIds)
                    || Farm::visibleTo($user, $team)->whereIn('id', $farmIds)->count() !== count($farmIds)) {
                    return null;
                }
                Gate::forUser($user)->authorize('notifications.dailyReport', $team);

                return $user;
            }
            if (! is_string($data['farm_id'] ?? null)) {
                return null;
            }
            $farm = Farm::with('team')->find($data['farm_id']);
            if (! $farm || ! $farm->team || $farm->team_id !== $data['team_id']) {
                return null;
            }
            if ($data['kind'] !== 'harvest_reminder' || ! Gate::forUser($user)->allows('notifications.harvestReminder', $farm)) {
                return null;
            }
            $cycle = PlantingCycle::visibleTo($user, $farm->team)->whereKey($data['cycle_id'])->first();
            $cycle?->setRelation('farm', $farm);
            if (! $cycle || $cycle->farm_id !== $farm->id || $cycle->status !== 'active'
                || $cycle->estimated_harvest_at?->toDateString() !== $data['harvest_date']
                || ! Gate::forUser($user)->allows('view', $cycle)) {
                return null;
            }

            Gate::forUser($user)->authorize('notifications.harvestReminder', $farm);

            return $user;
        });
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'));
    }
}
