import rawActivityLogs from '@/data/activityLogs.json';
import rawCommoditySummaries from '@/data/commoditySummaries.json';
import rawKebunInsights from '@/data/kebunInsights.json';
import rawKebunLocations from '@/data/kebunLocations.json';
import type { ActivityLog } from '@/data/activityLogs';
import { farmerFinanceMock } from '@/data/farmerFinance';
import { kebunSeedData, type Kebun } from '@/data/kebun';

export type KebunWeather = {
    condition: string;
    temperature: string;
    humidity: string;
};

export type KebunInsight = {
    plantCondition: string;
    projectedRevenue: number;
    revenue: number;
    expenses: number;
    growth: number;
    plantedAt: string;
    harvestAt: string;
    summary: string;
    weightTon: number;
    aiConfidence: number;
    weather: KebunWeather;
    nextStep: string;
    risk: string;
};

export type CommodityCatalogItem = {
    id: string;
    name: string;
    image: string;
};

export type FarmDemoState = {
    kebun: Kebun[];
    kebunLocations: Record<string, [number, number]>;
    kebunInsights: Record<string, KebunInsight>;
    activityLogs: ActivityLog[];
    finance: typeof farmerFinanceMock;
    commodityCatalog: CommodityCatalogItem[];
};

const commodityCatalog = rawCommoditySummaries
    .filter((item) => item.id !== 'all')
    .map(({ id, name, image }) => ({ id, name, image }));

export const farmDemoSeedData: FarmDemoState = {
    kebun: kebunSeedData,
    kebunLocations: Object.fromEntries(
        Object.entries(rawKebunLocations).map(([id, position]) => [
            id,
            [position[0], position[1]] as [number, number],
        ]),
    ),
    kebunInsights: rawKebunInsights as Record<string, KebunInsight>,
    activityLogs: rawActivityLogs as ActivityLog[],
    finance: farmerFinanceMock,
    commodityCatalog,
};
