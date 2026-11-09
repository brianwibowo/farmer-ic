// Historical source: 73f2555d91d0fb2d6acb7bc70813921edd479ea4 / resources/js/services/kebunService.ts
import { type Kebun } from '@/reference/data/kebun';
import {
    getFarmKebunDetail,
    getFarmMappedKebun,
    listFarmKebun,
} from '@/reference/services/farmDemoService';
import type { KebunInsight } from '@/reference/data/farmDemo';

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
