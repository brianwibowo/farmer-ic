import { kebunSeedData, type Kebun } from '@/data/kebun';
import {
    readDemoStorage,
    writeDemoStorage,
} from '@/composables/useDemoStorage';
import kebunLocations from '@/data/kebunLocations.json';

export type MappedKebun = Kebun & { boundary: [number, number][] };

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

export async function listMappedKebun(): Promise<MappedKebun[]> {
    const kebun = await listKebun();
    const locations: Record<string, number[][]> = kebunLocations;

    return kebun.flatMap((item) => {
        const boundary = locations[item.id];
        if (!boundary) return [];

        return [
            {
                ...item,
                // These bundled coordinates illustrate demo areas, not surveyed land.
                boundary: boundary.map(
                    ([latitude, longitude]) =>
                        [latitude, longitude] as [number, number],
                ),
            },
        ];
    });
}
