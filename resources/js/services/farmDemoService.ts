import {
    readDemoStorage,
    writeDemoStorage,
} from '@/composables/useDemoStorage';
import {
    farmDemoSeedData,
    overallFarmSummary,
    type CommodityCatalogItem,
    type FarmDemoState,
    type KebunInsight,
} from '@/data/farmDemo';
import type { ActivityLog } from '@/data/activityLogs';
import type { FinanceTransaction } from '@/data/farmerFinance';

// Bump the demo snapshot version whenever the unified JSON seed changes so an
// existing browser session receives the latest demo data.
const storageKey = 'farm_state_v8';

export type DashboardFinancialSummary = {
    income: number;
    projectedIncome: number;
    expenses: number;
    projectedExpenses: number;
};

export type CommoditySummary = {
    id: string;
    name: string;
    image: string;
    statistics: Array<{
        label: string;
        value: string;
        highlight?: boolean;
    }>;
};

function wait(milliseconds: number): Promise<void> {
    return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
}

function cloneState(state: FarmDemoState): FarmDemoState {
    return structuredClone(state);
}

export async function loadFarmDemoState(): Promise<FarmDemoState> {
    await wait(260);
    const stored = readDemoStorage<FarmDemoState>(
        storageKey,
        cloneState(farmDemoSeedData),
    );

    try {
        if (!window.localStorage.getItem(`demo_v1_${storageKey}`)) {
            writeDemoStorage(storageKey, stored);
        }
    } catch {
        // The seed snapshot is still available when storage is unavailable.
    }

    return cloneState(stored);
}

export async function listFarmKebun(): Promise<FarmDemoState['kebun']> {
    return (await loadFarmDemoState()).kebun;
}

export async function listFarmActivities(): Promise<ActivityLog[]> {
    const state = await loadFarmDemoState();
    const kebunById = new Map(state.kebun.map((item) => [item.id, item]));

    return state.activityLogs.map((activity) => {
        const kebun = kebunById.get(activity.kebunId);
        if (!kebun) return activity;

        return {
            ...activity,
            kebunName: kebun.name,
            city: kebun.city,
            seedType: kebun.seedType,
        };
    });
}

export async function getFarmKebunDetail(
    id: string,
): Promise<
    | (FarmDemoState['kebun'][number] &
          KebunInsight & { position: [number, number] })
    | undefined
> {
    const state = await loadFarmDemoState();
    const kebun = state.kebun.find((item) => item.id === id);
    const insight = state.kebunInsights[id];
    const position = state.kebunLocations[id];

    if (!kebun || !insight || !position) return undefined;

    return { ...kebun, ...insight, position };
}

export async function getFarmMappedKebun(): Promise<
    Array<
        FarmDemoState['kebun'][number] &
            KebunInsight & { position: [number, number] }
    >
> {
    const state = await loadFarmDemoState();

    return state.kebun.flatMap((kebun) => {
        const insight = state.kebunInsights[kebun.id];
        const position = state.kebunLocations[kebun.id];
        return insight && position ? [{ ...kebun, ...insight, position }] : [];
    });
}

export async function getFarmFinance(): Promise<FarmDemoState['finance']> {
    return (await loadFarmDemoState()).finance;
}

function sumTransactions(
    transactions: FinanceTransaction[],
    commodity: string,
    type: FinanceTransaction['type'],
): number {
    return transactions
        .filter(
            (transaction) =>
                transaction.commodity === commodity &&
                transaction.type === type,
        )
        .reduce((total, transaction) => total + transaction.amount, 0);
}

function formatHectare(value: number): string {
    return `${new Intl.NumberFormat('id-ID').format(value)} ha`;
}

function formatTon(value: number): string {
    return `${new Intl.NumberFormat('id-ID').format(value)} Ton`;
}

export async function getFarmCommoditySummaries(): Promise<CommoditySummary[]> {
    const state = await loadFarmDemoState();
    const mappedKebun = state.kebun.map((item) => ({
        ...item,
        insight: state.kebunInsights[item.id],
    }));

    const commoditySummaries = state.commodityCatalog.map(
        (commodity: CommodityCatalogItem) => {
            const seedType =
                commodity.name === 'Rumput' ? 'Rumput' : commodity.name;
            const items = mappedKebun.filter(
                (item) => item.seedType === seedType,
            );
            const plantedArea = items.reduce(
                (total, item) => total + item.usedHectare,
                0,
            );
            const totalArea = items.reduce(
                (total, item) => total + item.availableHectare,
                0,
            );
            const totalWeight = items.reduce(
                (total, item) => total + (item.insight?.weightTon ?? 0),
                0,
            );
            const income = sumTransactions(
                state.finance.transactions,
                commodity.name === 'Rumput' ? 'Rumput odot' : commodity.name,
                'income',
            );

            return {
                id: commodity.id,
                name: commodity.name,
                image: commodity.image,
                statistics: [
                    { label: 'Jumlah', value: `${items.length} Kebun` },
                    {
                        label: 'Luas tanam',
                        value: `${formatHectare(plantedArea)} / ${formatHectare(totalArea)}`,
                    },
                    { label: 'Total panen', value: formatTon(totalWeight) },
                    {
                        label: 'Total pendapatan',
                        value: `Rp ${new Intl.NumberFormat('id-ID').format(income)}`,
                        highlight: true,
                    },
                ],
            };
        },
    );

    const totalPlantedArea = state.kebun.reduce(
        (total, item) => total + item.usedHectare,
        0,
    );
    const totalFarmArea = state.kebun.reduce(
        (total, item) => total + item.availableHectare,
        0,
    );

    const overall: CommoditySummary = {
        ...overallFarmSummary,
        statistics: [
            { label: 'Jumlah', value: `${state.kebun.length} Kebun` },
            {
                label: 'Luas Total',
                value: `${formatHectare(totalPlantedArea)} / ${formatHectare(totalFarmArea)}`,
            },
            {
                label: 'Total tanam',
                value: `${state.kebun.filter((item) => item.usedHectare > 0).length} Kebun`,
            },
        ],
    };

    return [overall, ...commoditySummaries];
}

export async function getDashboardFinancialSummary(): Promise<DashboardFinancialSummary> {
    const state = await loadFarmDemoState();
    const projectedIncome = Object.values(state.kebunInsights).reduce(
        (total, item) => total + item.projectedRevenue,
        0,
    );

    return {
        income: state.finance.totalRevenue,
        projectedIncome,
        expenses: state.finance.totalExpense,
        projectedExpenses: state.finance.totalExpense,
    };
}

export type FarmReportPeriod = 'month' | 'quarter' | 'year';
export type FarmReportCommodity = 'all' | 'Jagung' | 'Rumput' | 'Kedelai';

export type FarmReportFilters = {
    period: FarmReportPeriod;
    month: number;
    quarter: 1 | 2 | 3 | 4;
    year: number;
    kebunId: string;
    commodity: FarmReportCommodity;
};

export type FarmReport = {
    generatedAt: string;
    periodLabel: string;
    scopeLabel: string;
    summary: {
        totalKebun: number;
        healthy: number;
        attention: number;
        critical: number;
        totalArea: number;
        growth: number;
    };
    finance: {
        revenue: number;
        expenses: number;
        net: number;
        margin: number;
        points: Array<{ label: string; revenue: number; expenses: number }>;
        farms: Array<{
            id: string;
            name: string;
            crop: string;
            phase: string;
            revenue: number;
            expenses: number;
            roi: number;
        }>;
    };
    harvest: {
        totalWeight: number;
        nextDate: string;
        nextKebun: string;
        progress: number;
        items: Array<{
            id: string;
            name: string;
            crop: string;
            date: string;
            growth: number;
            status: string;
        }>;
    };
    growthTimeline: {
        currentStage: string;
        plantedAt: string;
        harvestAt: string;
        lastUpdatedAt: string;
        observedKebunCount: number;
        observedUpdateCount: number;
    };
    growthStages: Array<{
        label: string;
        percent: number;
        count: number;
        active: boolean;
    }>;
    risks: Array<{
        id: string;
        name: string;
        status: string;
        message: string;
    }>;
    activities: Array<{
        id: string;
        typeLabel: string;
        kebunName: string;
        timestamp: string;
        officerName: string;
    }>;
    ai: {
        finance: string;
        farm: string;
    };
};

const reportMonthNumbers: Record<string, number> = {
    Jan: 1,
    Feb: 2,
    Mar: 3,
    Apr: 4,
    Mei: 5,
    Jun: 6,
    Jul: 7,
    Agu: 8,
    Sep: 9,
    Okt: 10,
    Nov: 11,
    Des: 12,
};

const reportMonthLabels: Record<number, string> = {
    1: 'Januari',
    2: 'Februari',
    3: 'Maret',
    4: 'April',
    5: 'Mei',
    6: 'Juni',
    7: 'Juli',
    8: 'Agustus',
    9: 'September',
    10: 'Oktober',
    11: 'November',
    12: 'Desember',
};

function reportMonthNumber(value: string): number {
    return reportMonthNumbers[value] ?? 0;
}

function reportDateNumber(value: string): number {
    const match = value.match(/^(\d{1,2})\s+(\w+)\s+(\d{4})/);
    if (!match) return 0;
    const monthNames: Record<string, number> = {
        januari: 1,
        februari: 2,
        maret: 3,
        april: 4,
        mei: 5,
        juni: 6,
        juli: 7,
        agustus: 8,
        september: 9,
        oktober: 10,
        november: 11,
        desember: 12,
    };
    return (
        Number(match[3]) * 10000 +
        (monthNames[match[2].toLowerCase()] ?? 0) * 100 +
        Number(match[1])
    );
}

function reportStage(growth: number): number {
    if (growth >= 90) return 5;
    if (growth >= 80) return 4;
    if (growth >= 60) return 3;
    if (growth >= 40) return 2;
    return 1;
}

function reportGrowthFromActivity(activity: ActivityLog): number {
    if (activity.type !== 'pengecekan-pertumbuhan') return 0;
    if (activity.daysAfterPlanting >= 90) return 100;
    if (activity.daysAfterPlanting >= 60) return 80;
    if (activity.daysAfterPlanting >= 30) return 60;
    if (activity.daysAfterPlanting >= 10) return 40;
    return 20;
}

function reportActivityMatchesPeriod(
    timestamp: string,
    filters: FarmReportFilters,
    months: number[],
): boolean {
    const date = reportDateNumber(timestamp);
    const year = Math.floor(date / 10000);
    const month = Math.floor((date % 10000) / 100);
    return year === filters.year && months.includes(month);
}

function reportPeriodMonths(filters: FarmReportFilters): number[] {
    if (filters.period === 'year') {
        return Array.from({ length: 12 }, (_, index) => index + 1);
    }
    if (filters.period === 'quarter') {
        const start = (filters.quarter - 1) * 3 + 1;
        return [start, start + 1, start + 2];
    }
    return [filters.month];
}

export function buildFarmReport(
    state: FarmDemoState,
    filters: FarmReportFilters,
): FarmReport {
    const kebunWithInsight = state.kebun
        .map((item) => ({ item, insight: state.kebunInsights[item.id] }))
        .filter(({ item, insight }) => {
            const matchesKebun =
                filters.kebunId === 'all' || item.id === filters.kebunId;
            const matchesCommodity =
                filters.commodity === 'all' ||
                item.seedType === filters.commodity;
            return matchesKebun && matchesCommodity && Boolean(insight);
        })
        .map(({ item, insight }) => ({ item, insight: insight! }));

    const totalKebun = kebunWithInsight.length;
    const healthy = kebunWithInsight.filter(
        ({ item }) => item.healthStatus === 'Sehat',
    ).length;
    const attention = kebunWithInsight.filter(
        ({ item }) => item.healthStatus === 'Perlu Perhatian',
    ).length;
    const critical = kebunWithInsight.filter(
        ({ item }) => item.healthStatus === 'Kritis',
    ).length;
    const totalArea = kebunWithInsight.reduce(
        (total, { item }) => total + item.usedHectare,
        0,
    );
    const months = reportPeriodMonths(filters);
    const growthUpdates = state.activityLogs
        .filter(
            (activity) =>
                activity.type === 'pengecekan-pertumbuhan' &&
                kebunWithInsight.some(
                    ({ item }) => item.id === activity.kebunId,
                ) &&
                reportActivityMatchesPeriod(
                    activity.timestamp,
                    filters,
                    months,
                ),
        )
        .sort(
            (a, b) =>
                reportDateNumber(b.timestamp) - reportDateNumber(a.timestamp),
        );
    const latestGrowthByKebun = new Map<string, ActivityLog>();
    growthUpdates.forEach((activity) => {
        if (!latestGrowthByKebun.has(activity.kebunId)) {
            latestGrowthByKebun.set(activity.kebunId, activity);
        }
    });
    const growthSnapshots = [...latestGrowthByKebun.values()].map(
        (activity) => ({
            activity,
            growth: reportGrowthFromActivity(activity),
        }),
    );
    const growth = growthSnapshots.length
        ? Math.round(
              growthSnapshots.reduce(
                  (total, snapshot) => total + snapshot.growth,
                  0,
              ) / growthSnapshots.length,
          )
        : 0;
    const transactions = state.finance.transactions.filter((transaction) => {
        const matchesCommodity =
            filters.commodity === 'all' ||
            transaction.commodity ===
                (filters.commodity === 'Rumput'
                    ? 'Rumput odot'
                    : filters.commodity);
        return (
            matchesCommodity &&
            months.includes(reportMonthNumber(transaction.date.month))
        );
    });
    const selectedSingleKebun =
        filters.kebunId !== 'all' && kebunWithInsight.length === 1;
    const revenue = selectedSingleKebun
        ? kebunWithInsight[0].insight.revenue
        : transactions
              .filter((transaction) => transaction.type === 'income')
              .reduce((total, transaction) => total + transaction.amount, 0);
    const expenses = selectedSingleKebun
        ? kebunWithInsight[0].insight.expenses
        : transactions
              .filter((transaction) => transaction.type === 'expense')
              .reduce((total, transaction) => total + transaction.amount, 0);
    const net = revenue - expenses;
    const financePoints = months.map((month) => {
        const monthTransactions = transactions.filter(
            (transaction) =>
                reportMonthNumber(transaction.date.month) === month,
        );
        return {
            label: reportMonthLabels[month] ?? `Bulan ${month}`,
            revenue: monthTransactions
                .filter((transaction) => transaction.type === 'income')
                .reduce((total, transaction) => total + transaction.amount, 0),
            expenses: monthTransactions
                .filter((transaction) => transaction.type === 'expense')
                .reduce((total, transaction) => total + transaction.amount, 0),
        };
    });
    const financeFarms = kebunWithInsight.map(({ item, insight }) => ({
        id: item.id,
        name: item.name,
        crop: item.seedType === 'Rumput' ? 'Rumput odot' : item.seedType,
        phase:
            insight.growth >= 80
                ? 'Menjelang panen'
                : insight.growth >= 60
                  ? 'Vegetatif aktif'
                  : 'Pertumbuhan awal',
        revenue: insight.revenue,
        expenses: insight.expenses,
        roi: insight.expenses
            ? Math.round(
                  ((insight.revenue - insight.expenses) / insight.expenses) *
                      100,
              )
            : 0,
    }));

    const harvestItems = kebunWithInsight
        .map(({ item, insight }) => {
            const growthUpdate = latestGrowthByKebun.get(item.id);
            const currentGrowth = growthUpdate
                ? reportGrowthFromActivity(growthUpdate)
                : undefined;
            return {
                id: item.id,
                name: item.name,
                crop: item.seedType,
                date: insight.harvestAt,
                growth: currentGrowth ?? 0,
                status:
                    currentGrowth === undefined
                        ? 'Belum ada update'
                        : currentGrowth >= 80
                          ? 'Segera panen'
                          : currentGrowth < 50
                            ? 'Perlu perhatian'
                            : 'Tumbuh baik',
            };
        })
        .sort((a, b) => reportDateNumber(a.date) - reportDateNumber(b.date));
    const risks = kebunWithInsight
        .filter(({ item }) => item.healthStatus !== 'Sehat')
        .map(({ item, insight }) => ({
            id: item.id,
            name: item.name,
            status: item.healthStatus,
            message: insight.risk,
        }))
        .slice(0, 3);
    const stageLabels = [
        'Kecambah',
        'Bibit awal',
        'Vegetatif aktif',
        'Pembungaan',
        'Pengisian & panen',
    ];
    const growthStages = stageLabels.map((label, index) => ({
        label,
        percent: (index + 1) * 20,
        count: kebunWithInsight.filter(({ item }) => {
            const update = latestGrowthByKebun.get(item.id);
            return (
                update &&
                reportStage(reportGrowthFromActivity(update)) === index + 1
            );
        }).length,
        active: (index + 1) * 20 <= growth,
    }));
    const activities = state.activityLogs
        .filter((activity) =>
            kebunWithInsight.some(({ item }) => item.id === activity.kebunId),
        )
        .sort(
            (a, b) =>
                reportDateNumber(b.timestamp) - reportDateNumber(a.timestamp),
        )
        .slice(0, 4)
        .map((activity) => ({
            id: activity.id,
            typeLabel: activity.typeLabel,
            kebunName:
                state.kebun.find((item) => item.id === activity.kebunId)
                    ?.name ?? activity.kebunName,
            timestamp: activity.timestamp,
            officerName: activity.officerName,
        }));
    const firstRisk = risks[0];
    const firstInsight = kebunWithInsight[0]?.insight;
    const farmProgressText = growthSnapshots.length
        ? `Update fase terakhir ${growthUpdates[0].timestamp}: ${stageLabels[Math.max(0, reportStage(growth) - 1)]}.`
        : 'Belum ada update pengecekan pertumbuhan pada periode ini.';
    const earliestPlanting = [...kebunWithInsight]
        .sort(
            (a, b) =>
                reportDateNumber(a.insight.plantedAt) -
                reportDateNumber(b.insight.plantedAt),
        )
        .at(0)?.insight.plantedAt;
    const scopeLabel =
        filters.kebunId === 'all'
            ? filters.commodity === 'all'
                ? 'Semua kebun'
                : filters.commodity
            : (state.kebun.find((item) => item.id === filters.kebunId)?.name ??
              'Kebun terpilih');

    return {
        generatedAt: new Date().toLocaleString('id-ID'),
        periodLabel:
            filters.period === 'year'
                ? `Tahun ${filters.year}`
                : filters.period === 'month'
                  ? `${reportMonthLabels[filters.month]} ${filters.year}`
                  : `Q${filters.quarter} · ${reportMonthLabels[months[0]]}–${reportMonthLabels[months[2]]} ${filters.year}`,
        scopeLabel,
        summary: {
            totalKebun,
            healthy,
            attention,
            critical,
            totalArea,
            growth,
        },
        finance: {
            revenue,
            expenses,
            net,
            margin: revenue ? Math.round((net / revenue) * 100) : 0,
            points: financePoints,
            farms: financeFarms,
        },
        harvest: {
            totalWeight: kebunWithInsight.reduce(
                (total, { insight }) => total + insight.weightTon,
                0,
            ),
            nextDate: harvestItems[0]?.date ?? '-',
            nextKebun: harvestItems[0]?.name ?? 'Belum ada data',
            progress: growth,
            items: harvestItems.slice(0, 3),
        },
        growthTimeline: {
            currentStage: growthSnapshots.length
                ? (stageLabels[Math.max(0, reportStage(growth) - 1)] ??
                  'Belum ada fase')
                : 'Belum ada update',
            plantedAt: earliestPlanting ?? '-',
            harvestAt: harvestItems[0]?.date ?? '-',
            lastUpdatedAt: growthUpdates[0]?.timestamp ?? '-',
            observedKebunCount: growthSnapshots.length,
            observedUpdateCount: growthUpdates.length,
        },
        growthStages,
        risks,
        activities,
        ai: {
            finance: `Pendapatan kotor ${new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(revenue)} dan OPEX ${new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(expenses)} menghasilkan bersih ${new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(net)} dengan margin ${revenue ? Math.round((net / revenue) * 100) : 0}%.`,
            farm: `${farmProgressText} ${firstInsight?.nextStep ?? 'Belum ada rekomendasi tindakan untuk cakupan ini.'} ${firstRisk?.message ?? 'Tidak ada risiko kritis pada cakupan ini.'}`.trim(),
        },
    };
}
