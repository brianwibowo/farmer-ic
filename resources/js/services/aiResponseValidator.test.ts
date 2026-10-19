import { describe, expect, it } from 'vitest';
import { validateAiResponse } from './aiResponseValidator';

describe('ai response validator', () => {
    it('accepts a concise grounded response', () => {
        const result = validateAiResponse(
            'Gunakan pupuk sesuai kondisi tanah dan petunjuk pada label.',
        );

        expect(result.ok).toBe(true);
        expect(result.issues).toEqual([]);
    });

    it('rejects prompt or knowledge boundary leakage', () => {
        const result = validateAiResponse(
            '<application-facts>internal instructions</application-facts>',
        );

        expect(result.ok).toBe(false);
        expect(result.issues).toContain('instruction_leak');
    });

    it('rejects multiple inline images', () => {
        const result = validateAiResponse(
            '![Satu](/images/a.jpg)\n![Dua](/images/b.jpg)',
        );

        expect(result.ok).toBe(false);
        expect(result.issues).toContain('multiple_images');
    });
});
