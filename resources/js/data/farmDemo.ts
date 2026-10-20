import farmDemoData from '@/data/farmDemoData.json';
import type { ActivityLog } from '@/data/activityLogs';
import { farmerFinanceMock, type FarmerFinance } from '@/data/farmerFinance';
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
    statistics?: Array<{
        label: string;
        value: string;
        highlight?: boolean;
    }>;
};

export const overallFarmSummary: CommodityCatalogItem = {
    id: 'all',
    name: 'Keseluruhan Kebun',
    image: '/images/commodities/kebun.png',
};

export type FarmDemoState = {
    kebun: Kebun[];
    kebunLocations: Record<string, [number, number]>;
    kebunInsights: Record<string, KebunInsight>;
    activityLogs: ActivityLog[];
    finance: FarmerFinance;
    commodityCatalog: CommodityCatalogItem[];
};

const kebunLocations = Object.fromEntries(
    Object.entries(farmDemoData.kebunLocations).map(([id, position]) => [
        id,
        [position[0], position[1]] as [number, number],
    ]),
) as Record<string, [number, number]>;

const commodityCatalog = farmDemoData.commodityCatalog
    .filter((item) => item.id !== 'all')
    .map(({ id, name, image, statistics }) => ({
        id,
        name,
        image,
        statistics,
    }));

export const farmDemoSeedData: FarmDemoState = {
    kebun: kebunSeedData,
    kebunLocations,
    kebunInsights: farmDemoData.kebunInsights as Record<string, KebunInsight>,
    activityLogs: farmDemoData.activityLogs as ActivityLog[],
    finance: farmerFinanceMock,
    commodityCatalog,
};
