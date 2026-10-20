import { describe, expect, it } from 'vitest';
import { queryKnowledge, retrieveKnowledge } from './aiKnowledgeService';

describe('ai knowledge retrieval', () => {
    it('returns the most relevant current-finance chunk first', () => {
        const matches = retrieveKnowledge('ringkasan finansial bulan ini');

        expect(matches[0]?.id).toBe('finance-current-unavailable-v1');
        expect(matches[0]?.score).toBeGreaterThan(0);
    });

    it('answers the guarded current-finance prompt from knowledge', async () => {
        const originalWindow = globalThis.window;
        globalThis.window = {
            setTimeout: (callback: TimerHandler) => {
                if (typeof callback === 'function') callback();
                return 1;
            },
        } as unknown as Window & typeof globalThis;

        await expect(
            queryKnowledge('data keuangan bulan ini'),
        ).resolves.toMatchObject({
            chunkIds: ['finance-current-unavailable-v1'],
        });

        globalThis.window = originalWindow;
    });
});
