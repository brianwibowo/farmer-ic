import { describe, expect, it } from 'vitest';
import { farmDemoSeedData } from '@/data/farmDemo';
import { buildFarmReport } from './farmDemoService';

const baseFilters = {
    period: 'month' as const,
    month: 4,
    quarter: 2 as const,
    kebunId: 'all',
    commodity: 'all' as const,
};

describe('farm report finance period boundaries', () => {
    it('keeps the selected report year in the transaction filter', () => {
        const april2026 = buildFarmReport(farmDemoSeedData, {
            ...baseFilters,
            year: 2026,
        });
        const april2025 = buildFarmReport(farmDemoSeedData, {
            ...baseFilters,
            year: 2025,
        });

        expect(april2026.finance.revenue).toBe(11_000_000);
        expect(april2026.finance.expenses).toBe(4_000_000);
        expect(april2025.finance.revenue).toBe(0);
        expect(april2025.finance.expenses).toBe(0);
    });
});
