import { describe, expect, it } from 'vite-plus/test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import ExecutiveSummaryCards from './ExecutiveSummaryCards.vue';
import { executiveTotals } from '@/services/ownerExecutiveSummary';

const props = {
    totals: {
        ...executiveTotals([]),
        area: 64.5,
        planted: 16,
        unused: 48.5,
        utilization: 24.8,
        harvested: 34.8,
    },
    commodities: [
        { id: 'jagung', name: 'Jagung', harvested: 0 },
        { id: 'rumput', name: 'Rumput', harvested: 34.8 },
    ],
    period: '1 Januari–31 Desember 2026',
    partnerCount: 0,
    month: 'November 2026',
    showCommercialCard: false,
};
describe('Executive summary database cards', () => {
    it('shows current land use and actual harvest together with its reporting period', async () => {
        const html = await renderToString(
            createSSRApp(ExecutiveSummaryCards, props),
        );
        expect((html.match(/<article/g) ?? []).length).toBe(2);
        expect(html).toContain('Stok panen per komoditas');
        expect(html).toContain('Total panen tercatat sebelum pengiriman.');
        expect(html).toMatch(/Rumput<\/span><\/dt><dd[^>]*>34,8\s+<span/);
        expect(html).toMatch(/Jagung<\/span><\/dt><dd[^>]*>0\s+<span/);
        expect(html).toContain('1 Januari–31 Desember 2026');
        expect(html).toContain('34,8');
        expect(html).toContain('24,8%');
        expect(html).toContain('tanam aktif');
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
