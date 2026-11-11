import { describe, expect, it } from 'vitest';
import { getGrowthProfile, getGrowthStageAtHst } from './growthStages';

describe('growth reference by actual HST', () => {
    const corn = getGrowthProfile('Jagung', 'Hibrida BISI-18');
    it.each([
        [0, 1],
        [7, 1],
        [8, 2],
        [39, 2],
        [45, 2],
        [46, 3],
        [60, 3],
        [61, 4],
        [95, 4],
        [96, 5],
        [105, 5],
        [150, 5],
    ])('maps corn age %i to phase %i at inclusive boundaries', (hst, phase) => {
        expect(getGrowthStageAtHst(corn, hst)?.number).toBe(phase);
    });
    it.each([null, -1, NaN, Infinity, 1.5])(
        'has no phase for an invalid or missing age: %s',
        (hst) => {
            expect(getGrowthStageAtHst(corn, hst)).toBeNull();
        },
    );
    it('follows the selected variety and commodity instead of a shared default timeline', () => {
        expect(
            getGrowthStageAtHst(getGrowthProfile('Jagung', 'Lamuru'), 44)
                ?.number,
        ).toBe(3);
        expect(getGrowthStageAtHst(corn, 44)?.number).toBe(2);
        expect(
            getGrowthStageAtHst(getGrowthProfile('Kedelai', 'Anjasmoro'), 36)
                ?.number,
        ).toBe(3);
        expect(
            getGrowthStageAtHst(getGrowthProfile('Kedelai', 'Grobogan'), 31)
                ?.number,
        ).toBe(3);
        expect(
            getGrowthStageAtHst(getGrowthProfile('Rumput'), 46)?.number,
        ).toBe(5);
    });
});
