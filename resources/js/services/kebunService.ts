import { kebunSeedData, type Kebun } from '@/data/kebun';
import { readDemoStorage, writeDemoStorage } from '@/composables/useDemoStorage';

const storageKey = 'kebun';

function wait(milliseconds: number): Promise<void> {
    return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
}

export async function listKebun(): Promise<Kebun[]> {
    await wait(400);
    const kebun = readDemoStorage<Kebun[]>(storageKey, kebunSeedData);

    try {
        if (!window.localStorage.getItem(`demo_v1_${storageKey}`)) {
            writeDemoStorage(storageKey, kebunSeedData);
        }
    } catch {
        // The seed data is still returned when storage is unavailable.
    }

    return kebun;
}
