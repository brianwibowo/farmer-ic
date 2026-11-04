export type OwnerActivityComparison = {
    currentLabel: string;
    previousLabel: string;
    currentTotal: number;
    previousTotal: number;
    changePercent: number | null;
    buckets: Array<{ label: string; current: number; previous: number }>;
};

export type OwnerHarvestForecast = {
    start: string;
    end: string;
    weeks: Array<{
        label: string;
        start: string;
        end: string;
        batchCount: number;
        areaHa: number;
        estimatedTon: number | null;
        missingWeightCount: number;
    }>;
    overdueCount: number;
    undatedCount: number;
};

export type OwnerPlantingTimeline = {
    start: string;
    end: string;
    rows: Array<{
        id: string;
        label: string;
        farm: string;
        block: string | null;
        commodity: string | null;
        status: string;
        startDate: string;
        endDate: string;
        startKind: 'actual' | 'planned';
        areaHa: number;
        href: string;
        stages: Array<{
            code: string;
            label: string;
            status: string;
            startDate: string | null;
            endDate: string | null;
        }>;
    }>;
    undatedCount: number;
    total: number;
    hasMore: boolean;
};

export type OwnerLandComparison = {
    farms: Array<{
        id: string;
        name: string;
        totalAreaHa: number;
        activeAreaHa: number;
        plannedAreaHa: number;
        href: string;
    }>;
};

export type OwnerRiskOverview = {
    total: number;
    severities: Array<{ key: string; label: string; count: number }>;
    items: Array<{
        id: string;
        description: string;
        recommendation: string | null;
        severity: string;
        farm: string;
        href: string;
    }>;
};

export type OwnerDashboardData = {
    filters: { farm_id: string | null; period: string };
    farmOptions: Array<{ id: string; name: string }>;
    insights: {
        asOf: string;
        batchStatus: Array<{ key: string; label: string; count: number }>;
        activityComparison: OwnerActivityComparison;
        harvestForecast: OwnerHarvestForecast;
        timeline: OwnerPlantingTimeline;
        landUse: OwnerLandComparison;
        risks: OwnerRiskOverview;
    };
};
