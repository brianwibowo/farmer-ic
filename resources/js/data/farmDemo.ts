import rawActivityLogs from '@/data/activityLogs.json';
import rawCommoditySummaries from '@/data/commoditySummaries.json';
import rawKebunInsights from '@/data/kebunInsights.json';
import rawKebunLocations from '@/data/kebunLocations.json';
import {
    sortActivityLogsByDateDesc,
    type ActivityLog,
} from '@/data/activityLogs';
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
    finance: typeof farmerFinanceMock;
    commodityCatalog: CommodityCatalogItem[];
};

const commodityCatalog = rawCommoditySummaries
    .filter((item) => item.id !== 'all')
    .map(({ id, name, image }) => ({ id, name, image }));

// ---------------------------------------------------------------------------
// Activity log.
//
// The growth-check history used to be generated here from kebunInsights.json
// at module load. It now lives in activityLogs.json together with the curated
// entries, so the whole log is one inspectable dataset. Regenerate the checks
// if the planting/harvest anchors in kebunInsights.json change.
// ---------------------------------------------------------------------------

export const farmDemoSeedData: FarmDemoState = {
    kebun: kebunSeedData,
    kebunLocations: Object.fromEntries(
        Object.entries(rawKebunLocations).map(([id, position]) => [
            id,
            [position[0], position[1]] as [number, number],
        ]),
    ),
    kebunInsights: rawKebunInsights as Record<string, KebunInsight>,
    // Sorted at the source so every consumer (any page, any service) sees the
    // log newest-first, even before the service layer applies its own sort.
    activityLogs: sortActivityLogsByDateDesc(rawActivityLogs as ActivityLog[]),
    finance: farmerFinanceMock,
    commodityCatalog,
};
