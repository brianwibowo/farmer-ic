import type {
    ExecutiveDemoData,
    ExecutiveFilters,
    ExecutiveRow,
} from '@/types/owner-executive';

// Illustrative data only. Replace this adapter when connecting the live backend.
export async function loadExecutiveDemo(): Promise<ExecutiveDemoData> {
    const [module] = await Promise.all([
        import('@/data/ownerExecutiveDemo.json'),
        new Promise<void>((resolve) => setTimeout(resolve, 400)),
    ]);
    return module.default;
}

// Calendar years follow the app's Indonesian reporting timezone, including
// events close to midnight on 1 January / 31 December.
function reportingYear(date: string): number {
    return Number(
        new Intl.DateTimeFormat('en', {
            year: 'numeric',
            timeZone: 'Asia/Jakarta',
        }).format(new Date(date)),
    );
}

export function executiveYears(data: ExecutiveDemoData): number[] {
    return [
        ...new Set([
            ...data.snapshots.map((snapshot) => snapshot.year),
            ...data.operations.flatMap((operation) => [
                operation.year,
                reportingYear(operation.harvestedAt),
                reportingYear(operation.projectedHarvestAt),
            ]),
        ]),
    ].sort((a, b) => b - a);
}

export function executiveCycleForYear(
    cycle: ExecutiveFilters['cycle'],
    available: number[],
): ExecutiveFilters['cycle'] {
    return cycle === 'all' || available.includes(cycle) ? cycle : 'all';
}

export function executiveSnapshotDate(data: ExecutiveDemoData, year: number) {
    return data.snapshots
        .filter((snapshot) => snapshot.year === year)
        .map((snapshot) => snapshot.capturedAt)
        .sort((a, b) => Date.parse(b) - Date.parse(a))[0];
}

export function executiveRows(
    data: ExecutiveDemoData,
    filters: ExecutiveFilters,
): ExecutiveRow[] {
    const annual = filters.cycle === 'all';
    const operations = data.operations.filter((operation) =>
        annual
            ? reportingYear(operation.harvestedAt) === filters.year ||
              reportingYear(operation.projectedHarvestAt) === filters.year
            : operation.year === filters.year &&
              operation.cycle === filters.cycle,
    );
    const periodFarmIds = new Set(
        operations.map((operation) => operation.farmId),
    );
    // Include unplanted farms in total land, even without a harvest record.
    for (const snapshot of data.snapshots) {
        if (snapshot.year === filters.year) periodFarmIds.add(snapshot.farmId);
    }
    return data.farms.flatMap((farm): ExecutiveRow[] => {
        if (
            !periodFarmIds.has(farm.id) ||
            (filters.farm && farm.id !== filters.farm) ||
            (filters.partner && farm.partnerId !== filters.partner) ||
            (filters.region && farm.region !== filters.region) ||
            (filters.commodity && farm.commodityId !== filters.commodity)
        )
            return [];
        const commodity = data.commodities.find(
            (item) => item.id === farm.commodityId,
        );
        const partner = data.partners.find(
            (item) => item.id === farm.partnerId,
        );
        if (!commodity || !partner) return [];
        const group = operations.filter(
            (operation) => operation.farmId === farm.id,
        );
        const snapshot = data.snapshots
            .filter(
                (item) => item.farmId === farm.id && item.year === filters.year,
            )
            .sort(
                (a, b) => Date.parse(b.capturedAt) - Date.parse(a.capturedAt),
            )[0];
        // These are active planted areas in one position, not hectares
        // accumulated as the same land is cultivated again in another cycle.
        const plantedHa =
            snapshot?.plantings.reduce(
                (sum, planting) =>
                    sum +
                    (planting.status === 'active' ? planting.plantedHa : 0),
                0,
            ) ?? 0;
        const total = (
            read: (
                operation: ExecutiveDemoData['operations'][number],
            ) => number,
        ) => group.reduce((sum, operation) => sum + read(operation), 0);
        const contract = data.contracts.find(
            (item) =>
                item.farmId === farm.id &&
                item.startYear <= filters.year &&
                item.endYear >= filters.year,
        );
        return [
            {
                id: `${farm.id}-${filters.year}-${filters.cycle}`,
                farmId: farm.id,
                year: filters.year,
                cycle: filters.cycle,
                plantedHa,
                harvestedTon: total((operation) =>
                    !annual ||
                    reportingYear(operation.harvestedAt) === filters.year
                        ? operation.harvestedTon
                        : 0,
                ),
                monthlyDeliveredTon: snapshot?.monthlyDeliveredTon ?? 0,
                status: plantedHa > 0 ? 'Tanam aktif' : 'Belum ditanam',
                farm,
                commodity,
                partner,
                contract,
                client: data.clients.find(
                    (item) => item.id === contract?.clientId,
                ),
                projectedTon: total((operation) =>
                    !annual ||
                    reportingYear(operation.projectedHarvestAt) === filters.year
                        ? operation.plantedHa * commodity.tonPerHa
                        : 0,
                ),
                stockTon: snapshot?.stockTon ?? 0,
            },
        ];
    });
}

export function executiveTotals(rows: ExecutiveRow[]) {
    // Land and agreements are counted once, independently of cycle/contract joins.
    const farms = [
        ...new Map(rows.map((row) => [row.farm.id, row.farm])).values(),
    ];
    const total = (read: (row: ExecutiveRow) => number) =>
        rows.reduce((sum, row) => sum + read(row), 0);
    const area = farms.reduce((sum, farm) => sum + farm.areaHa, 0);
    const planted = total((row) => row.plantedHa);
    const projected = total((row) => row.projectedTon);
    const revenue = total(
        (row) => row.projectedTon * 1000 * row.commodity.pricing.sellingPrice,
    );
    const totalHpp = total(
        (row) => row.projectedTon * 1000 * row.commodity.pricing.hpp,
    );
    const totalTransport = total(
        (row) => row.projectedTon * 1000 * row.commodity.pricing.transport,
    );
    const costs = totalHpp + totalTransport;
    const grossProfit = revenue - costs;
    return {
        agreements: farms.filter((farm) => farm.partnershipStatus === 'Aktif')
            .length,
        area,
        planted,
        unused: area - planted,
        utilization: area ? (planted / area) * 100 : 0,
        contracts: total((row) =>
            row.contract?.status === 'Aktif' ? row.contract.monthlyTon : 0,
        ),
        harvested: total((row) => row.harvestedTon),
        stock: total((row) => row.stockTon),
        projected,
        revenue,
        totalHpp,
        totalTransport,
        costs,
        grossProfit,
        grossMarginPercent: revenue ? (grossProfit / revenue) * 100 : 0,
    };
}

export const executiveNumber = (value: number, digits = 1) =>
    new Intl.NumberFormat('id-ID', { maximumFractionDigits: digits }).format(
        value,
    );
export const executiveMoney = (value: number, compact = false) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: compact ? 2 : 0,
        ...(compact ? { notation: 'compact' as const } : {}),
    }).format(value);

export function executiveLandCategories(rows: ExecutiveRow[]) {
    return [
        { id: 'pks', name: 'PKS — Kerja sama' },
        { id: 'bhi', name: 'BHI — Milik sendiri' },
        { id: 'plasma', name: 'Plasma — Koperasi' },
    ].map((category) => ({
        ...category,
        value: executiveTotals(
            rows.filter((row) => row.farm.landCategory === category.id),
        ).area,
    }));
}

export function executiveClients(
    rows: ExecutiveRow[],
    clients: ExecutiveDemoData['clients'],
) {
    return clients
        .map((client) => {
            const group = rows.filter(
                (row) =>
                    row.client?.id === client.id &&
                    row.contract?.status === 'Aktif',
            );
            const commodities = [
                ...new Map(
                    group.map((row) => [row.commodity.id, row.commodity]),
                ).values(),
            ];
            const breakdown = commodities.map((commodity) => {
                const cropRows = group.filter(
                    (row) => row.commodity.id === commodity.id,
                );
                return {
                    id: commodity.id,
                    name: commodity.name,
                    contracted: cropRows.reduce(
                        (sum, row) => sum + (row.contract?.monthlyTon ?? 0),
                        0,
                    ),
                    delivered: cropRows.reduce(
                        (sum, row) => sum + row.monthlyDeliveredTon,
                        0,
                    ),
                };
            });
            const contracted = breakdown.reduce(
                (sum, crop) => sum + crop.contracted,
                0,
            );
            const delivered = breakdown.reduce(
                (sum, crop) => sum + crop.delivered,
                0,
            );
            return {
                ...client,
                contracted,
                delivered,
                fulfillment: contracted ? (delivered / contracted) * 100 : 0,
                crops: commodities.map((crop) => crop.name).join(', '),
                commodities: breakdown,
                status: [
                    ...new Set(group.map((row) => row.contract?.status)),
                ].join(', '),
            };
        })
        .filter((client) => client.contracted > 0)
        .sort((a, b) => b.contracted - a.contracted);
}

export function executiveCommodities(rows: ExecutiveRow[]) {
    const commodities = [
        ...new Map(
            rows.map((row) => [row.commodity.id, row.commodity]),
        ).values(),
    ];
    return commodities.map((commodity) => {
        const group = rows.filter((row) => row.commodity.id === commodity.id);
        const totals = executiveTotals(group);
        return {
            ...commodity,
            planted: totals.planted,
            projected: totals.projected,
            harvested: totals.harvested,
            stock: totals.stock,
            revenue: totals.revenue,
            grossProfit: totals.grossProfit,
            totalHpp: totals.totalHpp,
            totalTransport: totals.totalTransport,
            marginPerKg:
                commodity.pricing.sellingPrice -
                commodity.pricing.hpp -
                commodity.pricing.transport,
        };
    });
}
