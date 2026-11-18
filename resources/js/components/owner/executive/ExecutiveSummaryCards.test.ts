import { describe, expect, it } from 'vite-plus/test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import ExecutiveSummaryCards from './ExecutiveSummaryCards.vue';
import { executiveTotals } from '@/services/ownerExecutiveSummary';

const props = {
    totals: executiveTotals([]),
    commodities: [],
    partnerCount: 0,
    month: 'November 2026',
    showCommercialCard: false,
};
describe('Executive summary database cards', () => {
    it('clearly marks unknown stock instead of showing a fabricated balance', async () => {
        const html = await renderToString(
            createSSRApp(ExecutiveSummaryCards, props),
        );
        expect((html.match(/<article/g) ?? []).length).toBe(2);
        expect(html).toContain('Belum tersedia. Saldo stok');
        expect(html).toContain('Luas tanam aktif · posisi hari ini');
        expect(html).not.toMatch(/NaN|Infinity|Data contoh/);
    });
    it('preserves loading feedback', async () => {
        const html = await renderToString(
            createSSRApp(ExecutiveSummaryCards, { ...props, loading: true }),
        );
        expect(html).toContain('Memuat ringkasan eksekutif');
        expect(html).not.toContain('<article');
    });
});
