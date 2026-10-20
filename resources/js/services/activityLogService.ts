import {
    sortActivityLogsByDateDesc,
    type ActivityLog,
    type ActivityType,
} from '@/data/activityLogs';
import { listFarmActivities } from '@/services/farmDemoService';

export async function listActivityLogs(): Promise<ActivityLog[]> {
    return sortActivityLogsByDateDesc(await listFarmActivities());
}

export async function getActivityLogByType(
    type: ActivityType,
): Promise<ActivityLog | undefined> {
    const logs = await listActivityLogs();
    return logs.find((item) => item.type === type);
}
