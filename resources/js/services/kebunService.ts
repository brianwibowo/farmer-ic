import { kebunSeedData, type Kebun } from '@/data/kebun';
import {
    readDemoStorage,
    writeDemoStorage,
} from '@/composables/useDemoStorage';
import kebunLocations from '@/data/kebunLocations.json';
import kebunInsights from '@/data/kebunInsights.json';

type KebunInsights = {
    plantCondition: string;
    projectedRevenue: number;
    revenue: number;
    expenses: number;
    growth: number;
    plantedAt: string;
    harvestAt: string;
    summary: string;
};

export type MappedKebun = Kebun & KebunInsights & { position: [number, number] };

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
    const locations: Record<string, number[]> = kebunLocations;
    const insights: Record<string, KebunInsights> = kebunInsights;

    return kebun.flatMap((item) => {
        const position = locations[item.id];
        const insight = insights[item.id];
        if (!position || !insight) return [];

        return [
            {
                ...item,
                ...insight,
                // Demo locations and crop assessments are bundled frontend data.
                position: [position[0], position[1]] as [number, number],
            },
        ];
    });
}

export async function getKebunDetail(id: string): Promise<MappedKebun | undefined> {
    return (await listMappedKebun()).find((item) => item.id === id);
}
