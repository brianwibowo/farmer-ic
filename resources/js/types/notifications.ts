export type CountItem = { label: string; count: number };
export type HarvestCommodity = {
    name: string;
    target_ton: number | null;
    target_missing: number;
    harvest_ton: number;
    harvest_area_ha: number;
    harvest_count: number;
    harvest_missing: number;
};
export type HarvestSummary = {
    commodities: HarvestCommodity[];
    unidentified_batches: number;
};
export type WeeklyOwnerContent = {
    version: 1;
    kind: 'weekly_owner';
    period: { from: string; to: string };
    totals: HarvestSummary;
    regions: (HarvestSummary & { key: string; name: string })[];
};
export type ActivityBlock = {
    id: string | null;
    name: string;
    activities: CountItem[];
    obstacle_count: number;
    obstacle_summary: string | null;
    incidents: CountItem[];
};
export type WeeklyAdminContent = {
    version: 1;
    kind: 'weekly_admin';
    period: { from: string; to: string };
    farms: {
        id: string;
        name: string;
        activity_count: number;
        blocks: ActivityBlock[];
    }[];
};
export type TaskContent = {
    version: 1;
    kind: 'task_reminder' | 'sop_deviation';
    groups: {
        key: string;
        farm_name: string;
        block_name: string;
        items: {
            name: string;
            count: number;
            scheduled_date: string;
            due_date: string;
            days_before: number;
        }[];
    }[];
};
export type NotificationContent =
    | WeeklyOwnerContent
    | WeeklyAdminContent
    | TaskContent;
export type InboxNotice = {
    id: string;
    type: string | null;
    title: string;
    message: string;
    content: NotificationContent | null;
    tenant_name: string | null;
    is_read: boolean;
    created_at: string;
    date_key: string;
    date_label: string;
    can_read: boolean;
};
