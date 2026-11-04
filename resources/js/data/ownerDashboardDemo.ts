import type {
    OwnerActivityComparison,
    OwnerDashboardData,
    OwnerPlantingTimeline,
} from '@/types/owner-dashboard';

// Visual illustration only. These fictional records never represent a tenant's
// live operations and are intentionally kept separate from backend read paths.
const AS_OF = '2026-11-04';
const DAY_MS = 86_400_000;
const AS_OF_TIME = Date.parse(`${AS_OF}T00:00:00Z`);

const farms = [
    { id: 'demo-sukamaju', name: 'Kebun Sukamaju', totalAreaHa: 12.5 },
    { id: 'demo-bukit-asri', name: 'Kebun Bukit Asri', totalAreaHa: 9.2 },
    { id: 'demo-lembah-hijau', name: 'Kebun Lembah Hijau', totalAreaHa: 7.8 },
];

export const ownerDashboardDemoFarms = farms.map(({ id, name }) => ({
    id,
    name,
}));

type DemoBatch = {
    id: string;
    label: string;
    farmId: string;
    block: string;
    commodity: string;
    status: 'active' | 'planned' | 'harvested' | 'cancelled';
    startDate: string;
    harvestDate: string | null;
    areaHa: number;
    estimatedTon: number | null;
};

const batches: DemoBatch[] = [
    {
        id: 'demo-sm-01',
        label: 'Batch SM-01',
        farmId: 'demo-sukamaju',
        block: 'Blok Utara',
        commodity: 'Pakcoy',
        status: 'active',
        startDate: '2026-10-10',
        harvestDate: '2026-11-16',
        areaHa: 1.2,
        estimatedTon: 3.8,
    },
    {
        id: 'demo-sm-02',
        label: 'Batch SM-02',
        farmId: 'demo-sukamaju',
        block: 'Blok Tengah',
        commodity: 'Tomat',
        status: 'active',
        startDate: '2026-09-18',
        harvestDate: '2026-11-26',
        areaHa: 1.8,
        estimatedTon: 6.4,
    },
    {
        id: 'demo-sm-03',
        label: 'Batch SM-03',
        farmId: 'demo-sukamaju',
        block: 'Blok Selatan',
        commodity: 'Jagung',
        status: 'planned',
        startDate: '2026-11-09',
        harvestDate: '2027-01-16',
        areaHa: 2.4,
        estimatedTon: 9.8,
    },
    {
        id: 'demo-ba-01',
        label: 'Batch BA-01',
        farmId: 'demo-bukit-asri',
        block: 'Blok Timur',
        commodity: 'Cabai',
        status: 'active',
        startDate: '2026-09-14',
        harvestDate: '2026-11-29',
        areaHa: 1.6,
        estimatedTon: null,
    },
    {
        id: 'demo-ba-02',
        label: 'Batch BA-02',
        farmId: 'demo-bukit-asri',
        block: 'Blok Barat',
        commodity: 'Jagung',
        status: 'active',
        startDate: '2026-09-10',
        harvestDate: '2026-12-05',
        areaHa: 2.1,
        estimatedTon: 7.2,
    },
    {
        id: 'demo-ba-03',
        label: 'Batch BA-03',
        farmId: 'demo-bukit-asri',
        block: 'Blok Atas',
        commodity: 'Kacang panjang',
        status: 'planned',
        startDate: '2026-11-23',
        harvestDate: '2027-01-09',
        areaHa: 1.8,
        estimatedTon: null,
    },
    {
        id: 'demo-lh-01',
        label: 'Batch LH-01',
        farmId: 'demo-lembah-hijau',
        block: 'Blok Sungai',
        commodity: 'Jagung',
        status: 'active',
        startDate: '2026-09-20',
        harvestDate: '2026-12-18',
        areaHa: 2.2,
        estimatedTon: 8.4,
    },
    {
        id: 'demo-lh-02',
        label: 'Batch LH-02',
        farmId: 'demo-lembah-hijau',
        block: 'Blok Tengah',
        commodity: 'Bayam',
        status: 'active',
        startDate: '2026-10-28',
        harvestDate: null,
        areaHa: 0.9,
        estimatedTon: null,
    },
    {
        id: 'demo-lh-03',
        label: 'Batch LH-03',
        farmId: 'demo-lembah-hijau',
        block: 'Blok Bukit',
        commodity: 'Tomat',
        status: 'planned',
        startDate: '2026-11-05',
        harvestDate: '2026-12-27',
        areaHa: 1.5,
        estimatedTon: 5.3,
    },
    {
        id: 'demo-sm-00',
        label: 'Batch SM-00',
        farmId: 'demo-sukamaju',
        block: 'Blok Barat',
        commodity: 'Jagung',
        status: 'harvested',
        startDate: '2026-07-20',
        harvestDate: '2026-10-20',
        areaHa: 1.5,
        estimatedTon: 5.6,
    },
    {
        id: 'demo-ba-00',
        label: 'Batch BA-00',
        farmId: 'demo-bukit-asri',
        block: 'Blok Bawah',
        commodity: 'Kacang panjang',
        status: 'harvested',
        startDate: '2026-08-10',
        harvestDate: '2026-10-24',
        areaHa: 1.2,
        estimatedTon: 2.9,
    },
    {
        id: 'demo-lh-00',
        label: 'Batch LH-00',
        farmId: 'demo-lembah-hijau',
        block: 'Blok Selatan',
        commodity: 'Cabai',
        status: 'cancelled',
        startDate: '2026-09-05',
        harvestDate: null,
        areaHa: 1.4,
        estimatedTon: null,
    },
];

const risks = [
    {
        id: 'demo-risk-drainage',
        farmId: 'demo-bukit-asri',
        description: 'Genangan muncul di saluran sisi timur setelah hujan.',
        recommendation:
            'Periksa aliran air dan bersihkan saluran sebelum penyiraman berikutnya.',
        severity: 'high',
    },
    {
        id: 'demo-risk-leaves',
        farmId: 'demo-sukamaju',
        description: 'Bercak daun ditemukan pada sebagian tanaman tomat.',
        recommendation:
            'Tandai tanaman yang terdampak dan jadwalkan pemeriksaan lapangan lanjutan.',
        severity: 'medium',
    },
    {
        id: 'demo-risk-observation',
        farmId: 'demo-lembah-hijau',
        description: 'Dokumentasi pengamatan batch bayam belum lengkap.',
        recommendation:
            'Lengkapi foto dan catatan pengamatan sebelum tinjauan mingguan.',
        severity: 'low',
    },
];

// Totals are ordered from the reference day backwards. The last seven days
// contain 42 activities and the preceding seven contain 24 across all farms.
const dailyTotals = [
    7, 5, 8, 6, 4, 5, 7, 4, 3, 5, 2, 4, 3, 3, 5, 4, 6, 3, 4, 5, 2, 4, 3, 5, 4,
    3, 4, 5, 3, 4, 3, 4, 2, 3, 5, 2, 3, 4, 2, 4, 3, 2, 3, 4, 2, 3, 2, 4, 3, 2,
    3, 4, 2, 3, 4, 2, 3, 2, 4, 3,
];

function dateAt(offset: number): string {
    return new Date(AS_OF_TIME + offset * DAY_MS).toISOString().slice(0, 10);
}

function shiftDate(date: string, offset: number): string {
    return new Date(Date.parse(`${date}T00:00:00Z`) + offset * DAY_MS)
        .toISOString()
        .slice(0, 10);
}

function displayDate(date: string, withYear = false): string {
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        ...(withYear ? { year: 'numeric' as const } : {}),
        timeZone: 'Asia/Jakarta',
    }).format(new Date(`${date}T00:00:00Z`));
}

function periodLabel(start: string, end: string): string {
    return `${displayDate(start)}–${displayDate(end, true)}`;
}

const activityRecords = dailyTotals.flatMap((total, dayOffset) => {
    const firstFarm = Math.floor(total * 0.45);
    const secondFarm = Math.floor(total * 0.32);
    const counts = [firstFarm, secondFarm, total - firstFarm - secondFarm];
    return farms.map((farm, index) => ({
        farmId: farm.id,
        date: dateAt(-dayOffset),
        count: counts[index] ?? 0,
    }));
});

function activityComparison(
    farmIds: Set<string>,
    days: number,
): OwnerActivityComparison {
    const records = activityRecords.filter((record) =>
        farmIds.has(record.farmId),
    );
    const totalBetween = (start: string, end: string): number =>
        records
            .filter((record) => record.date >= start && record.date <= end)
            .reduce((total, record) => total + record.count, 0);
    const currentStart = dateAt(1 - days);
    const previousStart = dateAt(1 - days * 2);
    const previousEnd = dateAt(-days);
    const currentTotal = totalBetween(currentStart, AS_OF);
    const previousTotal = totalBetween(previousStart, previousEnd);
    const bucketSize = days === 30 ? 5 : 1;
    const buckets = Array.from({ length: days / bucketSize }, (_, index) => {
        const startOffset = 1 - days + index * bucketSize;
        const endOffset = startOffset + bucketSize - 1;
        const start = dateAt(startOffset);
        const end = dateAt(endOffset);
        return {
            label:
                bucketSize === 1
                    ? displayDate(start)
                    : `${displayDate(start)}–${displayDate(end)}`,
            current: totalBetween(start, end),
            previous: totalBetween(
                dateAt(startOffset - days),
                dateAt(endOffset - days),
            ),
        };
    });

    return {
        currentLabel: periodLabel(currentStart, AS_OF),
        previousLabel: periodLabel(previousStart, previousEnd),
        currentTotal,
        previousTotal,
        changePercent:
            previousTotal === 0
                ? null
                : Math.round(
                      ((currentTotal - previousTotal) / previousTotal) * 100,
                  ),
        buckets,
    };
}

function stagesFor(
    batch: DemoBatch,
): OwnerPlantingTimeline['rows'][number]['stages'] {
    if (batch.status === 'planned') {
        return [
            {
                code: 'soil_preparation',
                label: 'Persiapan tanah',
                status: 'pending',
                startDate: null,
                endDate: null,
            },
            {
                code: 'planting',
                label: 'Penanaman',
                status: 'pending',
                startDate: null,
                endDate: null,
            },
            {
                code: 'maintenance',
                label: 'Pemeliharaan',
                status: 'pending',
                startDate: null,
                endDate: null,
            },
            {
                code: 'harvest',
                label: 'Panen',
                status: 'pending',
                startDate: null,
                endDate: null,
            },
        ];
    }

    return [
        {
            code: 'soil_preparation',
            label: 'Persiapan tanah',
            status: 'completed',
            startDate: shiftDate(batch.startDate, -7),
            endDate: shiftDate(batch.startDate, -2),
        },
        {
            code: 'planting',
            label: 'Penanaman',
            status: 'completed',
            startDate: batch.startDate,
            endDate: shiftDate(batch.startDate, 1),
        },
        {
            code: 'maintenance',
            label: 'Pemeliharaan',
            status: 'in_progress',
            startDate: shiftDate(batch.startDate, 7),
            endDate: null,
        },
        {
            code: 'harvest',
            label: 'Panen',
            status: 'pending',
            startDate: null,
            endDate: null,
        },
    ];
}

function rounded(value: number): number {
    return Number(value.toFixed(4));
}

export function getOwnerDashboardDemo(
    farmId: string,
    period: string,
): OwnerDashboardData {
    const selectedFarm = farms.find((farm) => farm.id === farmId);
    const selectedFarms = selectedFarm ? [selectedFarm] : farms;
    const farmIds = new Set(selectedFarms.map((farm) => farm.id));
    const selectedBatches = batches.filter((batch) =>
        farmIds.has(batch.farmId),
    );
    const running = selectedBatches.filter((batch) =>
        ['active', 'planned'].includes(batch.status),
    );
    const dated = running
        .filter((batch) => batch.harvestDate !== null)
        .sort((left, right) =>
            (left.harvestDate ?? '').localeCompare(right.harvestDate ?? ''),
        );
    const selectedRisks = risks.filter((risk) => farmIds.has(risk.farmId));
    const days = period === '30' ? 30 : 7;
    const forecastEnd = dateAt(83);

    return {
        filters: { farm_id: selectedFarm?.id ?? null, period: String(days) },
        farmOptions: ownerDashboardDemoFarms.map((farm) => ({ ...farm })),
        insights: {
            asOf: AS_OF,
            batchStatus: [
                {
                    key: 'active',
                    label: 'Aktif',
                    count: selectedBatches.filter(
                        (batch) => batch.status === 'active',
                    ).length,
                },
                {
                    key: 'planned',
                    label: 'Direncanakan',
                    count: selectedBatches.filter(
                        (batch) => batch.status === 'planned',
                    ).length,
                },
                {
                    key: 'harvested',
                    label: 'Sudah dipanen',
                    count: selectedBatches.filter(
                        (batch) => batch.status === 'harvested',
                    ).length,
                },
                {
                    key: 'cancelled',
                    label: 'Dibatalkan',
                    count: selectedBatches.filter(
                        (batch) => batch.status === 'cancelled',
                    ).length,
                },
            ],
            activityComparison: activityComparison(farmIds, days),
            harvestForecast: {
                start: AS_OF,
                end: forecastEnd,
                weeks: Array.from({ length: 12 }, (_, index) => {
                    const start = dateAt(index * 7);
                    const end = dateAt(index * 7 + 6);
                    const scheduled = dated.filter(
                        (batch) =>
                            batch.harvestDate !== null &&
                            batch.harvestDate >= start &&
                            batch.harvestDate <= end,
                    );
                    const weighted = scheduled.filter(
                        (batch) => batch.estimatedTon !== null,
                    );
                    return {
                        label: displayDate(start),
                        start,
                        end,
                        batchCount: scheduled.length,
                        areaHa: rounded(
                            scheduled.reduce(
                                (total, batch) => total + batch.areaHa,
                                0,
                            ),
                        ),
                        estimatedTon:
                            scheduled.length > 0 && weighted.length === 0
                                ? null
                                : rounded(
                                      weighted.reduce(
                                          (total, batch) =>
                                              total + (batch.estimatedTon ?? 0),
                                          0,
                                      ),
                                  ),
                        missingWeightCount: scheduled.length - weighted.length,
                    };
                }),
                overdueCount: dated.filter(
                    (batch) => (batch.harvestDate ?? '') < AS_OF,
                ).length,
                undatedCount: running.length - dated.length,
            },
            timeline: {
                start: '2026-09-01',
                end: forecastEnd,
                rows: dated.slice(0, 6).map((batch) => ({
                    id: batch.id,
                    label: batch.label,
                    farm:
                        farms.find((farm) => farm.id === batch.farmId)?.name ??
                        '',
                    block: batch.block,
                    commodity: batch.commodity,
                    status: batch.status,
                    startDate: batch.startDate,
                    endDate: batch.harvestDate ?? AS_OF,
                    startKind:
                        batch.status === 'planned' ? 'planned' : 'actual',
                    areaHa: batch.areaHa,
                    href: '/kebun',
                    stages: stagesFor(batch),
                })),
                undatedCount: running.length - dated.length,
                total: running.length,
                hasMore: dated.length > 6,
            },
            landUse: {
                farms: selectedFarms.map((farm) => ({
                    id: farm.id,
                    name: farm.name,
                    totalAreaHa: farm.totalAreaHa,
                    activeAreaHa: rounded(
                        selectedBatches
                            .filter(
                                (batch) =>
                                    batch.farmId === farm.id &&
                                    batch.status === 'active',
                            )
                            .reduce((total, batch) => total + batch.areaHa, 0),
                    ),
                    plannedAreaHa: rounded(
                        selectedBatches
                            .filter(
                                (batch) =>
                                    batch.farmId === farm.id &&
                                    batch.status === 'planned',
                            )
                            .reduce((total, batch) => total + batch.areaHa, 0),
                    ),
                    href: '/kebun',
                })),
            },
            risks: {
                total: selectedRisks.length,
                severities: [
                    { key: 'critical', label: 'Kritis' },
                    { key: 'high', label: 'Tinggi' },
                    { key: 'medium', label: 'Sedang' },
                    { key: 'low', label: 'Rendah' },
                    { key: 'unknown', label: 'Belum dinilai' },
                ].map((severity) => ({
                    ...severity,
                    count: selectedRisks.filter(
                        (risk) => risk.severity === severity.key,
                    ).length,
                })),
                items: selectedRisks.map((risk) => ({
                    id: risk.id,
                    description: risk.description,
                    recommendation: risk.recommendation,
                    severity: risk.severity,
                    farm:
                        farms.find((farm) => farm.id === risk.farmId)?.name ??
                        '',
                    href: '/kebun',
                })),
            },
        },
    };
}
