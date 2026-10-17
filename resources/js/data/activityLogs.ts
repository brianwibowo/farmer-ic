import rawActivityLogs from '@/data/activityLogs.json';
import type { SeedType } from '@/data/kebun';

export type ActivityType =
    | 'penanaman-benih'
    | 'pemupukan'
    | 'pengairan'
    | 'pengecekan-pertumbuhan'
    | 'pestisida'
    | 'pelaporan-insiden'
    | 'panen';

export type DisturbanceLevel = 'Aman' | 'Ringan' | 'Berat';
export type IncidentSeverity = 'Bisa Ditangani' | 'Darurat';

export type PenanamanBenihReport = {
    kind: 'penanaman-benih';
    brandOrVariety: string;
    quantityKg: number;
    plantingPattern: string;
    areaSize: string;
};

export type PemupukanReport = {
    kind: 'pemupukan';
    fertilizationStage: string;
    fertilizerType: string;
    brandName: string;
    subsidyStatus: 'Subsidi' | 'Non-Subsidi';
    applicationMethod: string;
    usageAmount: string;
};

export type PengairanReport = {
    kind: 'pengairan';
    waterSource: string;
    irrigationMethod: string;
    machineType: string;
    durationHours: number;
};

export type PengecekanPertumbuhanReport = {
    kind: 'pengecekan-pertumbuhan';
    growthPhase: string;
    averageHeightCm: number;
    diseaseIndication: DisturbanceLevel;
    pestIndication: DisturbanceLevel;
};

export type PestisidaReport = {
    kind: 'pestisida';
    pesticideCategory: 'Insektisida' | 'Herbisida' | 'Fungisida';
    purpose: 'Preventif' | 'Korektif';
    brandName: string;
    activeIngredient: string;
    dosagePerTank: string;
    tankSizeLiters: number;
    tankCount: number;
};

export type PelaporanInsidenReport = {
    kind: 'pelaporan-insiden';
    incidentCategory: string;
    severity: IncidentSeverity;
    affectedAreaHa: number;
    proposedSolution: string;
};

export type PanenReport = {
    kind: 'panen';
    harvestedArea: string;
    grossWeight: string;
    quality: {
        moistureContent: string;
        color: string;
        ripeness: string;
        cleanliness: string;
    };
};

export type ActivityReportData =
    | PenanamanBenihReport
    | PemupukanReport
    | PengairanReport
    | PengecekanPertumbuhanReport
    | PestisidaReport
    | PelaporanInsidenReport
    | PanenReport;

export type ActivityPhoto = {
    id: string;
    label: string;
    caption: string;
    image: string;
};

export type ActivityLog = {
    id: string;
    type: ActivityType;
    typeLabel: string;
    timestamp: string;
    daysAfterPlanting: number;
    officerName: string;
    kebunId: string;
    kebunName: string;
    city: string;
    seedType: SeedType;
    report: ActivityReportData;
    photos: ActivityPhoto[];
};

export const activityTypeOptions: Array<{
    value: ActivityType;
    label: string;
}> = [
    { value: 'penanaman-benih', label: 'Penanaman Benih' },
    { value: 'pemupukan', label: 'Pemupukan' },
    { value: 'pengairan', label: 'Pengairan' },
    { value: 'pengecekan-pertumbuhan', label: 'Pengecekan Fase Pertumbuhan' },
    { value: 'pestisida', label: 'Pestisida' },
    { value: 'pelaporan-insiden', label: 'Pelaporan Insiden' },
    { value: 'panen', label: 'Panen' },
];

export const activityLogSeedData: ActivityLog[] =
    rawActivityLogs as ActivityLog[];

const indonesianMonths: Record<string, string> = {
    Januari: '01',
    Februari: '02',
    Maret: '03',
    April: '04',
    Mei: '05',
    Juni: '06',
    Juli: '07',
    Agustus: '08',
    September: '09',
    Oktober: '10',
    November: '11',
    Desember: '12',
};

export function parseIndonesianDateToIso(value: string): string | undefined {
    const match = value.match(/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/);
    if (!match) return undefined;

    const [, day, monthName, year] = match;
    const month = indonesianMonths[monthName];
    if (!month) return undefined;

    return `${year}-${month}-${day.padStart(2, '0')}`;
}
