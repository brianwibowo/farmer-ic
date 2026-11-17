import { describe, expect, it } from 'vite-plus/test';
import data from '@/data/ownerExecutiveDemo.json';
import {
    executiveCommodities,
    executiveClients,
    executiveLandCategories,
    executiveRows,
    executiveYears,
    executiveCycleForYear,
    executiveTotals,
} from './ownerExecutiveDemoService';
import type { ExecutiveFilters } from '@/types/owner-executive';

const filters: ExecutiveFilters = {
    year: 2026,
    cycle: 1,
    partner: '',
    region: '',
    farm: '',
    commodity: '',
};

describe('Executive JSON summary', () => {
    it('reconciles commodity stock with the displayed total across all global filters', () => {
        for (const selection of [
            {},
            { commodity: data.commodities[0].id },
            { partner: data.partners[0].id },
            { region: data.farms[0].region },
            { farm: data.farms[0].id },
            { farm: 'no-matching-farm' },
        ]) {
            const rows = executiveRows(data, { ...filters, ...selection });
            const stock = executiveCommodities(rows).reduce(
                (sum, crop) => sum + crop.stock,
                0,
            );
            expect(stock).toBeCloseTo(executiveTotals(rows).stock);
            expect(
                executiveCommodities(rows).every((crop) => crop.stock >= 0),
            ).toBe(true);
        }
    });
    it('calculates land and stock once for the selected snapshot', () => {
        const rows = executiveRows(data, filters);
        const totals = executiveTotals(rows);
        expect(rows).toHaveLength(9);
        expect(totals.area).toBe(152);
        expect(totals.planted).toBe(117);
        expect(totals.unused).toBe(35);
        expect(totals.projected).toBe(7620);
        expect(totals.stock).toBeCloseTo(
            data.snapshots
                .filter((snapshot) => snapshot.year === filters.year)
                .reduce((sum, snapshot) => sum + snapshot.stockTon, 0),
        );
    });
    it('applies each commodity price before aggregating revenue, costs, and profit', () => {
        const rows = executiveRows(data, filters);
        const totals = executiveTotals(rows);
        const crops = executiveCommodities(rows);
        expect(crops.map((crop) => crop.pricing.sellingPrice)).toEqual([
            2200, 1500, 2000,
        ]);
        expect(totals.revenue).toBe(13220000000);
        expect(totals.totalHpp).toBe(3634740000);
        expect(totals.totalTransport).toBe(3810000000);
        expect(totals.grossProfit).toBe(5775260000);
        expect(totals.revenue).toBe(
            crops.reduce((sum, crop) => sum + crop.revenue, 0),
        );
        expect(totals.grossProfit).toBe(
            crops.reduce((sum, crop) => sum + crop.grossProfit, 0),
        );
        expect(totals.grossMarginPercent).toBeCloseTo(
            (totals.grossProfit / totals.revenue) * 100,
        );
        expect(crops.map((crop) => crop.marginPerKg)).toEqual([
            1223, 523, 1023,
        ]);
    });
    it('intersects all global filters and updates financial summaries for the selected farm', () => {
        const rows = executiveRows(data, {
            ...filters,
            partner: 'bumi',
            region: 'Jawa Barat',
            commodity: 'jagung',
            farm: 'cariu',
        });
        expect(rows.map((row) => row.farm.id)).toEqual(['cariu']);
        expect(executiveTotals(rows).revenue).toBe(880000000);
        expect(executiveCommodities(rows)).toHaveLength(1);
        expect(
            executiveRows(data, {
                ...filters,
                region: 'DI Yogyakarta',
                partner: 'bumi',
            }),
        ).toEqual([]);
    });
    it('never sums repeated hectares across years and cycles', () => {
        const first = executiveTotals(executiveRows(data, filters));
        const second = executiveTotals(
            executiveRows(data, { ...filters, cycle: 2 }),
        );
        const previous = executiveTotals(
            executiveRows(data, { ...filters, year: 2025 }),
        );
        expect(first.area).toBe(second.area);
        expect(first.area).toBe(previous.area);
        expect(second.planted).toBe(first.planted);
        expect(previous.planted).toBeCloseTo(first.planted * 0.9);
    });
    it('accumulates annual production while keeping land, stock and monthly sales as positions', () => {
        const single = executiveRows(data, filters);
        const annual = executiveRows(data, { ...filters, cycle: 'all' });
        const totals = executiveTotals(annual);
        const snapshot = executiveTotals(single);
        expect(annual).toHaveLength(9);
        expect(new Set(annual.map((row) => row.farm.id)).size).toBe(9);
        for (const key of [
            'area',
            'planted',
            'unused',
            'utilization',
            'stock',
            'contracts',
            'agreements',
        ] as const)
            expect(totals[key]).toBeCloseTo(snapshot[key]);
        expect(totals.harvested).toBeCloseTo(
            data.operations
                .filter((operation) => operation.year === filters.year)
                .reduce((sum, operation) => sum + operation.harvestedTon, 0),
        );
        expect(totals.projected).toBeCloseTo(snapshot.projected * 2.8);
        expect(executiveClients(annual, data.clients)).toEqual(
            executiveClients(single, data.clients),
        );
        expect(
            executiveCommodities(annual).reduce(
                (sum, crop) => sum + crop.stock,
                0,
            ),
        ).toBeCloseTo(totals.stock);
        expect(totals.revenue).toBeCloseTo(snapshot.revenue * 2.8);
    });
    it('counts only active planted areas, retaining unplanted land and past harvests', () => {
        const fixture = structuredClone(data);
        fixture.snapshots.find(
            (snapshot) =>
                snapshot.year === filters.year && snapshot.farmId === 'cariu',
        )!.plantings = [
            { status: 'active', plantedHa: 3 },
            { status: 'active', plantedHa: 2 },
            { status: 'planned', plantedHa: 4 },
            { status: 'harvested', plantedHa: 10 },
        ];
        const selected = { ...filters, farm: 'cariu', cycle: 'all' as const };
        const rows = executiveRows(fixture, selected);
        expect(executiveTotals(rows).planted).toBe(5);
        expect(executiveTotals(rows).area).toBe(14);
        expect(executiveTotals(rows).harvested).toBeGreaterThan(0);
        fixture.snapshots.find(
            (snapshot) =>
                snapshot.year === filters.year && snapshot.farmId === 'cariu',
        )!.plantings = [];
        fixture.operations = fixture.operations.filter(
            (operation) => operation.farmId !== 'cariu',
        );
        const unplanted = executiveTotals(executiveRows(fixture, selected));
        expect(unplanted.area).toBe(14);
        expect(unplanted.planted).toBe(0);
        expect(unplanted.utilization).toBe(0);
        expect(unplanted.harvested).toBe(0);
    });
    it('uses the latest position once even when older snapshots exist', () => {
        const fixture = structuredClone(data);
        const snapshot = fixture.snapshots.find(
            (item) => item.year === filters.year && item.farmId === 'cariu',
        )!;
        fixture.snapshots.unshift({
            ...snapshot,
            capturedAt: '2026-01-01T00:00:00+07:00',
            stockTon: 999,
            plantings: [{ status: 'active', plantedHa: 1 }],
        });
        const totals = executiveTotals(
            executiveRows(fixture, { ...filters, farm: 'cariu', cycle: 'all' }),
        );
        expect(totals.stock).toBe(snapshot.stockTon);
        expect(totals.planted).toBe(10);
    });
    it('reports January–December by actual harvest date, separately from the projection schedule and planting year', () => {
        const fixture = structuredClone(data);
        const dates = [
            '2025-12-31T16:59:59Z',
            '2025-12-31T17:00:00Z',
            '2026-12-31T16:59:59Z',
            '2026-12-31T17:00:00Z',
        ];
        const operation = fixture.operations[0];
        fixture.operations = dates.map((date, i) => ({
            ...operation,
            id: `boundary-${i}`,
            year: 2025,
            harvestedAt: date,
            projectedHarvestAt: dates[3 - i],
            harvestedTon: (i + 1) * 10,
            plantedHa: i + 1,
        }));
        const annual = executiveTotals(
            executiveRows(fixture, { ...filters, farm: 'cariu', cycle: 'all' }),
        );
        expect(annual.harvested).toBe(50);
        expect(annual.projected).toBe((2 + 3) * 40);
        expect(executiveYears(fixture)).toEqual([2027, 2026, 2025]);
    });
    it('intersects all annual filters without multiplying farms or stocks', () => {
        for (const selection of [
            { commodity: 'jagung' },
            { partner: 'bumi' },
            { region: 'Jawa Barat' },
            { farm: 'cariu' },
            { partner: 'bumi', region: 'DI Yogyakarta' },
        ]) {
            const annual = executiveRows(data, {
                ...filters,
                ...selection,
                cycle: 'all',
            });
            const single = executiveRows(data, { ...filters, ...selection });
            expect(annual.map((row) => row.farm.id)).toEqual(
                single.map((row) => row.farm.id),
            );
            const totals = executiveTotals(annual);
            expect(totals.area).toBe(executiveTotals(single).area);
            expect(
                executiveCommodities(annual).reduce(
                    (sum, crop) => sum + crop.harvested,
                    0,
                ),
            ).toBeCloseTo(totals.harvested);
        }
        expect(
            executiveRows(data, { ...filters, year: 2030, cycle: 'all' }),
        ).toEqual([]);
    });
    it('retains all across years and falls back to all when a numbered cycle is unavailable', () => {
        expect(executiveCycleForYear('all', [1, 2])).toBe('all');
        expect(executiveCycleForYear(2, [1, 2])).toBe(2);
        expect(executiveCycleForYear(3, [1, 2])).toBe('all');
        expect(executiveCycleForYear(1, [])).toBe('all');
    });
    it('handles missing periods and loss-making commodity prices honestly', () => {
        const empty = executiveTotals(
            executiveRows(data, { ...filters, year: 2030 }),
        );
        expect(empty.grossMarginPercent).toBe(0);
        expect(empty.revenue).toBe(0);
        expect(empty.utilization).toBe(0);
        const lossData = structuredClone(data);
        lossData.commodities.forEach((commodity) => {
            commodity.pricing.sellingPrice = 900;
        });
        const loss = executiveTotals(executiveRows(lossData, filters));
        expect(loss.grossProfit).toBe(7620000 * -77);
        expect(loss.grossMarginPercent).toBeLessThan(0);
    });
    it('aggregates explicit land categories without duplicating farms', () => {
        const rows = executiveRows(data, filters);
        const categories = executiveLandCategories(rows);
        expect(categories.map((category) => category.id)).toEqual([
            'pks',
            'bhi',
            'plasma',
        ]);
        expect(categories.every((category) => category.value > 0)).toBe(true);
        expect(
            categories.reduce((sum, category) => sum + category.value, 0),
        ).toBe(152);
        expect(executiveLandCategories([...rows, rows[0]])).toEqual(categories);
        const selected = executiveRows(data, { ...filters, partner: 'bumi' });
        expect(
            executiveLandCategories(selected)
                .filter((category) => category.value > 0)
                .map((category) => category.id),
        ).toEqual(['bhi']);
    });
    it('keeps client and commodity sales totals consistent with the selected rows', () => {
        const rows = executiveRows(data, filters);
        const clients = executiveClients(rows, data.clients);
        expect(clients).toHaveLength(4);
        expect(
            clients.reduce((sum, client) => sum + client.contracted, 0),
        ).toBe(executiveTotals(rows).contracts);
        for (const client of clients) {
            expect(
                client.commodities.reduce(
                    (sum, crop) => sum + crop.contracted,
                    0,
                ),
            ).toBe(client.contracted);
            expect(
                client.commodities.reduce(
                    (sum, crop) => sum + crop.delivered,
                    0,
                ),
            ).toBe(client.delivered);
            for (const crop of client.commodities) {
                const group = rows.filter(
                    (row) =>
                        row.client?.id === client.id &&
                        row.commodity.id === crop.id,
                );
                expect(crop.contracted).toBe(
                    group.reduce(
                        (sum, row) => sum + (row.contract?.monthlyTon ?? 0),
                        0,
                    ),
                );
                expect(crop.delivered).toBe(
                    group.reduce(
                        (sum, row) => sum + row.monthlyDeliveredTon,
                        0,
                    ),
                );
            }
        }
        const filtered = executiveClients(
            executiveRows(data, { ...filters, commodity: 'jagung' }),
            data.clients,
        );
        expect(
            filtered.every(
                (client) =>
                    client.commodities.length === 1 &&
                    client.commodities[0].id === 'jagung',
            ),
        ).toBe(true);
        expect(executiveClients([], data.clients)).toEqual([]);
        const inactive = rows.map((row) => ({
            ...row,
            contract: row.contract
                ? { ...row.contract, status: 'Selesai' }
                : undefined,
        }));
        expect(executiveClients(inactive, data.clients)).toEqual([]);
    });
    it('keeps fixture areas and stocks valid and uses only the three specified crops', () => {
        expect(data.commodities.map((crop) => crop.name)).toEqual([
            'Jagung',
            'Rumput',
            'Sorgum',
        ]);
        const keys = data.operations.map(
            (row) => `${row.farmId}-${row.year}-${row.cycle}`,
        );
        expect(new Set(keys).size).toBe(keys.length);
        for (const row of data.operations) {
            expect(row.plantedHa).toBeLessThanOrEqual(
                data.farms.find((farm) => farm.id === row.farmId)!.areaHa,
            );
            expect(
                row.harvestedTon - row.dispatchedTon + row.adjustmentTon,
            ).toBeGreaterThanOrEqual(0);
        }
    });
});
