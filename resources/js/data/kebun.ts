import farmDemoData from './farmDemoData.json';

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
