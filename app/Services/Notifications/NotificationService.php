<?php

namespace App\Services\Notifications;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Models\User;
use App\Notifications\SopDeviationNotification;
use App\Notifications\TaskReminderNotification;
use App\Notifications\WeeklyReportNotification;
use App\Support\RolePermissions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class NotificationService
{
    /** @var array<string, list<string>> */
    private const AUDIENCES = [
        'weekly_report' => ['owner', 'admin'],
        'task_reminder' => ['petugas'],
        'sop_deviation' => ['admin'],
    ];

    public function __construct(private readonly WeeklyReportService $weekly) {}

    public function weeklyReports(): int
    {
        if (! config('operational_notifications.enabled')) {
            return 0;
        }
        $now = $this->now();
        $start = $now->startOfWeek(CarbonImmutable::MONDAY);
        $end = $start->addDays(6)->setTime(20, 0);
        $count = 0;
        $permissions = app(RolePermissions::class);
        foreach ($this->recipientQuery(self::AUDIENCES['weekly_report'])->lazyById(100) as $recipient) {
            $count += $permissions->withMembershipSnapshot($recipient, function () use ($permissions, $recipient, $start, $end, $now): int {
                $reports = 0;
                $ownerFarms = [];
                $audience = $permissions->roleName($recipient);
                foreach ($recipient->teams->where('is_personal', false) as $team) {
                    if (! Gate::forUser($recipient)->allows('notifications.weeklyReport', [$team, $audience])) {
                        continue;
                    }
                    Gate::forUser($recipient)->authorize('notifications.weeklyReport', [$team, $audience]);
                    $farms = Farm::visibleTo($recipient, $team)->orderBy('name')->orderBy('id')->get();
                    if ($farms->isEmpty()) {
                        continue;
                    }
                    if ($audience === 'owner') {
                        array_push($ownerFarms, ...$this->weekly->ownerFarms($recipient, $team, $farms, $start, $end));

                        continue;
                    }
                    $this->dispatch($recipient, [
                        'kind' => 'weekly_report', 'format_version' => 2, 'audience' => 'admin',
                        'user_id' => $recipient->id, 'team_id' => $team->id, 'team_ids' => [$team->id],
                        'event_date' => $now->toDateString(), 'period_start' => $start->toDateString(),
                        'period_end' => $end->toDateString(),
                        'farms' => $this->weekly->adminFarms($recipient, $team, $farms, $start, $end),
                    ]);
                    $reports++;
                }
                if ($ownerFarms !== []) {
                    $this->dispatch($recipient, [
                        'kind' => 'weekly_report', 'format_version' => 2, 'audience' => 'owner',
                        'user_id' => $recipient->id, 'team_id' => null,
                        'team_ids' => array_values(array_unique(array_column($ownerFarms, 'team_id'))),
                        'event_date' => $now->toDateString(), 'period_start' => $start->toDateString(),
                        'period_end' => $end->toDateString(), 'farms' => $ownerFarms,
                    ]);
                    $reports++;
                }

                return $reports;
            }, reload: false);
        }

        return $count;
    }

    public function taskReminders(): int
    {
        return $this->taskNotifications('task_reminder');
    }

    public function sopDeviations(): int
    {
        return $this->taskNotifications('sop_deviation');
    }

    /**
     * @param  list<string>  $roles
     * @return Builder<User>
     */
    private function recipientQuery(array $roles): Builder
    {
        return User::query()->where('is_active', true)->whereHas('role', fn ($query) => $query->whereIn('name', $roles))
            ->with(['role', 'teams']);
    }

    private function taskNotifications(string $kind): int
    {
        if (! config('operational_notifications.enabled')) {
            return 0;
        }
        $now = $this->now();
        $count = 0;
        $permissions = app(RolePermissions::class);
        foreach ($this->recipientQuery(self::AUDIENCES[$kind])->lazyById(100) as $recipient) {
            $count += $permissions->withMembershipSnapshot($recipient, function () use ($recipient, $kind, $now): int {
                $reports = 0;
                foreach ($recipient->teams->where('is_personal', false) as $team) {
                    $ability = $kind === 'task_reminder' ? 'notifications.taskReminder' : 'notifications.sopDeviation';
                    if (! Gate::forUser($recipient)->allows($ability, $team)) {
                        continue;
                    }
                    Gate::forUser($recipient)->authorize($ability, $team);
                    $tasks = $this->taskRows($recipient, $team, $kind, $now);
                    if ($tasks === []) {
                        continue;
                    }
                    $this->dispatch($recipient, [
                        'kind' => $kind, 'user_id' => $recipient->id, 'team_id' => $team->id,
                        'event_date' => $now->toDateString(), 'tasks' => $tasks,
                    ]);
                    $reports++;
                }

                return $reports;
            }, reload: false);
        }

        return $count;
    }

    /**
     * Subblock tasks contribute to their block; no subblock labels are sent.
     *
     * @param  list<string>|null  $ids
     * @return list<array<string, mixed>>
     */
    private function taskRows(User $recipient, Team $team, string $kind, CarbonImmutable $now, ?array $ids = null): array
    {
        $farms = Farm::visibleTo($recipient, $team)->get()->keyBy('id');
        $blocks = FarmBlock::visibleTo($recipient, $team)->whereIn('farm_id', $farms->modelKeys())->get()->keyBy('id');
        $cycles = PlantingCycle::visibleTo($recipient, $team)->whereIn('farm_id', $farms->modelKeys())
            ->where('status', 'active')->whereNotNull('planted_at')->where('planted_at', '<=', $now->toDateString())->get()->keyBy('id');
        $query = PlantingCycleTask::visibleTo($recipient, $team)->whereIn('planting_cycle_id', $cycles->modelKeys())
            ->whereIn('status', ['pending', 'in_progress']);
        if ($ids !== null) {
            $query->whereKey($ids);
        }
        if ($kind === 'task_reminder') {
            $dates = array_map(fn (int $days): string => $now->addDays($days)->toDateString(), [3, 1, 0]);
            $query->whereIn(DB::raw('COALESCE(rescheduled_date, scheduled_date)'), $dates);
        } else {
            $query->whereDate(DB::raw('COALESCE(rescheduled_date, scheduled_date)'), '<', $now->toDateString());
        }

        return array_values($query->orderBy('id')->get()->map(function (PlantingCycleTask $task) use ($cycles, $farms, $blocks, $kind, $now): ?array {
            $date = $task->effectiveScheduledDate();
            $due = $date?->addDays($task->tolerance_days);
            if ($date === null || ($kind === 'sop_deviation' && $due->toDateString() >= $now->toDateString())) {
                return null;
            }
            $cycle = $cycles->get($task->planting_cycle_id);
            $farm = $farms->get($cycle->farm_id);
            $block = $blocks->get($cycle->block_id);
            if ($cycle->block_id !== null && ($block === null || $block->getAttribute('farm_id') !== $farm->id)) {
                return null;
            }

            return [
                'id' => $task->id, 'cycle_id' => $cycle->id, 'farm_id' => $farm->id,
                'farm_name' => $farm->name, 'block_id' => $cycle->block_id,
                'block_name' => $block?->getAttribute('name') ?? 'Tanpa blok', 'name' => $task->name,
                'scheduled_date' => $date->toDateString(), 'due_date' => $due->toDateString(),
                'days_before' => (int) $now->startOfDay()->diffInDays($date, false),
            ];
        })->filter()->all());
    }

    /** @param array<string, mixed> $data */
    private function dispatch(User $recipient, array $data): void
    {
        $identity = [$data['kind'], $data['user_id'], $data['team_id'], $data['period_start'] ?? $data['event_date']];
        if ($data['kind'] === 'weekly_report') {
            $identity[] = $data['audience'];
        }
        $hash = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
        $data['id'] = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-5'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
        $notification = match ($data['kind']) {
            'weekly_report' => new WeeklyReportNotification($data),
            'task_reminder' => new TaskReminderNotification($data),
            'sop_deviation' => new SopDeviationNotification($data),
            default => throw new \LogicException('Unsupported operational notification type.'),
        };
        DB::afterCommit(function () use ($recipient, $notification, $data): void {
            $recipient->notify($notification);
            if ($notification instanceof WeeklyReportNotification) {
                if (! is_array($data['farms'])) {
                    throw new \LogicException('Weekly report farms must be an array.');
                }
                $groups = $data['audience'] === 'owner'
                    ? collect($data['farms'])->groupBy('region_key')->all()
                    : array_map(fn (array $farm): array => [$farm], $data['farms']);
                foreach ($groups as $farms) {
                    $farms = $farms instanceof \Illuminate\Support\Collection ? $farms->values()->all() : $farms;
                    SendWhatsAppNotification::dispatch(new WeeklyReportNotification([
                        ...$data, 'farms' => $farms,
                        'team_ids' => array_values(array_unique(array_column($farms, 'team_id'))),
                    ]));
                }
            } else {
                SendWhatsAppNotification::dispatch($notification);
            }
        });
    }

    /** @param array<string, mixed> $data */
    public function validRecipient(array $data): ?User
    {
        $ability = match ($data['kind'] ?? null) {
            'weekly_report' => 'notifications.weeklyReport',
            'task_reminder' => 'notifications.taskReminder',
            'sop_deviation' => 'notifications.sopDeviation',
            default => null,
        };
        if ($ability === null || ! config('operational_notifications.enabled')
            || $this->now()->toDateString() !== ($data['event_date'] ?? null)
            || ! is_string($data['user_id'] ?? null)) {
            return null;
        }
        $user = $this->recipientQuery(self::AUDIENCES[$data['kind']])->whereKey($data['user_id'])->first();
        if ($user === null) {
            return null;
        }
        if ($data['kind'] === 'weekly_report') {
            return $this->validWeeklyRecipient($user, $data);
        }
        if (! is_int($data['team_id'] ?? null)) {
            return null;
        }
        $team = Team::query()->whereKey($data['team_id'])->first();
        if ($team === null || ! Gate::forUser($user)->allows($ability, $team)) {
            return null;
        }
        Gate::forUser($user)->authorize($ability, $team);

        return $user;
    }

    /** @param array<string, mixed> $data */
    private function validWeeklyRecipient(User $user, array $data): ?User
    {
        if (($data['format_version'] ?? null) !== 2 || ! in_array($data['audience'] ?? null, ['owner', 'admin'], true)
            || ! is_array($data['team_ids'] ?? null) || $data['team_ids'] === []
            || ! is_array($data['farms'] ?? null) || $data['farms'] === []) {
            return null;
        }
        $farmIds = array_column($data['farms'], 'id');
        if (count($farmIds) !== count($data['farms']) || count(array_unique($farmIds)) !== count($farmIds)
            || ($data['audience'] === 'admin' && $data['team_ids'] !== [$data['team_id'] ?? null])) {
            return null;
        }

        return app(RolePermissions::class)->withMembershipSnapshot($user, function () use ($user, $data, $farmIds): ?User {
            $validated = 0;
            foreach (array_unique($data['team_ids']) as $teamId) {
                if (! is_int($teamId)) {
                    return null;
                }
                $team = Team::query()->whereKey($teamId)->first();
                if ($team === null || ! Gate::forUser($user)->allows('notifications.weeklyReport', [$team, $data['audience']])) {
                    return null;
                }
                Gate::forUser($user)->authorize('notifications.weeklyReport', [$team, $data['audience']]);
                $snapshots = array_values(array_filter($data['farms'], fn (array $farm): bool => ($farm['team_id'] ?? null) === $teamId));
                $farms = Farm::visibleTo($user, $team)->whereKey(array_column($snapshots, 'id'))->get()->keyBy('id');
                if ($snapshots === [] || $farms->count() !== count($snapshots)) {
                    return null;
                }
                $blocks = $data['audience'] === 'admin'
                    ? FarmBlock::visibleTo($user, $team)->whereIn('farm_id', $farms->modelKeys())->pluck('farm_id', 'id')
                    : collect();
                foreach ($snapshots as $snapshot) {
                    if ($data['audience'] === 'owner' && $this->weekly->region($farms->get($snapshot['id']))['key'] !== ($snapshot['region_key'] ?? null)) {
                        return null;
                    }
                    foreach ($snapshot['blocks'] ?? [] as $block) {
                        if ($block['id'] !== null && $blocks->get($block['id']) !== $snapshot['id']) {
                            return null;
                        }
                    }
                }
                $validated += $farms->count();
            }

            return $validated === count($farmIds) ? $user : null;
        });
    }

    /**
     * Refresh each task so completing one does not suppress the remaining tasks.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function dataForDelivery(User $recipient, array $data): ?array
    {
        if ($data['kind'] === 'weekly_report') {
            return $data;
        }
        if (! is_int($data['team_id'] ?? null) || ! is_array($data['tasks'] ?? null)) {
            return null;
        }
        $team = Team::query()->whereKey($data['team_id'])->firstOrFail();
        $snapshots = [];
        $ids = [];
        foreach ($data['tasks'] as $snapshot) {
            if (! is_array($snapshot) || ! is_string($snapshot['id'] ?? null)) {
                return null;
            }
            $ids[] = $snapshot['id'];
            $snapshots[$snapshot['id']] = $snapshot;
        }
        $tasks = $this->taskRows($recipient, $team, $data['kind'], $this->now(), $ids);
        $tasks = array_values(array_filter($tasks, function (array $task) use ($snapshots): bool {
            $snapshot = $snapshots[$task['id']] ?? null;

            return $snapshot !== null && $task['cycle_id'] === $snapshot['cycle_id']
                && $task['farm_id'] === $snapshot['farm_id'] && $task['block_id'] === $snapshot['block_id']
                && $task['scheduled_date'] === $snapshot['scheduled_date'] && $task['due_date'] === $snapshot['due_date'];
        }));

        return $tasks === [] ? null : [...$data, 'tasks' => $tasks];
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'));
    }
}
