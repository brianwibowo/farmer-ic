import {
    readDemoStorage,
    writeDemoStorage,
} from '@/composables/useDemoStorage';
import {
    farmDemoSeedData,
    type CommodityCatalogItem,
    type FarmDemoState,
    type KebunInsight,
} from '@/data/farmDemo';
import type { ActivityLog } from '@/data/activityLogs';
import type { FinanceTransaction } from '@/data/farmerFinance';

const storageKey = 'farm_state_v1';

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

    return state.commodityCatalog.map((commodity: CommodityCatalogItem) => {
        const seedType =
            commodity.name === 'Rumput' ? 'Rumput' : commodity.name;
        const items = mappedKebun.filter((item) => item.seedType === seedType);
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
    });
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
