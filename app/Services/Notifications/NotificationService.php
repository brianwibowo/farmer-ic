<?php

namespace App\Services\Notifications;

use App\Enums\IncidentSeverity;
use App\Jobs\SendWhatsAppNotification;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\IncidentDetail;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Models\User;
use App\Notifications\IncidentReportNotification;
use App\Notifications\SopDeviationNotification;
use App\Notifications\TaskReminderNotification;
use App\Notifications\WeeklyReportNotification;
use App\Support\RolePermissions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class NotificationService
{
    /** @var array<string, list<string>> */
    private const AUDIENCES = [
        'weekly_report' => ['owner', 'admin'],
        'task_reminder' => ['admin', 'petugas'],
        'sop_deviation' => ['admin'],
        'incident_report' => ['admin'],
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
                    $sent = $this->dispatch($recipient, [
                        'kind' => 'weekly_report', 'format_version' => 2, 'audience' => 'admin',
                        'user_id' => $recipient->id, 'team_id' => $team->id, 'team_ids' => [$team->id],
                        'event_date' => $now->toDateString(), 'period_start' => $start->toDateString(),
                        'period_end' => $end->toDateString(),
                        'farms' => $this->weekly->adminFarms($recipient, $team, $farms, $start, $end),
                    ]);
                    $reports += (int) $sent;
                }
                if ($ownerFarms !== []) {
                    $sent = $this->dispatch($recipient, [
                        'kind' => 'weekly_report', 'format_version' => 2, 'audience' => 'owner',
                        'user_id' => $recipient->id, 'team_id' => null,
                        'team_ids' => array_values(array_unique(array_column($ownerFarms, 'team_id'))),
                        'event_date' => $now->toDateString(), 'period_start' => $start->toDateString(),
                        'period_end' => $end->toDateString(), 'farms' => $ownerFarms,
                    ]);
                    $reports += (int) $sent;
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
                    $sent = $this->dispatch($recipient, [
                        'kind' => $kind, 'user_id' => $recipient->id, 'team_id' => $team->id,
                        'event_date' => $now->toDateString(), 'tasks' => $tasks,
                    ]);
                    $reports += (int) $sent;
                }

                return $reports;
            }, reload: false);
        }

        return $count;
    }

    /**
     * Tasks retain persisted subblock identity for the inbox; relationships are loaded in batches.
     *
     * @param  list<string>|null  $ids
     * @return list<array<string, mixed>>
     */
    private function taskRows(User $recipient, Team $team, string $kind, CarbonImmutable $now, ?array $ids = null): array
    {
        $farms = Farm::visibleTo($recipient, $team)->get()->keyBy('id');
        $blocks = FarmBlock::visibleTo($recipient, $team)->whereIn('farm_id', $farms->modelKeys())->get()->keyBy('id');
        $cycles = PlantingCycle::visibleTo($recipient, $team)->whereIn('farm_id', $farms->modelKeys())
            ->where('status', 'active')->whereNotNull('planted_at')->where('planted_at', '<=', $now->toDateString())
            ->with(['variety' => fn ($query) => $query->visibleTo($recipient, $team)
                ->with(['commodity' => fn ($commodities) => $commodities->visibleTo($recipient, $team)])])->get()->keyBy('id');
        $query = PlantingCycleTask::visibleTo($recipient, $team)->whereIn('planting_cycle_id', $cycles->modelKeys())
            ->whereIn('status', ['pending', 'in_progress']);
        if ($ids !== null) {
            $query->whereKey($ids);
        }
        if ($kind === 'task_reminder') {
            $dates = array_map(fn (int $days): string => $now->addDays($days)->toDateString(), [3, 1, 0]);
            $query->where('activity_category', 'harvest')->whereIn(DB::raw('COALESCE(rescheduled_date, scheduled_date)'), $dates);
        } else {
            $query->whereNotNull('scheduled_date');
        }

        $rows = [];
        foreach ($query->orderBy('id')->get() as $task) {
            $date = $task->effectiveScheduledDate();
            if ($date === null) {
                continue;
            }
            $due = $date->addDays($task->tolerance_days);
            $cycle = $cycles->get($task->planting_cycle_id);
            $farm = $farms->get($cycle->farm_id);
            $block = $blocks->get($cycle->block_id);
            if ($cycle->block_id !== null && ($block === null || $block->getAttribute('farm_id') !== $farm->id)) {
                continue;
            }
            $base = [
                'id' => $task->id, 'cycle_id' => $cycle->id, 'farm_id' => $farm->id,
                'subblock_name' => $cycle->subblock_label ?? 'Subblok '.$cycle->batch_number,
                'commodity_id' => $cycle->variety?->commodity?->id,
                'commodity_name' => $cycle->variety?->commodity?->name,
                'farm_name' => $farm->name, 'block_id' => $cycle->block_id,
                'block_name' => $block?->getAttribute('name') ?? 'Tanpa blok', 'name' => $task->name,
                'scheduled_date' => $date->toDateString(), 'due_date' => $due->toDateString(),
                'days_before' => (int) $now->startOfDay()->diffInDays($date, false),
            ];
            if ($kind === 'task_reminder') {
                $rows[] = [...$base, 'event_key' => $task->id.':H'.$base['days_before']];

                continue;
            }
            $originalDue = $task->scheduled_date?->addDays($task->tolerance_days);
            if ($task->rescheduled_date !== null && $originalDue !== null && $task->rescheduled_date->gt($originalDue)) {
                $rows[] = [...$base, 'event_key' => $task->id.':reschedule_limit', 'reason' => 'Jadwal ulang melewati toleransi jadwal awal', 'due_date' => $originalDue->toDateString()];
            }
            if ($due->toDateString() < $now->toDateString()) {
                $rows[] = [...$base, 'event_key' => $task->id.':unfinished_limit', 'reason' => 'Belum selesai melewati toleransi jadwal efektif'];
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $data */
    private function dispatch(User $recipient, array $data): bool
    {
        return DB::transaction(function () use ($recipient, $data): bool {
            // Serialize claims per recipient, not per task; a unique ledger survives retries/restarts.
            User::query()->whereKey($recipient->id)->lockForUpdate()->firstOrFail();
            $items = $data['tasks'] ?? $data['incidents'] ?? null;
            $keys = $items === null
                ? ['weekly:'.$data['audience'].':'.$data['team_id'].':'.$data['period_start']]
                : array_column($items, 'event_key');
            $hashes = array_map(fn ($key) => hash('sha256', $recipient->id.':'.$data['kind'].':'.$key), $keys);
            $existing = DB::table('operational_notification_events')->whereIn('event_key', $hashes)->pluck('event_key')->all();
            $newHashes = array_values(array_diff($hashes, $existing));
            if ($newHashes === []) {
                return false;
            }
            if ($items !== null) {
                $field = isset($data['tasks']) ? 'tasks' : 'incidents';
                $data[$field] = array_values(array_filter($items, fn ($item) => in_array(hash('sha256', $recipient->id.':'.$data['kind'].':'.$item['event_key']), $newHashes, true)));
            }
            $data['recipient_role'] = app(RolePermissions::class)->roleName($recipient);
            $hash = hash('sha256', implode('|', $newHashes));
            $data['id'] = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-5'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
            DB::table('operational_notification_deliveries')->insert([
                'id' => $data['id'], 'user_id' => $recipient->id, 'payload' => json_encode($data, JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);
            DB::table('operational_notification_events')->insert(array_map(fn ($key) => ['event_key' => $key, 'delivery_id' => $data['id']], $newHashes));
            DB::afterCommit(fn () => $this->queueDelivery($data));

            return true;
        });
    }

    /** @param array<string, mixed> $data */
    private function notification(array $data): WeeklyReportNotification|TaskReminderNotification
    {
        return match ($data['kind']) {
            'weekly_report' => new WeeklyReportNotification($data),
            'task_reminder' => new TaskReminderNotification($data),
            'sop_deviation' => new SopDeviationNotification($data),
            'incident_report' => new IncidentReportNotification($data),
            default => throw new \LogicException('Unsupported operational notification type.'),
        };
    }

    /** @param array<string, mixed> $data */
    private function queueDelivery(array $data): void
    {
        $recipient = $this->validRecipient($data);
        if ($recipient !== null) {
            $notification = $this->notification($data);
            $recipient->notify($notification);
            if (config('operational_notifications.fonnte.enabled')) {
                SendWhatsAppNotification::dispatch($notification);
            }
        }
        DB::table('operational_notification_deliveries')->where('id', $data['id'])->update(['queued_at' => now()]);
    }

    public function resumePendingDeliveries(): void
    {
        if (! config('operational_notifications.enabled')) {
            return;
        }
        foreach (DB::table('operational_notification_deliveries')->whereNull('queued_at')->orderBy('id')->lazyById(100) as $delivery) {
            $this->queueDelivery(json_decode($delivery->payload, true, flags: JSON_THROW_ON_ERROR));
        }
    }

    public function incidentReports(): int
    {
        if (! config('operational_notifications.enabled')) {
            return 0;
        }
        $count = 0;
        foreach ($this->recipientQuery(['admin'])->lazyById(100) as $recipient) {
            $count += app(RolePermissions::class)->withMembershipSnapshot($recipient, function () use ($recipient): int {
                $reports = 0;
                foreach ($recipient->teams->where('is_personal', false) as $team) {
                    if (! Gate::forUser($recipient)->allows('notifications.incidentReport', $team)) {
                        continue;
                    }
                    Gate::forUser($recipient)->authorize('notifications.incidentReport', $team);
                    $incidents = $this->incidentRows($recipient, $team);
                    if ($incidents !== []) {
                        $reports += (int) $this->dispatch($recipient, [
                            'kind' => 'incident_report', 'user_id' => $recipient->id, 'team_id' => $team->id,
                            'event_date' => $this->now()->toDateString(), 'incidents' => $incidents,
                        ]);
                    }
                }

                return $reports;
            }, reload: false);
        }

        return $count;
    }

    /** @param list<string>|null $ids
     * @return list<array<string, mixed>> */
    private function incidentRows(User $recipient, Team $team, ?array $ids = null): array
    {
        $query = FarmActivity::visibleTo($recipient, $team)
            ->whereHas('performer', fn ($users) => $users->withTrashed()->whereHas('role', fn ($roles) => $roles->where('name', 'petugas')))
            ->whereHas('incidentDetail', fn ($details) => $details->visibleTo($recipient, $team));
        if ($ids !== null) {
            $query->whereKey($ids);
        }
        if ($ids === null) {
            $prefix = $recipient->id.':incident_report:';
            $query->whereNotExists(fn ($seen) => $seen->selectRaw('1')->from('operational_notification_events')
                ->whereRaw("event_key = encode(sha256(convert_to(? || farm_activities.id::text, 'UTF8')), 'hex')", [$prefix]));
        }
        $activities = $query->orderBy('id')->limit(500)->get();
        $details = IncidentDetail::visibleTo($recipient, $team)->whereIn('activity_id', $activities->modelKeys())->get()->keyBy('activity_id');
        $farms = Farm::visibleTo($recipient, $team)->whereKey($activities->pluck('farm_id'))->get()->keyBy('id');

        return array_values($activities->map(fn ($activity) => [
            'id' => $activity->id, 'event_key' => $activity->id, 'farm_id' => $activity->farm_id,
            'farm_name' => $farms->get($activity->farm_id)?->name, 'date' => $activity->activity_date->toIso8601String(),
            'category' => $details->get($activity->id)->incident_category, 'severity' => IncidentSeverity::fromStored($details->get($activity->id)->severity)?->label() ?? $details->get($activity->id)->severity,
            'description' => $activity->description === null ? null : Str::limit(Str::squish($activity->description), 240),
        ])->all());
    }

    /** @param array<string, mixed> $data */
    public function validRecipient(array $data): ?User
    {
        $ability = match ($data['kind'] ?? null) {
            'weekly_report' => 'notifications.weeklyReport',
            'task_reminder' => 'notifications.taskReminder',
            'sop_deviation' => 'notifications.sopDeviation',
            'incident_report' => 'notifications.incidentReport',
            default => null,
        };
        if ($ability === null || ! config('operational_notifications.enabled')
            || $this->now()->toDateString() !== ($data['event_date'] ?? null)
            || ! is_string($data['user_id'] ?? null)) {
            return null;
        }
        $user = $this->recipientQuery(self::AUDIENCES[$data['kind']])->whereKey($data['user_id'])->first();
        if ($user === null || (isset($data['recipient_role']) && app(RolePermissions::class)->roleName($user) !== $data['recipient_role'])) {
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
        if ($data['kind'] === 'incident_report' && is_int($data['team_id'] ?? null) && is_array($data['incidents'] ?? null)) {
            $team = Team::query()->findOrFail($data['team_id']);
            $incidents = $this->incidentRows($recipient, $team, array_column($data['incidents'], 'id'));

            return $incidents === [] ? null : [...$data, 'incidents' => $incidents];
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
            $snapshots[$snapshot['event_key'] ?? $snapshot['id']] = $snapshot;
        }
        $tasks = $this->taskRows($recipient, $team, $data['kind'], $this->now(), $ids);
        $tasks = array_values(array_filter($tasks, function (array $task) use ($snapshots): bool {
            $snapshot = $snapshots[$task['event_key']] ?? null;

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
