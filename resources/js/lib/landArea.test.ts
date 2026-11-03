import { describe, expect, it } from 'vitest';
import { areaValue, squareMetres } from './landArea';

describe('batch land area conversion', () => {
    it.each([
        ['5', 'ha', 50000],
        ['50000', 'm2', 50000],
        ['0,0001', 'ha', 1],
        ['1.2500', 'ha', 12500],
        ['99999999.9999', 'ha', 999999999999],
    ] as const)(
        'converts %s %s without changing the area',
        (value, unit, metres) => {
            expect(squareMetres(value, unit)).toBe(metres);
            expect(squareMetres(areaValue(metres, 'ha'), 'ha')).toBe(metres);
            expect(squareMetres(areaValue(metres, 'm2'), 'm2')).toBe(metres);
        },
    );
    it.each(['-1', '1e4', '1.00001', '50.000,5', '', 'Infinity'])(
        'rejects ambiguous or invalid input %s',
        (value) => {
            expect(squareMetres(value)).toBeNull();
        },
    );
    it('rejects sub-square-metre precision', () => {
        expect(squareMetres('0.5', 'm2')).toBeNull();
    });
});
