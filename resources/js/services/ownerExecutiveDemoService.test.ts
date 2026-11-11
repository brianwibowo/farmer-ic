import { describe, expect, it } from 'vitest';
import data from '@/data/ownerExecutiveDemo.json';
import {
    executiveCommodities,
    executiveRows,
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
    it('calculates land and stock once for the selected snapshot', () => {
        const rows = executiveRows(data, filters);
        const totals = executiveTotals(rows);
        expect(rows).toHaveLength(9);
        expect(totals.area).toBe(152);
        expect(totals.planted).toBe(117);
        expect(totals.unused).toBe(35);
        expect(totals.projected).toBe(7620);
        expect(totals.stock).toBeCloseTo(
            rows.reduce(
                (sum, row) =>
                    sum +
                    row.harvestedTon -
                    row.dispatchedTon +
                    row.adjustmentTon,
                0,
            ),
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
        expect(second.planted).toBeCloseTo(first.planted * 0.95);
        expect(previous.planted).toBeCloseTo(first.planted * 0.9);
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
