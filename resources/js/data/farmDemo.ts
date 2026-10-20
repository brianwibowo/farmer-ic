import rawActivityLogs from '@/data/activityLogs.json';
import originDevDemoData from '@/data/farmDemoData.json';
import rawCommoditySummaries from '@/data/commoditySummaries.json';
import rawKebunInsights from '@/data/kebunInsights.json';
import rawKebunLocations from '@/data/kebunLocations.json';
import type { ActivityLog } from '@/data/activityLogs';
import { farmerFinanceMock } from '@/data/farmerFinance';
import { kebunSeedData, type Kebun } from '@/data/kebun';
import type { ReportData } from '@/data/report';

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
    report: ReportData;
};

const originDev = originDevDemoData as { kebun: Kebun[]; kebunLocations: Record<string, [number, number]>; kebunInsights: Record<string, KebunInsight>; activityLogs: ActivityLog[]; commodityCatalog: CommodityCatalogItem[] };

const commodityCatalog = (originDev.commodityCatalog?.length ? originDev.commodityCatalog : rawCommoditySummaries)
    .filter((item) => item.id !== 'all')
    .map(({ id, name, image }) => ({ id, name, image }));

const growthHistorySchedule = [
    {
        month: 'Januari',
        day: 18,
        daysAfterPlanting: 10,
        phase: 'Kecambah',
        height: 8,
    },
    {
        month: 'Februari',
        day: 18,
        daysAfterPlanting: 25,
        phase: 'Bibit awal',
        height: 18,
    },
    {
        month: 'Maret',
        day: 16,
        daysAfterPlanting: 40,
        phase: 'Vegetatif aktif',
        height: 38,
    },
    {
        month: 'April',
        day: 17,
        daysAfterPlanting: 50,
        phase: 'Vegetatif aktif',
        height: 52,
    },
    {
        month: 'Mei',
        day: 16,
        daysAfterPlanting: 55,
        phase: 'Vegetatif aktif',
        height: 62,
    },
    {
        month: 'Juni',
        day: 15,
        daysAfterPlanting: 70,
        phase: 'Pembungaan',
        height: 82,
    },
    {
        month: 'Juli',
        day: 16,
        daysAfterPlanting: 75,
        phase: 'Pembungaan',
        height: 98,
    },
    {
        month: 'Agustus',
        day: 14,
        daysAfterPlanting: 78,
        phase: 'Pembungaan',
        height: 110,
    },
    {
        month: 'September',
        day: 15,
        daysAfterPlanting: 88,
        phase: 'Pengisian & panen',
        height: 125,
    },
    {
        month: 'Oktober',
        day: 12,
        daysAfterPlanting: 96,
        phase: 'Pengisian & panen',
        height: 145,
    },
    {
        month: 'November',
        day: 12,
        daysAfterPlanting: 102,
        phase: 'Pengisian & panen',
        height: 152,
    },
    {
        month: 'Desember',
        day: 10,
        daysAfterPlanting: 108,
        phase: 'Pengisian & panen',
        height: 158,
    },
] as const;

const growthHistorySeedData: ActivityLog[] = kebunSeedData.flatMap(
    (kebun, kebunIndex) =>
        growthHistorySchedule.map((schedule, scheduleIndex) => ({
            id: `act-pertumbuhan-mock-${kebun.id}-${scheduleIndex + 1}`,
            type: 'pengecekan-pertumbuhan' as const,
            typeLabel: 'Pengecekan Fase Pertumbuhan',
            timestamp: `${schedule.day} ${schedule.month} 2026`,
            daysAfterPlanting: schedule.daysAfterPlanting + kebunIndex,
            officerName: ['Wahyu Pratama', 'Slamet Widodo', 'Hendra Kusuma'][
                kebunIndex % 3
            ],
            kebunId: kebun.id,
            kebunName: kebun.name,
            city: kebun.city,
            seedType: kebun.seedType,
            report: {
                kind: 'pengecekan-pertumbuhan' as const,
                growthPhase: schedule.phase,
                averageHeightCm: schedule.height + kebunIndex * 4,
                diseaseIndication: 'Aman' as const,
                pestIndication: 'Aman' as const,
            },
            photos: [
                {
                    id: `photo-pertumbuhan-mock-${kebun.id}-${scheduleIndex + 1}`,
                    label: 'Foto update pertumbuhan',
                    caption: `Update fase ${schedule.phase} dari petugas lapangan.`,
                    image: '/images/activities/tumbuh-tanaman-full.webp',
                },
            ],
        })),
);

export const farmDemoSeedData: FarmDemoState = {
    // origin/dev is the canonical additive demo snapshot; local seed remains the fallback.
    kebun: originDev.kebun?.length ? originDev.kebun : kebunSeedData,
    kebunLocations: Object.fromEntries(
        Object.entries(originDev.kebunLocations?.length ? originDev.kebunLocations : rawKebunLocations).map(([id, position]) => [
            id,
            [position[0], position[1]] as [number, number],
        ]),
    ),
    kebunInsights: originDev.kebunInsights ?? (rawKebunInsights as Record<string, KebunInsight>),
    activityLogs: [
        ...(originDev.activityLogs?.length ? originDev.activityLogs : (rawActivityLogs as ActivityLog[])),
        ...growthHistorySeedData,
    ],
    finance: farmerFinanceMock,
    commodityCatalog,
    report: originDev.report as ReportData,
};
