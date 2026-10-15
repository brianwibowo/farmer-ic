export type SeedType = 'Jagung' | 'Rumput' | 'Kedelai';
export type HealthStatus = 'Sehat' | 'Perlu Perhatian' | 'Kritis';

export type Kebun = {
    id: string;
    name: string;
    city: string;
    usedHectare: number;
    availableHectare: number;
    seedType: SeedType;
    healthStatus: HealthStatus;
    image: string;
};

export const kebunSeedData: Kebun[] = [
    { id: 'kebun-001', name: 'Kebun Sumber Makmur', city: 'Wonosobo', usedHectare: 300, availableHectare: 1000, seedType: 'Kedelai', healthStatus: 'Sehat', image: '/images/kebun-placeholder.svg' },
    { id: 'kebun-002', name: 'Kebun Tani Lestari', city: 'Temanggung', usedHectare: 180, availableHectare: 750, seedType: 'Jagung', healthStatus: 'Sehat', image: '/images/kebun-placeholder.svg' },
    { id: 'kebun-003', name: 'Kebun Arum Sari', city: 'Banjarnegara', usedHectare: 420, availableHectare: 1200, seedType: 'Rumput', healthStatus: 'Perlu Perhatian', image: '/images/kebun-placeholder.svg' },
    { id: 'kebun-004', name: 'Kebun Maju Bersama', city: 'Magelang', usedHectare: 95, availableHectare: 500, seedType: 'Kedelai', healthStatus: 'Sehat', image: '/images/kebun-placeholder.svg' },
    { id: 'kebun-005', name: 'Kebun Harapan Jaya', city: 'Purworejo', usedHectare: 260, availableHectare: 900, seedType: 'Jagung', healthStatus: 'Kritis', image: '/images/kebun-placeholder.svg' },
];
