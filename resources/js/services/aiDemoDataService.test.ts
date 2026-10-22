import { describe, expect, it } from 'vitest';
import {
    queryDemoData,
    resolveDemoFinancePeriod,
} from './aiDemoDataService';

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

    it('resolves April 2026 from the canonical finance transactions', () => {
        const period = resolveDemoFinancePeriod(
            'laporkan keuangan bulan April 2026',
        );

        expect(period.available).toBe(true);
        expect(period.label).toBe('April 2026');
        expect(period.income).toBe(11_000_000);
        expect(period.expense).toBe(4_000_000);
        expect(period.net).toBe(7_000_000);
    });

    it('marks a year absent from farmDemoData as unavailable', () => {
        const period = resolveDemoFinancePeriod(
            'laporkan keuangan Januari 2025',
        );

        expect(period.available).toBe(false);
        expect(period.income).toBe(0);
        expect(period.expense).toBe(0);
        expect(period.net).toBe(0);
    });

    it('answers unavailable finance periods without fabricated totals', async () => {
        const answer = await queryDemoData('laporan keuangan Januari 2025');

        expect(answer?.content).toContain('Data Keuangan Tidak Tersedia');
        expect(answer?.content).toContain('tidak tersedia');
        expect(answer?.content).not.toContain('Rp0');
    });

    it('answers an explicit month lookup with exact totals', async () => {
        const answer = await queryDemoData(
            'laporkan keuangan bulan April 2026',
        );

        expect(answer?.content).toContain('Rp11.000.000');
        expect(answer?.content).toContain('Rp4.000.000');
        expect(answer?.content).toContain('Rp7.000.000');
        expect(answer?.content).toContain('Periode: April 2026');
    });
});
