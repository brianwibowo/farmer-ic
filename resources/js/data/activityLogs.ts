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
    shortLabel: string;
}> = [
    {
        value: 'penanaman-benih',
        label: 'Penanaman Benih',
        shortLabel: 'Penanaman',
    },
    { value: 'pemupukan', label: 'Pemupukan', shortLabel: 'Pemupukan' },
    { value: 'pengairan', label: 'Pengairan', shortLabel: 'Pengairan' },
    {
        value: 'pengecekan-pertumbuhan',
        label: 'Pengecekan Fase Pertumbuhan',
        shortLabel: 'Pertumbuhan',
    },
    { value: 'pestisida', label: 'Pestisida', shortLabel: 'Pestisida' },
    {
        value: 'pelaporan-insiden',
        label: 'Pelaporan Insiden',
        shortLabel: 'Insiden',
    },
    { value: 'panen', label: 'Panen', shortLabel: 'Panen' },
];

export const activityLogSeedData: ActivityLog[] =
    rawActivityLogs as ActivityLog[];
