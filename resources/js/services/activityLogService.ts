import {
    readDemoStorage,
    writeDemoStorage,
} from '@/composables/useDemoStorage';
import {
    activityLogSeedData,
    type ActivityLog,
    type ActivityType,
} from '@/data/activityLogs';

const storageKey = 'activity_logs_v4';

function wait(milliseconds: number): Promise<void> {
    return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
}

export async function listActivityLogs(): Promise<ActivityLog[]> {
    await wait(420);
    const logs = readDemoStorage<ActivityLog[]>(
        storageKey,
        activityLogSeedData,
    );

    try {
        if (!window.localStorage.getItem(`demo_v1_${storageKey}`)) {
            writeDemoStorage(storageKey, activityLogSeedData);
        }
    } catch {
        // The seed data is still returned when storage is unavailable.
    }

    return logs;
}

export async function getActivityLogByType(
    type: ActivityType,
): Promise<ActivityLog | undefined> {
    const logs = await listActivityLogs();
    return logs.find((item) => item.type === type);
}
