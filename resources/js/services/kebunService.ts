import { type Kebun } from '@/data/kebun';
import {
    getFarmKebunDetail,
    getFarmMappedKebun,
    listFarmKebun,
} from '@/services/farmDemoService';
import type { KebunInsight } from '@/data/farmDemo';

export type MappedKebun = Kebun & KebunInsight & { position: [number, number] };

export async function listKebun(): Promise<Kebun[]> {
    return listFarmKebun();
}

export async function listMappedKebun(): Promise<MappedKebun[]> {
    return getFarmMappedKebun();
}

export async function getKebunDetail(
    id: string,
): Promise<MappedKebun | undefined> {
    return getFarmKebunDetail(id);
}
