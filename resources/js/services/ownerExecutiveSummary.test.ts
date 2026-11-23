import { describe, expect, it } from 'vite-plus/test';
import type { ExecutiveRow } from '@/types/owner-executive';
import {
    executiveTotals,
    executiveCommodities,
    executiveLandCategories,
    executiveNumber,
    executiveAvailableProjection,
} from './ownerExecutiveSummary';

export function row(overrides: Partial<ExecutiveRow> = {}): ExecutiveRow {
    return {
        id: 'farm:corn',
        farmId: 'farm',
        year: 2026,
        quarter: 'all',
        farm: {
            id: 'farm',
            name: 'Lahan Bogor',
            city: 'Bogor',
            region: 'Jawa Barat',
            partnerId: '',
            partnerName: 'Belum tercatat',
            landCategory: 'owned',
            areaHa: 10,
            commodityIds: ['corn', 'grass'],
            latitude: null,
            longitude: null,
            detail_url: '/kebun/farm',
        },
        partner: { id: '', name: 'Belum tercatat' },
        commodity: { id: 'corn', name: 'Jagung', pricing: null },
        plantedHa: 2,
        harvestedTon: 5,
        projectedTon: 10,
        projectionMissing: 0,
        stockTon: null,
        monthlyDeliveredTon: 0,
        status: 'Tanam aktif',
        ...overrides,
    };
}
describe('Executive database presentation', () => {
    it('sums available projections, preserving zero and identifying incomplete rows', () => {
        expect(
            executiveAvailableProjection([
                row({ projectedTon: 40 }),
                row({ projectedTon: null }),
                row({ projectedTon: 0 }),
            ]),
        ).toEqual({ projected: 40, missing: 1 });
        expect(
            executiveAvailableProjection([row({ projectedTon: null })]),
        ).toEqual({ projected: null, missing: 1 });
        expect(
            executiveAvailableProjection([row({ projectedTon: 0 })]),
        ).toEqual({ projected: 0, missing: 0 });
        expect(executiveAvailableProjection([])).toEqual({
            projected: null,
            missing: 0,
        });
    });
    it('counts farm area once across different commodities and preserves each production amount', () => {
        const rows = [
            row(),
            row({
                id: 'farm:grass',
                commodity: { id: 'grass', name: 'Rumput', pricing: null },
                plantedHa: 3,
                harvestedTon: 7,
                projectedTon: 20,
            }),
        ];
        expect(executiveTotals(rows)).toMatchObject({
            area: 10,
            planted: 5,
            utilization: 50,
            harvested: 12,
            projected: 30,
            stock: null,
        });
        expect(
            executiveCommodities(rows).map((crop) => crop.harvested),
        ).toEqual([5, 7]);
        expect(
            executiveLandCategories(rows).find(
                (category) => category.id === 'owned',
            )?.value,
        ).toBe(10);
    });
    it('does not turn unknown stock or incomplete projections into zero', () => {
        const totals = executiveTotals([
            row({ projectedTon: null, projectionMissing: 1 }),
        ]);
        expect(totals.stock).toBeNull();
        expect(totals.projected).toBeNull();
        expect(executiveNumber(totals.stock)).toBe('—');
        expect(executiveTotals([]).area).toBe(0);
    });
});
