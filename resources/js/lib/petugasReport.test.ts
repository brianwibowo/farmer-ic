import { describe, expect, it } from 'vitest';
import { formatReportDetails } from './petugasReport';

describe('petugas report area presentation', () => {
    it.each([
        ['5', 'm²', '5 m²'],
        ['5.0', 'm2', '5 m²'],
        ['0.0001', 'Ha', '1 m²'],
        ['1.2500', 'ha', '12.500 m²'],
    ])('converts the entered %s %s to %s', (value, unit, expected) => {
        expect(
            formatReportDetails([
                { label: 'Luas input', value },
                { label: 'Satuan luas', value: unit },
            ]),
        ).toEqual([{ label: 'Luas', value: expected }]);
    });

    it('prefers the recorded input and does not repeat the stored area', () => {
        expect(
            formatReportDetails([
                { label: 'Luas (ha)', value: '1.0000' },
                { label: 'Metode', value: 'Manual' },
                { label: 'Luas input', value: '5' },
                { label: 'Satuan luas', value: 'm²' },
            ]),
        ).toEqual([
            { label: 'Luas', value: '5 m²' },
            { label: 'Metode', value: 'Manual' },
        ]);
    });

    it.each([
        ['Luas (ha)', 'Luas', '0.0005', '5 m²'],
        ['Luas terdampak (ha)', 'Luas terdampak', '0.1250', '1.250 m²'],
        ['Luas panen (ha)', 'Luas panen', '0.5000', '5.000 m²'],
        ['Luas (ha)', 'Luas', '0.0000', '0 m²'],
    ])('converts legacy %s to %s', (label, expectedLabel, value, expected) => {
        expect(formatReportDetails([{ label, value }])).toEqual([
            { label: expectedLabel, value: expected },
        ]);
    });

    it('falls back to stored hectares when the entered unit is unknown', () => {
        expect(
            formatReportDetails([
                { label: 'Luas (ha)', value: '0.5000' },
                { label: 'Luas input', value: '5' },
                { label: 'Satuan luas', value: 'tidak dikenal' },
            ]),
        ).toEqual([{ label: 'Luas', value: '5.000 m²' }]);
    });

    it('does not invent an area unit for incomplete legacy data', () => {
        expect(
            formatReportDetails([{ label: 'Luas input', value: '5' }]),
        ).toEqual([
            { label: 'Luas', value: '5 (satuan tidak dapat dipastikan)' },
        ]);
    });

    it('handles invalid values and reports without an area', () => {
        expect(
            formatReportDetails([{ label: 'Luas (ha)', value: 'invalid' }]),
        ).toEqual([{ label: 'Luas', value: 'Tidak dapat dikonversi' }]);
        expect(
            formatReportDetails([{ label: 'Metode', value: 'Manual' }]),
        ).toEqual([{ label: 'Metode', value: 'Manual' }]);
    });
});
