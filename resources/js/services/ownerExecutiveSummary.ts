import type {
    ExecutiveRow,
    ExecutiveSummaryData,
} from '@/types/owner-executive';

export function executiveTotals(rows: ExecutiveRow[]) {
    // Land and agreements are counted once, independently of cycle/contract joins.
    const farms = [
        ...new Map(rows.map((row) => [row.farm.id, row.farm])).values(),
    ];
    const total = (read: (row: ExecutiveRow) => number) =>
        rows.reduce((sum, row) => sum + read(row), 0);
    const area = farms.reduce((sum, farm) => sum + farm.areaHa, 0);
    const planted = total((row) => row.plantedHa);
    const projected = rows.some((row) => row.projectedTon === null)
        ? null
        : total((row) => row.projectedTon ?? 0);
    const revenue = total(
        (row) =>
            (row.projectedTon ?? 0) *
            1000 *
            (row.commodity.pricing?.sellingPrice ?? 0),
    );
    const totalHpp = total(
        (row) =>
            (row.projectedTon ?? 0) * 1000 * (row.commodity.pricing?.hpp ?? 0),
    );
    const totalTransport = total(
        (row) =>
            (row.projectedTon ?? 0) *
            1000 *
            (row.commodity.pricing?.transport ?? 0),
    );
    const costs = totalHpp + totalTransport;
    const grossProfit = revenue - costs;
    return {
        agreements: 0,
        area,
        planted,
        unused: area - planted,
        utilization: area ? (planted / area) * 100 : 0,
        contracts: total((row) =>
            row.contract?.status === 'Aktif' ? row.contract.monthlyTon : 0,
        ),
        harvested: total((row) => row.harvestedTon),
        stock:
            rows.length && rows.every((row) => row.stockTon !== null)
                ? total((row) => row.stockTon ?? 0)
                : null,
        projected,
        revenue,
        totalHpp,
        totalTransport,
        costs,
        grossProfit,
        grossMarginPercent: revenue ? (grossProfit / revenue) * 100 : 0,
    };
}

export const executiveNumber = (
    value: number | null | undefined,
    digits = 1,
) =>
    value == null
        ? '—'
        : new Intl.NumberFormat('id-ID', {
              maximumFractionDigits: digits,
          }).format(value);
export const executiveMoney = (value: number, compact = false) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: compact ? 2 : 0,
        ...(compact ? { notation: 'compact' as const } : {}),
    }).format(value);

export function executiveLandCategories(rows: ExecutiveRow[]) {
    return [
        { id: 'owned', name: 'Milik sendiri' },
        { id: 'rented', name: 'Sewa' },
        { id: 'partnership', name: 'Kerja sama' },
        { id: 'unknown', name: 'Belum tercatat' },
    ].map((category) => ({
        ...category,
        value: executiveTotals(
            rows.filter((row) => row.farm.landCategory === category.id),
        ).area,
    }));
}

export function executiveClients(
    rows: ExecutiveRow[],
    clients: ExecutiveSummaryData['clients'],
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
                (commodity.pricing?.sellingPrice ?? 0) -
                (commodity.pricing?.hpp ?? 0) -
                (commodity.pricing?.transport ?? 0),
        };
    });
}
