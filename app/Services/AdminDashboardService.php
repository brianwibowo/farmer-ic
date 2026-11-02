<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\FarmRisk;
use App\Models\FinancialProjection;
use App\Models\FinancialTransaction;
use App\Models\OperationalTarget;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    /**
     * Build a read-only dashboard from the same scoped records used elsewhere
     * in the admin portal. The service intentionally returns presentation-ready
     * values, while authorization remains at the route/controller boundary.
     *
     * @return array<string, mixed>
     */
    public function summary(User $actor, ?Team $team, array $filters = []): array
    {
        $farmId = $filters['farm_id'] ?? null;
        $period = (int) ($filters['period'] ?? 7);
        $farmOptions = Farm::query()->visibleTo($actor, $team)->orderBy('name')->get(['id', 'name']);
        $farms = Farm::query()->visibleTo($actor, $team)->when($farmId, fn (Builder $q) => $q->whereKey($farmId));
        $weekStart = CarbonImmutable::today()->subDays($period - 1);
        $weekEnd = CarbonImmutable::today()->endOfDay();
        $monthStart = CarbonImmutable::today()->startOfMonth();
        $monthEnd = CarbonImmutable::today()->endOfMonth();

        $activityQuery = FarmActivity::query()->visibleTo($actor, $team)->when($farmId, fn (Builder $q) => $q->where('farm_id', $farmId));
        $mappedBlocks = FarmBlock::query()->visibleTo($actor, $team)->when($farmId, fn (Builder $q) => $q->where('farm_id', $farmId))->count();
        $cycles = PlantingCycle::query()->visibleTo($actor, $team)->when($farmId, fn (Builder $q) => $q->where('farm_id', $farmId));
        $activeCycles = PlantingCycle::query()
            ->visibleTo($actor, $team)
            ->when($farmId, fn (Builder $q) => $q->where('farm_id', $farmId))
            ->where('status', 'active');
        $openRisks = FarmRisk::query()
            ->visibleTo($actor, $team)
            ->when($farmId, fn (Builder $q) => $q->whereHas('plantingCycle', fn (Builder $cycles) => $cycles->where('farm_id', $farmId)))
            ->where(function (Builder $query): void {
                $query->whereNull('status')
                    ->orWhereNotIn('status', ['resolved', 'closed']);
            });
        $financialTransactions = FinancialTransaction::query()->visibleTo($actor, $team)->when($farmId, fn (Builder $q) => $q->where('farm_id', $farmId));
        $currentProjections = FinancialProjection::query()
            ->visibleTo($actor, $team)
            ->when($farmId, fn (Builder $q) => $q->where('farm_id', $farmId))
            ->whereDate('period_start', '<=', CarbonImmutable::today())
            ->whereDate('period_end', '>=', CarbonImmutable::today());
        $recentActivities = (clone $activityQuery)
            ->with(['farm:id,name', 'performer:id,name,avatar_url', 'activityType:id,name'])
            ->orderByDesc('activity_date')
            ->limit(30)
            ->get();
        $activityTotalsByOfficer = (clone $activityQuery)
            ->selectRaw('performed_by, COUNT(*) as activity_count, MAX(activity_date) as latest_activity_at')
            ->groupBy('performed_by')
            ->get()
            ->keyBy('performed_by');
        $officers = User::query()
            ->visibleTo($actor, $team)
            ->when($farmId, fn (Builder $q) => $q->where('assigned_farm_id', $farmId))
            ->with('role:id,name')
            ->where('is_active', true)
            ->whereHas('role', fn (Builder $roles) => $roles->where('name', 'petugas'))
            ->orderBy('name')
            ->get(['id', 'role_id', 'name', 'avatar_url'])
            ->map(function (User $officer) use ($activityTotalsByOfficer): array {
                $summary = $activityTotalsByOfficer->get($officer->id);

                return [
                    'id' => (string) $officer->id,
                    'name' => $officer->name,
                    'avatarUrl' => $officer->avatar_url,
                    'activityCount' => (int) ($summary?->activity_count ?? 0),
                    'latestActivityAt' => $summary?->latest_activity_at === null
                        ? null
                        : CarbonImmutable::parse($summary->latest_activity_at)->locale('id')->isoFormat('D MMM YYYY'),
                ];
            })->values()->all();

        $tasks = OperationalTarget::query()
            ->visibleTo($actor, $team)
            ->when($farmId, fn (Builder $q) => $q->where('farm_id', $farmId))
            ->with(['farm:id,name', 'creator:id,name'])
            ->orderByRaw('target_date asc nulls last')
            ->limit(8)
            ->get()
            ->map(fn (OperationalTarget $task): array => [
                'id' => (string) $task->id,
                'title' => $task->task_name,
                'farm' => $task->farm?->name ?? 'Kebun tidak tersedia',
                'assignee' => $task->creator?->name ?? 'Belum ditentukan',
                'date' => $task->target_date?->locale('id')->isoFormat('D MMM YYYY') ?? 'Tanpa tenggat',
                'status' => $this->taskStatus($task->status),
            ])->values()->all();

        $trendActivities = (clone $activityQuery)->with('activityType:id,code,name')
            ->whereBetween('activity_date', [$weekStart, $weekEnd])->get();
        $legacyStart = CarbonImmutable::today()->subDays(6);
        $activityCounts = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $date = $legacyStart->addDays($offset);
            $activityCounts[] = [
                'label' => $date->locale('id')->isoFormat('dddd'),
                'value' => $trendActivities->filter(fn (FarmActivity $activity): bool => $activity->activity_date->isSameDay($date))->count(),
            ];
        }

        $totalArea = (float) (clone $farms)->sum('total_area_ha');
        $activeArea = (float) (clone $activeCycles)->sum('planted_area_ha');
        $activitiesThisWeek = array_sum(array_column($activityCounts, 'value'));
        $opexThisMonth = (float) (clone $financialTransactions)
            ->where('transaction_type', 'expense')
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->sum('amount');
        $projectedIncome = (float) (clone $currentProjections)->sum('projected_income');
        $projectedExpense = (float) (clone $currentProjections)->sum('projected_expense');
        $mapFarms = (clone $farms)
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'total_area_ha', 'latitude', 'longitude']);
        $commodityCycles = PlantingCycle::query()
            ->visibleTo($actor, $team)
            ->when($farmId, fn (Builder $q) => $q->where('farm_id', $farmId))
            ->whereIn('status', ['active', 'harvested'])
            ->with('variety.commodity')
            ->get();
        $commodities = $commodityCycles
            ->filter(fn (PlantingCycle $cycle): bool => $cycle->variety?->commodity !== null)
            ->groupBy(fn (PlantingCycle $cycle): string => (string) $cycle->variety->commodity->id)
            ->map(function (Collection $cycles): array {
                /** @var PlantingCycle $first */
                $first = $cycles->first();

                return [
                    'id' => (string) $first->variety->commodity->id,
                    'name' => $first->variety->commodity->name,
                    'plantedArea' => $this->decimal((float) $cycles->sum('planted_area_ha')),
                    'areaValue' => (float) $cycles->sum('planted_area_ha'),
                    'cycleCount' => $cycles->count(),
                ];
            })
            ->sortByDesc(fn (array $commodity): float => (float) str_replace(',', '.', str_replace('.', '', $commodity['plantedArea'])))
            ->take(3)
            ->values()
            ->all();

        $activityByFarm = $mapFarms->map(function (Farm $farm) use ($trendActivities, $legacyStart): array {
            $days = [];
            for ($offset = 0; $offset < 7; $offset++) {
                $date = $legacyStart->addDays($offset);
                $days[] = [
                    'label' => $date->locale('id')->isoFormat('dddd'),
                    'value' => $trendActivities->filter(fn (FarmActivity $activity): bool => $activity->farm_id === $farm->id && $activity->activity_date->isSameDay($date))->count(),
                ];
            }

            return ['id' => (string) $farm->id, 'name' => $farm->name, 'days' => $days];
        })->values()->all();

        $runningCycles = (clone $cycles)->whereIn('status', ['planned', 'active']);
        $runningCount = (clone $runningCycles)->count();
        $stageCounts = PlantingCycleStage::query()->visibleTo($actor, $team)
            ->whereIn('planting_cycle_id', (clone $runningCycles)->select('id'))
            ->where('status', 'in_progress')
            ->selectRaw('code, COUNT(*) as total')->groupBy('code')->pluck('total', 'code');
        $stageNames = ['soil_preparation' => 'Persiapan tanah', 'basic_fertilizer' => 'Pupuk dasar', 'planting' => 'Penanaman', 'maintenance' => 'Pemeliharaan', 'harvest' => 'Panen'];
        $stages = collect($stageNames)->map(fn (string $label, string $code): array => ['code' => $code, 'label' => $label, 'count' => (int) ($stageCounts[$code] ?? 0)])->values()->all();
        $landClearing = FarmBlock::query()->visibleTo($actor, $team)
            ->when($farmId, fn (Builder $q) => $q->where('farm_id', $farmId))
            ->where('is_active', true)->whereNull('land_clearing_completed_at')->count();
        $timelineUnknown = max(0, $runningCount - array_sum(array_column($stages, 'count')));
        $trend = $this->activityTrend($trendActivities, $weekStart, $period);

        return [
            'filters' => ['farm_id' => $farmId, 'period' => (string) $period],
            'farmOptions' => $farmOptions->map(fn (Farm $farm): array => ['id' => (string) $farm->id, 'name' => $farm->name])->all(),
            'trend' => $trend,
            'cycles' => [
                'running' => $runningCount,
                'active' => (clone $cycles)->where('status', 'active')->count(),
                'planned' => (clone $cycles)->where('status', 'planned')->count(),
                'harvested' => (clone $cycles)->where('status', 'harvested')->count(),
                'stages' => $stages,
                'withoutTimeline' => $timelineUnknown,
            ],
            'attention' => [
                ['key' => 'risks', 'label' => 'Risiko belum ditutup', 'count' => (clone $openRisks)->count(), 'description' => 'Risiko berstatus selain resolved atau closed pada kebun dalam cakupan akses.'],
                ['key' => 'harvest', 'label' => 'Estimasi panen 14 hari', 'count' => (clone $activeCycles)->whereBetween('estimated_harvest_at', [CarbonImmutable::today(), CarbonImmutable::today()->addDays(14)])->count(), 'description' => 'Batch aktif dengan estimasi panen mulai hari ini hingga 14 hari ke depan.'],
                ['key' => 'clearing', 'label' => 'Blok land clearing', 'count' => $landClearing, 'description' => 'Blok aktif yang pembersihan lahannya belum selesai. Tahap ini dicatat pada tingkat blok.'],
                ['key' => 'maintenance', 'label' => 'Batch pemeliharaan', 'count' => (int) ($stageCounts['maintenance'] ?? 0), 'description' => 'Batch berjalan dengan tahap pemeliharaan berstatus sedang berlangsung.'],
                ['key' => 'officers', 'label' => 'Petugas lapangan aktif', 'count' => count($officers), 'description' => 'Akun petugas aktif yang dapat dilihat dalam cakupan akses saat ini.'],
            ],
            'generatedAt' => CarbonImmutable::now()->locale('id')->isoFormat('D MMMM YYYY'),
            'metrics' => [
                ['key' => 'farms', 'label' => 'Total kebun', 'value' => (clone $farms)->count(), 'unit' => 'kebun', 'note' => 'Kebun dalam cakupan akses Anda.'],
                [
                    'key' => 'area',
                    'label' => 'Luas terkelola',
                    'value' => $this->decimal($totalArea),
                    'unit' => 'ha',
                    'note' => 'Akumulasi luas kebun yang dapat dipantau.',
                    'breakdown' => [
                        'blocks' => $mappedBlocks,
                        'subBlocks' => (clone $cycles)->whereNotNull('planting_round_id')->count(),
                    ],
                ],
                ['key' => 'cycles', 'label' => 'Batch berjalan', 'value' => $runningCount, 'unit' => 'batch', 'note' => $this->decimal($activeArea).' ha pada batch aktif.'],
                ['key' => 'risks', 'label' => 'Risiko terbuka', 'value' => (clone $openRisks)->count(), 'unit' => 'risiko', 'note' => 'Perlu ditinjau sebelum status ditutup.'],
            ],
            'activity' => [
                'total' => $activitiesThisWeek,
                'days' => $activityCounts,
                'farms' => $activityByFarm,
            ],
            'commodities' => $commodities,
            'financial' => [
                'opexThisMonth' => $this->currency($opexThisMonth),
                'monthLabel' => $monthStart->locale('id')->isoFormat('MMMM YYYY'),
                'projectionCount' => (clone $currentProjections)->count(),
                'projectedIncome' => $this->currency($projectedIncome),
                'projectedExpense' => $this->currency($projectedExpense),
                'projectedNet' => $this->currency($projectedIncome - $projectedExpense),
            ],
            'landMap' => $this->landMap($mapFarms),
            'activities' => $recentActivities->map(fn (FarmActivity $activity): array => [
                'id' => (string) $activity->id,
                'title' => $activity->activityType?->name ?? 'Aktivitas lapangan',
                'description' => $activity->description,
                'farm' => $activity->farm?->name ?? 'Kebun tidak tersedia',
                'officerId' => (string) $activity->performed_by,
                'officerName' => $activity->performer?->name ?? 'Petugas tidak tersedia',
                'date' => $activity->activity_date->locale('id')->isoFormat('D MMM YYYY'),
                'status' => $activity->status ?? 'Belum ada status',
            ])->values()->all(),
            'officers' => $officers,
            'tasks' => $tasks,
        ];
    }

    /** @param Collection<int, FarmActivity> $activities */
    private function activityTrend(Collection $activities, CarbonImmutable $start, int $period): array
    {
        $bucketSize = $period === 30 ? 5 : 1;
        $days = [];
        for ($offset = 0; $offset < $period; $offset += $bucketSize) {
            $from = $start->addDays($offset);
            $to = $from->addDays($bucketSize - 1)->endOfDay();
            $values = [0, 0, 0];
            foreach ($activities as $activity) {
                if (! $activity->activity_date->betweenIncluded($from, $to)) {
                    continue;
                }
                $index = match ($activity->activityType?->code) {
                    'harvest' => 2,
                    'planting' => 0,
                    default => 1,
                };
                $values[$index]++;
            }
            $days[] = [
                'label' => $period === 7 ? $from->locale('id')->isoFormat('ddd') : $from->locale('id')->isoFormat('D MMM'),
                'date' => $from->toDateString(),
                'values' => $values,
                'value' => array_sum($values),
            ];
        }

        return [
            'days' => $days,
            'total' => array_sum(array_column($days, 'value')),
            'periodLabel' => $start->locale('id')->isoFormat('D MMM').' – '.CarbonImmutable::today()->locale('id')->isoFormat('D MMM YYYY'),
            'series' => ['Penanaman', 'Perawatan & lainnya', 'Panen'],
        ];
    }

    /**
     * Farm rows provide map points, not boundary polygons. Keep the original
     * coordinates intact so the Leaflet client can display a real map.
     *
     * @param  Collection<int, Farm>  $farms
     * @return array{hasCoordinates: bool, farms: array<int, array<string, mixed>>}
     */
    private function landMap(Collection $farms): array
    {
        return [
            'hasCoordinates' => $farms->contains(fn (Farm $farm): bool => $farm->latitude !== null && $farm->longitude !== null),
            'farms' => $farms->values()->map(function (Farm $farm): array {
                return [
                    'id' => (string) $farm->id,
                    'name' => $farm->name,
                    'location' => $farm->city ?? 'Lokasi belum diisi',
                    'area' => $this->decimal((float) $farm->total_area_ha),
                    'latitude' => $farm->latitude === null ? null : (float) $farm->latitude,
                    'longitude' => $farm->longitude === null ? null : (float) $farm->longitude,
                ];
            })->all(),
        ];
    }

    private function taskStatus(?string $status): string
    {
        return match ($status) {
            'completed', 'done' => 'Selesai',
            'in_progress' => 'Dalam proses',
            default => 'Belum dimulai',
        };
    }

    private function decimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private function currency(float $value): string
    {
        return 'Rp'.number_format($value, 0, ',', '.');
    }
}
