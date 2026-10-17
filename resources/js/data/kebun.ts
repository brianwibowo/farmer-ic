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

export const kebunSeedData: Kebun[] = [
    { id: 'kebun-001', name: 'Kebun Sumber Makmur', city: 'Wonosobo', address: 'Jl. Raya Kejajar Km 4', district: 'Kejajar', usedHectare: 300, availableHectare: 1000, seedType: 'Kedelai', seedTypeDetail: 'Anjasmoro', healthStatus: 'Sehat', image: '/images/farm_fields/kedelai_1.jpg' },
    { id: 'kebun-002', name: 'Kebun Tani Lestari', city: 'Temanggung', address: 'Jl. Parakan-Ngadirejo No. 27', district: 'Parakan', usedHectare: 180, availableHectare: 750, seedType: 'Jagung', seedTypeDetail: 'Hibrida BISI-18', healthStatus: 'Sehat', image: '/images/farm_fields/jagung_1.jpg' },
    { id: 'kebun-003', name: 'Kebun Arum Sari', city: 'Banjarnegara', address: 'Jl. Raya Karangkobar Km 8', district: 'Karangkobar', usedHectare: 420, availableHectare: 1200, seedType: 'Rumput', seedTypeDetail: 'Odot', healthStatus: 'Perlu Perhatian', image: '/images/farm_fields/rumput.avif' },
    { id: 'kebun-004', name: 'Kebun Maju Bersama', city: 'Magelang', address: 'Jl. Salaman-Borobudur Km 5', district: 'Salaman', usedHectare: 95, availableHectare: 500, seedType: 'Kedelai', seedTypeDetail: 'Grobogan', healthStatus: 'Sehat', image: '/images/farm_fields/kedelai_2.webp' },
    { id: 'kebun-005', name: 'Kebun Harapan Jaya', city: 'Purworejo', address: 'Jl. Kutoarjo-Kemiri No. 12', district: 'Purwodadi', usedHectare: 260, availableHectare: 900, seedType: 'Jagung', seedTypeDetail: 'Komposit Lamuru', healthStatus: 'Kritis', image: '/images/farm_fields/jagung_2.jpeg' },
];
