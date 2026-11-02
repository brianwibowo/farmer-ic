<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmRisk;
use App\Models\BlockMappingDetail;
use App\Models\FinancialProjection;
use App\Models\FinancialTransaction;
use App\Models\OperationalTarget;
use App\Models\PlantingCycle;
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
    public function summary(User $actor, ?Team $team): array
    {
        $farms = Farm::query()->visibleTo($actor, $team);
        $weekStart = CarbonImmutable::today()->subDays(6);
        $weekEnd = CarbonImmutable::today()->endOfDay();
        $monthStart = CarbonImmutable::today()->startOfMonth();
        $monthEnd = CarbonImmutable::today()->endOfMonth();

        $activityQuery = FarmActivity::query()->visibleTo($actor, $team);
        $mappedBlocks = BlockMappingDetail::query()->visibleTo($actor, $team)->count();
        $activeCycles = PlantingCycle::query()
            ->visibleTo($actor, $team)
            ->where('status', 'active');
        $openRisks = FarmRisk::query()
            ->visibleTo($actor, $team)
            ->where(function (Builder $query): void {
                $query->whereNull('status')
                    ->orWhereNotIn('status', ['resolved', 'closed']);
            });
        $financialTransactions = FinancialTransaction::query()->visibleTo($actor, $team);
        $currentProjections = FinancialProjection::query()
            ->visibleTo($actor, $team)
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

        $activityCounts = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $date = $weekStart->addDays($offset);
            $activityCounts[] = [
                'label' => $date->locale('id')->isoFormat('dddd'),
                'value' => (clone $activityQuery)
                    ->whereDate('activity_date', $date->toDateString())
                    ->count(),
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
                    'cycleCount' => $cycles->count(),
                ];
            })
            ->sortByDesc(fn (array $commodity): float => (float) str_replace(',', '.', str_replace('.', '', $commodity['plantedArea'])))
            ->take(3)
            ->values()
            ->all();

        $activityByFarm = $mapFarms->map(function (Farm $farm) use ($activityQuery, $weekStart): array {
            $days = [];
            for ($offset = 0; $offset < 7; $offset++) {
                $date = $weekStart->addDays($offset);
                $days[] = [
                    'label' => $date->locale('id')->isoFormat('dddd'),
                    'value' => (clone $activityQuery)
                        ->where('farm_id', $farm->id)
                        ->whereDate('activity_date', $date->toDateString())
                        ->count(),
                ];
            }

            return ['id' => (string) $farm->id, 'name' => $farm->name, 'days' => $days];
        })->values()->all();

        return [
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
                        'subBlocks' => null,
                    ],
                ],
                ['key' => 'cycles', 'label' => 'Siklus aktif', 'value' => (clone $activeCycles)->count(), 'unit' => 'siklus', 'note' => $this->decimal($activeArea).' ha sedang ditanam.'],
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

    /**
     * Farm rows provide map points, not boundary polygons. Keep the original
     * coordinates intact so the Leaflet client can display a real map.
     *
     * @param Collection<int, Farm> $farms
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
