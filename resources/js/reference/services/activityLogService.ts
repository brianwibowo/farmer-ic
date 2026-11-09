// Historical source: 73f2555d91d0fb2d6acb7bc70813921edd479ea4 / resources/js/services/activityLogService.ts
import {
    sortActivityLogsByDateDesc,
    type ActivityLog,
    type ActivityType,
} from '@/reference/data/activityLogs';
import { listFarmActivities } from '@/reference/services/farmDemoService';

export async function listActivityLogs(): Promise<ActivityLog[]> {
    return sortActivityLogsByDateDesc(await listFarmActivities());
}

export async function getActivityLogByType(
    type: ActivityType,
): Promise<ActivityLog | undefined> {
    const logs = await listActivityLogs();
    return logs.find((item) => item.type === type);
}
