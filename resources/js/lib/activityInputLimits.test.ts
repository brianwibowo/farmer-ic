import { describe, expect, it } from 'vitest';
import {
    activityDetailMaximumErrors,
    areaMaximum,
    maximumError,
} from './activityInputLimits';

const fields = [
    { key: 'area', label: 'Luas area', type: 'number', max: 999999 },
    { key: 'moisture', label: 'Kadar air (%)', type: 'number', max: 100 },
    {
        key: 'bag_size',
        label: 'Berat per sak',
        type: 'number',
        max: 999999,
        when: ['quantity_unit', 'sak'] as [string, string],
    },
];

describe('activity numeric maximums', () => {
    it('accepts the exact limit and reports higher values immediately', () => {
        expect(maximumError('7', 7, 'Luas', 'ha')).toBeUndefined();
        expect(maximumError('7.0001', 7, 'Luas', 'ha')).toBe(
            'Luas maksimal 7 ha.',
        );
        expect(maximumError('', 7, 'Luas')).toBeUndefined();
        expect(maximumError('100.01', 100, 'Kadar air')).toBe(
            'Kadar air maksimal 100.',
        );
    });

    it('converts square metre limits and applies the lower fixed limit', () => {
        expect(areaMaximum(999999, 70000, 'Ha')).toBe(7);
        expect(areaMaximum(999999, 70000, 'm²')).toBe(70000);
        expect(areaMaximum(999999, 2000000, 'm²')).toBe(999999);
        expect(areaMaximum(999999, null, 'Ha')).toBe(999999);
        expect(areaMaximum(999999, 0, 'm²')).toBe(0);
        expect(
            activityDetailMaximumErrors(
                fields,
                { area: '70001', area_unit: 'm²' },
                70000,
                1000,
            )['details.area'],
        ).toBe('Luas area maksimal 70.000 m².');
        expect(
            activityDetailMaximumErrors(
                fields,
                { area: '7', area_unit: 'Ha' },
                70000,
                1000,
            ),
        ).toEqual({});
    });

    it('checks calculated areas and quantities but ignores hidden inputs', () => {
        const values = {
            bed_count: '2',
            length_m: '5',
            width_m: '1',
            quantity: '50',
            quantity_unit: 'sak',
            bag_size: '25',
        };
        expect(
            activityDetailMaximumErrors(fields, values, 9, 1000),
        ).toMatchObject({
            'details.bed_area':
                'Luas bedengan maksimal 9 m² sesuai lokasi yang dipilih.',
            'details.quantity': 'Jumlah setelah konversi maksimal 1.000 kg.',
        });
        expect(
            activityDetailMaximumErrors(
                fields,
                { ...values, quantity_unit: 'kg', bag_size: '1000000' },
                10,
                1000,
            ),
        ).toEqual({});
    });
});
