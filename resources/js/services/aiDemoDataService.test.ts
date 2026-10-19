import { describe, expect, it } from 'vitest';
import { queryDemoData } from './aiDemoDataService';

describe('demo data resolver boundaries', () => {
    it.each([
        'finansial bulan ini',
        'data keuangan bulan ini',
        'pendapatan periode ini',
    ])(
        'does not resolve unavailable current finance locally: %s',
        async (prompt) => {
            await expect(queryDemoData(prompt)).resolves.toBeNull();
        },
    );
});
