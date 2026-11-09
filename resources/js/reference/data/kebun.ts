// Historical source: 73f2555d91d0fb2d6acb7bc70813921edd479ea4 / resources/js/data/kebun.ts
import farmDemoData from '@/reference/fixtureData';

export type SeedType = 'Jagung' | 'Rumput' | 'Kedelai';
export type HealthStatus = 'Sehat' | 'Perlu Perhatian' | 'Kritis';

export type Kebun = {
    id: string;
    name: string;
    city: string;
    district: string;
    address: string;
    usedHectare: number;
    availableHectare: number;
    seedType: SeedType;
    seedTypeDetail?: string;
    healthStatus: HealthStatus;
    image: string;
};

export const kebunSeedData: Kebun[] = farmDemoData.kebun as Kebun[];
