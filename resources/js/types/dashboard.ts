export type DashboardActivity = {
    id: string;
    title: string;
    description: string | null;
    farm: string;
    officerId: string;
    officerName: string;
    date: string;
    status: string;
};

export type DashboardOfficer = {
    id: string;
    name: string;
    avatarUrl: string | null;
    activityCount: number;
    latestActivityAt: string | null;
};

export type DashboardTrend = {
    total: number;
    periodLabel: string;
    series: string[];
    days: Array<{
        label: string;
        date: string;
        value: number;
        values: number[];
    }>;
};

export type DashboardData = {
    generatedAt: string;
    filters: { farm_id: string | null; period: string };
    farmOptions: Array<{ id: string; name: string }>;
    metrics: Array<{
        key: 'farms' | 'area' | 'cycles' | 'risks';
        label: string;
        value: number | string;
        unit: string;
        note: string;
        breakdown?: { blocks: number; subBlocks: number | null };
    }>;
    trend: DashboardTrend;
    cycles: {
        running: number;
        active: number;
        planned: number;
        harvested: number;
        stages: Array<{ code: string; label: string; count: number }>;
        withoutTimeline: number;
    };
    attention: Array<{
        key: string;
        label: string;
        count: number;
        description: string;
    }>;
    commodities: Array<{
        id: string;
        name: string;
        plantedArea: string;
        areaValue: number;
        cycleCount: number;
    }>;
    financial: {
        opexThisMonth: string;
        monthLabel: string;
        projectionCount: number;
        projectedIncome: string;
        projectedExpense: string;
        projectedNet: string;
    };
    landMap: {
        hasCoordinates: boolean;
        farms: Array<{
            id: string;
            name: string;
            location: string;
            area: string;
            latitude: number | null;
            longitude: number | null;
        }>;
    };
    activities: DashboardActivity[];
    officers: DashboardOfficer[];
};
