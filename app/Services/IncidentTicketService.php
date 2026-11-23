<?php

namespace App\Services;

use App\Enums\IncidentSeverity;
use App\Models\ActivityType;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\IncidentDetail;
use App\Models\IncidentTicket;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use App\Services\Activity\ActivitySequenceService;
use App\Services\Activity\DailyActivityReportService;
use App\Support\SubblockLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class IncidentTicketService
{
    public const STATUS_LABELS = [
        'open' => 'Perlu penanganan',
        'in_progress' => 'Sedang ditangani',
        'resolved' => 'Selesai',
    ];

    public function __construct(
        private readonly DailyActivityReportService $reports,
        private readonly ActivitySequenceService $sequence,
    ) {}

    /** @param array<string, mixed> $details */
    public function createFromIncidentReport(FarmActivity $activity, IncidentDetail $detail, array $details, User $actor): IncidentTicket
    {
        $resolvedSeverity = IncidentSeverity::fromStored((string) ($details['severity'] ?? $detail->severity));
        $severity = $resolvedSeverity instanceof IncidentSeverity
            ? $resolvedSeverity->value
            : IncidentSeverity::Medium->value;
        $resolvedImmediately = ($details['incident_status'] ?? 'Perlu penanganan') === 'Sudah ditangani langsung';
        $immediateAction = filled($details['immediate_action'] ?? null)
            ? trim((string) $details['immediate_action'])
            : null;
        $now = CarbonImmutable::now('Asia/Jakarta');

        return IncidentTicket::create([
            'farm_id' => $activity->farm_id,
            'activity_id' => $activity->id,
            'planting_cycle_id' => $activity->planting_cycle_id,
            'block_id' => $activity->getAttribute('block_id') ?? $activity->plantingCycle?->block_id,
            'incident_category' => $detail->incident_category,
            'severity' => $severity,
            'status' => $resolvedImmediately ? 'resolved' : 'open',
            'resolution_note' => $resolvedImmediately ? $immediateAction : null,
            'resolved_by' => $resolvedImmediately ? $actor->id : null,
            'resolved_at' => $resolvedImmediately ? $now : null,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function respond(IncidentTicket $ticket, User $actor, array $data): IncidentTicket
    {
        return DB::transaction(function () use ($ticket, $actor, $data): IncidentTicket {
            $locked = $this->lockTicket($ticket);
            if ($locked->status === 'resolved') {
                throw ValidationException::withMessages([
                    'status' => 'Tiket yang sudah selesai harus dibuka kembali sebelum ditanggapi.',
                ]);
            }

            $severity = $this->resolveSeverityInput($data['severity'] ?? null) ?? $locked->severity;
            $now = CarbonImmutable::now('Asia/Jakarta');

            $locked->forceFill([
                'status' => 'in_progress',
                'severity' => $severity,
                'admin_note' => trim((string) $data['admin_note']),
                'responded_by' => $actor->id,
                'responded_at' => $now,
            ])->save();

            $this->syncIncidentReport($locked, 'in_progress', severity: $severity);

            return $locked->fresh($this->relations()) ?? $locked;
        });
    }

    /** @param array<string, mixed> $data */
    public function resolveDirectly(IncidentTicket $ticket, User $actor, array $data): IncidentTicket
    {
        return DB::transaction(function () use ($ticket, $actor, $data): IncidentTicket {
            $locked = $this->lockTicket($ticket);
            if ($locked->status === 'resolved') {
                throw ValidationException::withMessages([
                    'status' => 'Tiket insiden sudah berstatus selesai.',
                ]);
            }

            $severity = $this->resolveSeverityInput($data['severity'] ?? null) ?? $locked->severity;
            $note = trim((string) $data['resolution_note']);
            $now = CarbonImmutable::now('Asia/Jakarta');

            $locked->forceFill([
                'status' => 'resolved',
                'severity' => $severity,
                'resolution_note' => $note,
                'resolved_by' => $actor->id,
                'resolved_at' => $now,
                'resolved_activity_id' => null,
                'reopen_reason' => null,
                'reopened_by' => null,
                'reopened_at' => null,
            ])->save();

            $this->syncIncidentReport($locked, 'resolved', $note, true, $severity);

            return $locked->fresh($this->relations()) ?? $locked;
        });
    }

    public function assertFollowUpTargetMatches(
        User $actor,
        Farm $farm,
        IncidentTicket $ticket,
        ActivityType $type,
        ?PlantingCycle $cycle,
        ?FarmBlock $submittedBlock,
    ): void {
        if ($ticket->farm_id !== $farm->id) {
            throw ValidationException::withMessages([
                'incident_ticket_id' => 'Tiket insiden tidak berasal dari kebun yang sama.',
            ]);
        }

        if ($ticket->status === 'resolved') {
            throw ValidationException::withMessages([
                'incident_ticket_id' => 'Tiket insiden sudah berstatus selesai.',
            ]);
        }

        [$allowedCodes, $blockedReason] = $this->compatibleCategoryCodesAndReason($actor, $farm, $ticket);
        if (! in_array($type->code, $allowedCodes, true)) {
            throw ValidationException::withMessages([
                'incident_ticket_id' => $blockedReason ?? 'Kategori aktivitas ini tidak kompatibel untuk menindaklanjuti tiket insiden tersebut.',
            ]);
        }

        if ($ticket->planting_cycle_id !== null) {
            if ($cycle?->id !== $ticket->planting_cycle_id || $submittedBlock !== null) {
                throw ValidationException::withMessages([
                    'cycle_id' => 'Lokasi tindak lanjut harus sesuai dengan subblok pada tiket insiden.',
                ]);
            }

            return;
        }

        if ($ticket->block_id !== null) {
            if ($cycle !== null || $submittedBlock?->id !== $ticket->block_id) {
                throw ValidationException::withMessages([
                    'block_id' => 'Lokasi tindak lanjut harus sesuai dengan blok pada tiket insiden.',
                ]);
            }

            return;
        }

        if ($cycle !== null || $submittedBlock !== null) {
            throw ValidationException::withMessages([
                'incident_ticket_id' => 'Tindak lanjut insiden area kebun tidak boleh memilih blok atau subblok terpisah.',
            ]);
        }
    }

    public function resolveFromFollowUp(IncidentTicket $ticket, FarmActivity $activity, User $actor): IncidentTicket
    {
        $locked = $this->lockTicket($ticket);
        if ($locked->status === 'resolved') {
            throw ValidationException::withMessages([
                'incident_ticket_id' => 'Tiket insiden sudah berstatus selesai.',
            ]);
        }

        if ($activity->id === $locked->activity_id) {
            throw ValidationException::withMessages([
                'incident_ticket_id' => 'Aktivitas tindak lanjut tidak boleh sama dengan laporan insiden awal.',
            ]);
        }

        $activity->loadMissing('activityType');
        $activityType = $activity->activityType;
        $note = filled($activity->description)
            ? trim((string) $activity->description)
            : 'Diselesaikan melalui aktivitas '.($activityType instanceof ActivityType ? $activityType->name : 'tindak lanjut').'.';
        $now = CarbonImmutable::now('Asia/Jakarta');

        $locked->forceFill([
            'status' => 'resolved',
            'resolution_note' => $note,
            'resolved_by' => $actor->id,
            'resolved_at' => $now,
            'resolved_activity_id' => $activity->id,
            'reopen_reason' => null,
            'reopened_by' => null,
            'reopened_at' => null,
        ])->save();

        $this->syncIncidentReport($locked, 'resolved', $note, true);

        return $locked;
    }

    public function reopen(IncidentTicket $ticket, User $actor, string $reason): IncidentTicket
    {
        return DB::transaction(function () use ($ticket, $actor, $reason): IncidentTicket {
            $locked = $this->lockTicket($ticket);
            if ($locked->getAttribute('cancellation_role') !== null) {
                throw ValidationException::withMessages([
                    'status' => 'Tiket yang diselesaikan karena pembatalan subblok tidak dapat dibuka kembali.',
                ]);
            }
            if ($locked->status !== 'resolved') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya tiket berstatus selesai yang dapat dibuka kembali.',
                ]);
            }

            $now = CarbonImmutable::now('Asia/Jakarta');
            $locked->forceFill([
                'status' => 'open',
                'resolution_note' => null,
                'resolved_by' => null,
                'resolved_at' => null,
                'resolved_activity_id' => null,
                'reopen_reason' => trim($reason),
                'reopened_by' => $actor->id,
                'reopened_at' => $now,
            ])->save();

            $initialAction = IncidentDetail::query()
                ->where('activity_id', $locked->activity_id)
                ->value('proposed_solution');

            $this->syncIncidentReport($locked, 'open', is_string($initialAction) ? $initialAction : null, true);

            return $locked->fresh($this->relations()) ?? $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     tickets: \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, array<string, mixed>>,
     *     summary: array{total: int, open: int, in_progress: int, resolved: int, emergency_open: int},
     *     farms: list<array{id: string, name: string}>
     * }
     */
    public function adminIndex(User $actor, Team $team, array $filters): array
    {
        $base = $this->baseQuery($actor, $team);
        $this->applyCommonFilters($base, $actor, $team, $filters);
        $summary = $this->summary(clone $base);

        $view = (string) ($filters['view'] ?? 'unresolved');
        $query = clone $base;
        $this->applyViewFilter($query, $view);

        $tickets = $this->orderedQueue($query, $actor, $team)
            ->with($this->listRelations())
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString()
            ->through(fn (IncidentTicket $ticket): array => $this->row($ticket));

        $farms = array_values(Farm::query()
            ->visibleTo($actor, $team)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Farm $farm): array => ['id' => $farm->id, 'name' => $farm->name])
            ->all());

        return [
            'tickets' => $tickets,
            'summary' => $summary,
            'farms' => $farms,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     tickets: list<array<string, mixed>>,
     *     summary: array{total: int, open: int, in_progress: int, resolved: int, emergency_open: int}
     * }
     */
    public function petugasIndex(User $actor, Farm $farm, array $filters): array
    {
        $team = $farm->team;
        $base = $this->baseQuery($actor, $team)->where('incident_tickets.farm_id', $farm->id);
        $this->applyCommonFilters($base, $actor, $team, $filters);
        $summary = $this->summary(clone $base);

        $view = (string) ($filters['view'] ?? 'unresolved');
        $query = clone $base;
        $this->applyViewFilter($query, $view);

        $tickets = array_values($this->orderedQueue($query, $actor, $team)
            ->with($this->listRelations())
            ->get()
            ->map(fn (IncidentTicket $ticket): array => $this->row($ticket))
            ->all());

        return [
            'tickets' => $tickets,
            'summary' => $summary,
        ];
    }

    /** @return array<string, mixed> */
    public function detail(User $actor, IncidentTicket $ticket, string $audience = 'admin'): array
    {
        $ticket->loadMissing($this->relations());
        $farm = $ticket->farm;
        $activity = $ticket->activity;
        $resolvedActivity = $ticket->resolvedActivity;
        $cycle = $ticket->plantingCycle;
        $canViewCycle = $cycle instanceof PlantingCycle && Gate::forUser($actor)->allows('view', $cycle);

        $report = $activity instanceof FarmActivity
            ? $this->reports->activity($actor, $farm, $activity)
            : null;
        $followUpActivity = $resolvedActivity instanceof FarmActivity
            ? [
                ...$this->reports->activity($actor, $farm, $resolvedActivity),
                'work_date' => $resolvedActivity->dailyActivityLog?->work_date->toDateString()
                    ?? $resolvedActivity->activity_date->setTimezone('Asia/Jakarta')->toDateString(),
                'daily_log_id' => $resolvedActivity->daily_activity_log_id,
            ]
            : null;

        $followUp = $audience === 'petugas' && $ticket->status !== 'resolved' && Gate::forUser($actor)->allows('resolveByFollowUp', $ticket)
            ? $this->compatibleFollowUpCategories($actor, $farm, $ticket)
            : ['categories' => [], 'blocked_reason' => null];

        return [
            ...$this->row($ticket),
            'immediate_action' => $activity?->incidentDetail?->proposed_solution,
            'daily_log_id' => $activity?->daily_activity_log_id,
            'report' => $report,
            'follow_up_activity' => $followUpActivity,
            'follow_up_options' => $followUp['categories'],
            'follow_up_blocked_reason' => $followUp['blocked_reason'],
            'cancellation' => $ticket->getAttribute('cancellation_role') !== null && $canViewCycle ? [
                'reason' => $cycle->getAttribute('cancellation_reason'),
                'cancelled_at' => $cycle->getAttribute('cancelled_at') === null
                    ? null : CarbonImmutable::parse($cycle->getAttribute('cancelled_at'))->toISOString(),
                'role' => $ticket->getAttribute('cancellation_role'),
            ] : null,
            'can' => [
                'respond' => $ticket->status !== 'resolved' && Gate::forUser($actor)->allows('respond', $ticket),
                'resolve_directly' => $ticket->status !== 'resolved' && Gate::forUser($actor)->allows('resolveDirectly', $ticket),
                'resolve_by_follow_up' => $ticket->status !== 'resolved' && Gate::forUser($actor)->allows('resolveByFollowUp', $ticket),
                'reopen' => $ticket->status === 'resolved' && $ticket->getAttribute('cancellation_role') === null && Gate::forUser($actor)->allows('reopen', $ticket),
                'cancel_subblock' => $audience === 'admin' && $ticket->severity === IncidentSeverity::Emergency->value
                    && in_array($ticket->status, ['open', 'in_progress'], true) && $canViewCycle
                    && $cycle->farm_id === $ticket->farm_id && $cycle->block_id !== null
                    && in_array($cycle->status, SubblockLifecycle::RUNNING, true)
                    && Gate::forUser($actor)->allows('cancelSubblock', $ticket),
            ],
        ];
    }

    /**
     * @return array{
     *     categories: list<array{id: string, code: string, name: string, href: string}>,
     *     blocked_reason: string|null
     * }
     */
    public function compatibleFollowUpCategories(User $actor, Farm $farm, IncidentTicket $ticket): array
    {
        [$codes, $blockedReason] = $this->compatibleCategoryCodesAndReason($actor, $farm, $ticket);
        if ($codes === []) {
            return ['categories' => [], 'blocked_reason' => $blockedReason];
        }

        $categories = array_values(ActivityType::visibleTo($actor, $farm->team)
            ->where('is_active', true)
            ->where('is_selectable', true)
            ->whereIn('code', $codes)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (ActivityType $type): bool => Gate::forUser($actor)->allows('recordVoluntary', [FarmActivity::class, $farm, $type]))
            ->map(fn (ActivityType $type): array => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'href' => route('petugas.voluntary.create', [
                    'type' => $type->code,
                    'incident_ticket_id' => $ticket->id,
                ]),
            ])
            ->all());

        return ['categories' => $categories, 'blocked_reason' => null];
    }

    /** @return list<string> */
    public function compatibleCategoryCodes(User $actor, Farm $farm, IncidentTicket $ticket): array
    {
        return $this->compatibleCategoryCodesAndReason($actor, $farm, $ticket)[0];
    }

    /** @return array<string, mixed> */
    public function followUpContext(User $actor, Farm $farm, IncidentTicket $ticket): array
    {
        $ticket->loadMissing(['farm', 'block', 'plantingCycle.block', 'activity.incidentDetail']);
        $severity = IncidentSeverity::fromStored($ticket->severity) ?? IncidentSeverity::Medium;
        $cycle = $ticket->plantingCycle;
        $block = $ticket->block ?? $cycle?->block;

        $scope = $ticket->planting_cycle_id !== null
            ? 'cycle'
            : ($ticket->block_id !== null ? 'block' : 'farm');

        return [
            'id' => $ticket->id,
            'incident_category' => $ticket->incident_category,
            'severity' => $severity->value,
            'severity_label' => $severity->label(),
            'description' => $ticket->activity?->description,
            'admin_note' => $ticket->admin_note,
            'scope' => $scope,
            'cycle_id' => $ticket->planting_cycle_id,
            'block_id' => $scope === 'block' ? $ticket->block_id : null,
            'location_label' => $this->locationLabel($farm, $block, $cycle),
        ];
    }

    /** @return Builder<IncidentTicket> */
    private function baseQuery(User $actor, Team $team): Builder
    {
        return IncidentTicket::query()
            ->visibleTo($actor, $team)
            ->whereHas('farm', fn (Builder $farms) => $farms->visibleTo($actor, $team))
            ->whereHas('activity', fn (Builder $activities) => $activities->visibleTo($actor, $team));
    }

    /**
     * @param  Builder<IncidentTicket>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyCommonFilters(Builder $query, User $actor, Team $team, array $filters): void
    {
        if (! empty($filters['farm_id'])) {
            $query->where('incident_tickets.farm_id', $filters['farm_id']);
        }

        if (! empty($filters['severity'])) {
            $severity = IncidentSeverity::fromStored((string) $filters['severity']);
            if ($severity !== null) {
                $query->where('incident_tickets.severity', $severity->value);
            }
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $pattern = '%'.$search.'%';
            $query->where(function (Builder $q) use ($pattern, $actor, $team): void {
                $q->where('incident_tickets.incident_category', 'ilike', $pattern)
                    ->orWhere('incident_tickets.admin_note', 'ilike', $pattern)
                    ->orWhere('incident_tickets.resolution_note', 'ilike', $pattern)
                    ->orWhereHas('farm', fn (Builder $farms) => $farms->visibleTo($actor, $team)->where('name', 'ilike', $pattern))
                    ->orWhereHas('block', fn (Builder $blocks) => $blocks->visibleTo($actor, $team)->where('name', 'ilike', $pattern))
                    ->orWhereHas('plantingCycle', fn (Builder $cycles) => $cycles->visibleTo($actor, $team)->where('subblock_label', 'ilike', $pattern))
                    ->orWhereHas('activity', fn (Builder $activities) => $activities->visibleTo($actor, $team)
                        ->where(fn (Builder $act) => $act->where('description', 'ilike', $pattern)
                            ->orWhereHas('performer', fn (Builder $users) => $users->where('name', 'ilike', $pattern))));
            });
        }
    }

    /** @param Builder<IncidentTicket> $query */
    private function applyViewFilter(Builder $query, string $view): void
    {
        match ($view) {
            'open' => $query->where('incident_tickets.status', 'open'),
            'in_progress' => $query->where('incident_tickets.status', 'in_progress'),
            'resolved' => $query->where('incident_tickets.status', 'resolved'),
            'unresolved' => $query->whereIn('incident_tickets.status', ['open', 'in_progress']),
            default => null,
        };
    }

    /**
     * @param  Builder<IncidentTicket>  $query
     * @return Builder<IncidentTicket>
     */
    private function orderedQueue(Builder $query, User $actor, Team $team): Builder
    {
        return $query
            ->orderByRaw("CASE incident_tickets.status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE incident_tickets.severity WHEN 'emergency' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->orderByDesc(
                FarmActivity::visibleTo($actor, $team)
                    ->select('activity_date')
                    ->whereColumn('farm_activities.id', 'incident_tickets.activity_id'),
            )
            ->orderByDesc('incident_tickets.created_at');
    }

    /**
     * @param  Builder<IncidentTicket>  $base
     * @return array{total: int, open: int, in_progress: int, resolved: int, emergency_open: int}
     */
    private function summary(Builder $base): array
    {
        $row = (clone $base)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COUNT(*) FILTER (WHERE incident_tickets.status = 'open') as open_count")
            ->selectRaw("COUNT(*) FILTER (WHERE incident_tickets.status = 'in_progress') as in_progress_count")
            ->selectRaw("COUNT(*) FILTER (WHERE incident_tickets.status = 'resolved') as resolved_count")
            ->selectRaw("COUNT(*) FILTER (WHERE incident_tickets.status IN ('open', 'in_progress') AND incident_tickets.severity = 'emergency') as emergency_open_count")
            ->first();

        return [
            'total' => (int) ($row?->getAttribute('total') ?? 0),
            'open' => (int) ($row?->getAttribute('open_count') ?? 0),
            'in_progress' => (int) ($row?->getAttribute('in_progress_count') ?? 0),
            'resolved' => (int) ($row?->getAttribute('resolved_count') ?? 0),
            'emergency_open' => (int) ($row?->getAttribute('emergency_open_count') ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function row(IncidentTicket $ticket): array
    {
        $severity = IncidentSeverity::fromStored($ticket->severity) ?? IncidentSeverity::Medium;
        $activity = $ticket->activity;
        $farm = $ticket->farm;
        $cycle = $ticket->plantingCycle;
        $block = $ticket->block ?? $cycle?->block;
        $detail = $activity?->incidentDetail;
        $log = $activity?->dailyActivityLog;

        $subblok = $cycle !== null
            ? ($cycle->subblock_label ?? ($cycle->batch_number !== null ? 'Subblok '.$cycle->batch_number : null))
            : null;

        return [
            'id' => $ticket->id,
            'activity_id' => $ticket->activity_id,
            'farm_id' => $ticket->farm_id,
            'farm_name' => $farm?->name,
            'block_id' => $ticket->block_id,
            'block_name' => $block?->name,
            'planting_cycle_id' => $ticket->planting_cycle_id,
            'subblok' => $subblok,
            'location_label' => $this->locationLabel($farm, $block, $cycle),
            'incident_category' => $ticket->incident_category,
            'severity' => $severity->value,
            'severity_label' => $severity->label(),
            'status' => $ticket->status,
            'status_label' => self::STATUS_LABELS[$ticket->status] ?? $ticket->status,
            'description' => $activity?->description,
            'affected_area_ha' => $detail?->affected_area_ha,
            'reporter_name' => $activity?->performer?->name,
            'reported_at' => $activity?->activity_date?->toISOString(),
            'work_date' => $log?->work_date->toDateString()
                ?? $activity?->activity_date?->setTimezone('Asia/Jakarta')->toDateString(),
            'admin_note' => $ticket->admin_note,
            'responded_by_name' => $ticket->respondedBy?->name,
            'responded_at' => $ticket->responded_at?->toISOString(),
            'resolution_note' => $ticket->resolution_note,
            'resolved_by_name' => $ticket->resolvedBy?->name,
            'resolved_at' => $ticket->resolved_at?->toISOString(),
            'resolved_activity_id' => $ticket->resolved_activity_id,
            'resolved_activity_name' => $ticket->resolvedActivity?->activityType?->name,
            'reopen_reason' => $ticket->reopen_reason,
            'reopened_by_name' => $ticket->reopenedBy?->name,
            'reopened_at' => $ticket->reopened_at?->toISOString(),
        ];
    }

    private function locationLabel(?Farm $farm, ?FarmBlock $block, ?PlantingCycle $cycle): string
    {
        $subblok = $cycle !== null
            ? ($cycle->subblock_label ?? ($cycle->batch_number !== null ? 'Subblok '.$cycle->batch_number : null))
            : null;
        $parts = array_filter([$farm?->name, $block?->name, $subblok]);

        return $parts !== [] ? implode(' · ', $parts) : 'Area kebun';
    }

    /** @return array{0: list<string>, 1: string|null} */
    private function compatibleCategoryCodesAndReason(User $actor, Farm $farm, IncidentTicket $ticket): array
    {
        $team = $farm->team;

        if ($ticket->planting_cycle_id !== null) {
            $cycle = PlantingCycle::visibleTo($actor, $team)
                ->where('farm_id', $farm->id)
                ->whereKey($ticket->planting_cycle_id)
                ->first();

            if ($cycle === null || ! in_array($cycle->status, ['planned', 'active'], true)) {
                return [[], 'Subblok untuk insiden ini sudah ditutup. Hubungi Admin atau minta Admin menutup tiket langsung.'];
            }

            return [
                $this->sequence->blockedReason($actor, $cycle, 'maintenance') === null
                    ? ['maintenance', 'other']
                    : ['other'],
                null,
            ];
        }

        if ($ticket->block_id !== null) {
            $block = FarmBlock::visibleTo($actor, $team)
                ->where('farm_id', $farm->id)
                ->where('is_active', true)
                ->whereKey($ticket->block_id)
                ->first();

            if ($block === null) {
                return [[], 'Blok untuk insiden ini tidak aktif. Minta Admin menutup tiket langsung.'];
            }

            return [['land_cleaning', 'soil_preparation', 'base_fertilization', 'other'], null];
        }

        return [['other'], null];
    }

    private function syncIncidentReport(
        IncidentTicket $ticket,
        string $status,
        ?string $actionTaken = null,
        bool $updateActionTaken = false,
        ?string $severity = null,
    ): void {
        $activity = FarmActivity::query()->whereKey($ticket->activity_id)->lockForUpdate()->firstOrFail();
        $activity->status = $status;
        if ($updateActionTaken) {
            $activity->action_taken = $actionTaken;
        }
        $activity->save();

        if ($severity !== null) {
            IncidentDetail::query()
                ->where('activity_id', $ticket->activity_id)
                ->update(['severity' => $severity]);
        }
    }

    private function lockTicket(IncidentTicket $ticket): IncidentTicket
    {
        return IncidentTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
    }

    private function resolveSeverityInput(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $resolved = IncidentSeverity::fromStored((string) $value);
        if (! $resolved instanceof IncidentSeverity) {
            throw ValidationException::withMessages(['severity' => 'Tingkat kedaruratan tidak valid.']);
        }

        return $resolved->value;
    }

    /** @return array<int, string> */
    private function listRelations(): array
    {
        return [
            'farm:id,name',
            'block:id,name',
            'plantingCycle:id,block_id,batch_number,subblock_label',
            'plantingCycle.block:id,name',
            'activity:id,farm_id,planting_cycle_id,block_id,daily_activity_log_id,performed_by,activity_date,description,status,action_taken',
            'activity.performer:id,name',
            'activity.incidentDetail:activity_id,incident_category,severity,affected_area_ha,proposed_solution',
            'activity.dailyActivityLog:id,work_date',
            'respondedBy:id,name',
            'resolvedBy:id,name',
            'resolvedActivity:id,activity_type_id',
            'resolvedActivity.activityType:id,name',
            'reopenedBy:id,name',
        ];
    }

    /** @return array<int, string> */
    private function relations(): array
    {
        return [
            'farm:id,team_id,name,city,district,province',
            'block:id,farm_id,name',
            'plantingCycle:id,farm_id,block_id,batch_number,subblock_label,status,planted_at,cancellation_reason,cancelled_at',
            'plantingCycle.block:id,name',
            'activity.farm',
            'activity.activityType',
            'activity.plantingCycle',
            'activity.performer',
            'activity.incidentDetail',
            'activity.dailyActivityLog',
            'resolvedActivity.farm',
            'resolvedActivity.activityType',
            'resolvedActivity.plantingCycle',
            'resolvedActivity.performer',
            'resolvedActivity.dailyActivityLog',
            'respondedBy:id,name',
            'resolvedBy:id,name',
            'reopenedBy:id,name',
        ];
    }
}
