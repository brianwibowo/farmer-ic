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
