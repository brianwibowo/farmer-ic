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

const INDONESIAN_MONTHS: Record<string, string> = {
    januari: '01',
    februari: '02',
    maret: '03',
    april: '04',
    mei: '05',
    juni: '06',
    juli: '07',
    agustus: '08',
    september: '09',
    oktober: '10',
    november: '11',
    desember: '12',
};

const SHORT_MONTH_LABELS: Record<string, string> = {
    '01': 'Jan',
    '02': 'Feb',
    '03': 'Mar',
    '04': 'Apr',
    '05': 'Mei',
    '06': 'Jun',
    '07': 'Jul',
    '08': 'Agu',
    '09': 'Sep',
    '10': 'Okt',
    '11': 'Nov',
    '12': 'Des',
};

export function parseIndonesianDateToIso(timestamp: string): string | null {
    const clean = timestamp.split('-')[0]?.trim() ?? '';
    const parts = clean.split(/\s+/);
    if (parts.length < 3) return null;

    const day = parts[0].padStart(2, '0');
    const month = INDONESIAN_MONTHS[parts[1].toLocaleLowerCase()];
    const year = parts[2];

    if (!month || !/^\d{4}$/.test(year)) return null;
    return `${year}-${month}-${day}`;
}

export function formatIsoToShortDate(isoDate: string): string {
    const [year, month, day] = isoDate.split('-');
    if (!year || !month || !day) return isoDate;
    const shortMonth = SHORT_MONTH_LABELS[month] ?? month;
    return `${Number(day)} ${shortMonth}`;
}

export type ActivityPeriod = 'all' | 'week' | 'month';

export type ActivityTimeFilter = ActivityPeriod | `month:${string}`;

const FULL_MONTH_NAMES = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

export function formatIsoMonthToLabel(isoMonth: string): string {
    const [year, month] = isoMonth.split('-');
    const monthName = FULL_MONTH_NAMES[Number(month) - 1];
    if (!year || !monthName) {
        return isoMonth;
    }

    return `${monthName} ${year}`;
}

export function formatIsoMonthToShortLabel(isoMonth: string): string {
    const [year, month] = isoMonth.split('-');
    const shortMonth = SHORT_MONTH_LABELS[month];
    if (!year || !shortMonth) {
        return isoMonth;
    }

    return `${shortMonth} ${year}`;
}

export function collectActivityMonths(logs: ActivityLog[]): string[] {
    const months = new Set<string>();

    for (const log of logs) {
        const iso = parseIndonesianDateToIso(log.timestamp);
        if (iso) {
            months.add(iso.slice(0, 7));
        }
    }

    return [...months].sort().reverse();
}

function isoFromDate(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${date.getFullYear()}-${month}-${day}`;
}

export function matchesActivityTimeFilter(
    timestamp: string,
    filter: ActivityTimeFilter,
): boolean {
    if (filter === 'all') {
        return true;
    }

    const itemIso = parseIndonesianDateToIso(timestamp);
    if (!itemIso) {
        return false;
    }

    if (filter.startsWith('month:')) {
        return itemIso.startsWith(filter.slice('month:'.length));
    }

    const today = new Date();
    const start = new Date(
        today.getFullYear(),
        today.getMonth(),
        today.getDate(),
    );

    if (filter === 'week') {
        start.setDate(start.getDate() - 6);
    } else if (filter === 'month') {
        start.setMonth(start.getMonth() - 1);
    }

    return itemIso >= isoFromDate(start);
}
