import type { DashboardData } from './dashboard';

export type OwnerDashboardMapFarm =
    DashboardData['landMap']['farms'][number] & {
        crops: Array<{ id: string; name: string; varieties: string[] }>;
        pic: { id: string; name: string } | null;
        detail_url: string;
    };

export type OwnerDashboardData = Omit<DashboardData, 'landMap'> & {
    landMap: Omit<DashboardData['landMap'], 'farms'> & {
        farms: OwnerDashboardMapFarm[];
    };
};

export type OwnerDashboardLandTable = {
    rows: Array<{
        id: string;
        name: string;
        detail_url: string;
        area_ha: string;
        legality: string | null;
        crops: Array<{ id: string; name: string; varieties: string[] }>;
        progress: {
            completed_stages: number;
            total_stages: number;
            current_stages: string[];
            running_batches: number;
        };
        resume: null | {
            id: string;
            title: string | null;
            description: string | null;
            date: string;
            status: string | null;
            detail_url: string;
        };
    }>;
    total_area_ha: string;
    total: number;
};
