import { describe, expect, it } from 'vitest';
import { matchesSubblockView } from './subblockView';

describe('subblock display filters', () => {
    it('keeps preparation and active planting together', () => {
        expect(matchesSubblockView('planned', 'running')).toBe(true);
        expect(matchesSubblockView('active', 'running')).toBe(true);
        expect(matchesSubblockView('harvested', 'running')).toBe(false);
        expect(matchesSubblockView('cancelled', 'running')).toBe(false);
    });

    it('separates harvest and cancellation without hiding either from all', () => {
        expect(matchesSubblockView('harvested', 'harvested')).toBe(true);
        expect(matchesSubblockView('cancelled', 'harvested')).toBe(false);
        expect(matchesSubblockView('cancelled', 'cancelled')).toBe(true);
        expect(matchesSubblockView('harvested', 'cancelled')).toBe(false);
        for (const status of ['planned', 'active', 'harvested', 'cancelled']) {
            expect(matchesSubblockView(status, 'all')).toBe(true);
        }
    });
});
