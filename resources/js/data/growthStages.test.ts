import { describe, expect, it } from 'vite-plus/test';
import {
    findObservedGrowthStage,
    getGrowthProfile,
    getGrowthStageAtHst,
    resolveGrowthProfile,
} from './growthStages';

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

    it('resolves commodity profiles and matches observed phase labels and short labels', () => {
        const profile = resolveGrowthProfile('Jagung Hibrida', 'Bisi 18');
        expect(profile?.commodity).toBe('Jagung');
        expect(findObservedGrowthStage(profile, 'Vegetatif')?.number).toBe(2);
        expect(findObservedGrowthStage(profile, 'Pembungaan')?.number).toBe(3);
        expect(resolveGrowthProfile('Sorgum')).toBeNull();
    });
});
