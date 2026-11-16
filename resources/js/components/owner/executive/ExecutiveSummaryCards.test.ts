import { describe, expect, it } from 'vitest';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import ExecutiveSummaryCards from './ExecutiveSummaryCards.vue';
import data from '@/data/ownerExecutiveDemo.json';
import {
    executiveCommodities,
    executiveRows,
    executiveTotals,
} from '@/services/ownerExecutiveDemoService';

const filters = {
    year: data.defaultYear,
    cycle: 'all' as const,
    partner: '',
    region: '',
    farm: '',
    commodity: '',
};

describe('Executive summary cards', () => {
    it('shows consolidated land and partnership metrics and commodity stock from the same snapshot', async () => {
        const rows = executiveRows(data, filters);
        const html = await renderToString(
            createSSRApp(ExecutiveSummaryCards, {
                totals: executiveTotals(rows),
                commodities: executiveCommodities(rows),
                partnerCount: new Set(rows.map((row) => row.partner.id)).size,
                month: 'November 2026',
            }),
        );
        expect((html.match(/<article/g) ?? []).length).toBe(3);
        for (const text of [
            'Ringkasan lahan',
            'Kerja sama aktif',
            'Kontrak penjualan',
            'ton/bulan',
            'November 2026',
            'Stok panen tersedia',
            'Jagung',
            'Rumput',
            'Sorgum',
        ])
            expect(html).toContain(text);
        expect(html).toContain('role="progressbar"');
        expect(html).not.toContain('Proyeksi omzet');
        expect(html).not.toContain('Proyeksi laba kotor');
    });

    it('keeps the three-card loading and empty states readable without invalid totals', async () => {
        const props = {
            totals: executiveTotals([]),
            commodities: [],
            partnerCount: 0,
            month: 'November 2026',
        };
        const empty = await renderToString(
            createSSRApp(ExecutiveSummaryCards, props),
        );
        expect(empty).toContain('Belum ada stok untuk filter ini.');
        expect(empty).not.toMatch(/NaN|Infinity/);
        const loading = await renderToString(
            createSSRApp(ExecutiveSummaryCards, { ...props, loading: true }),
        );
        expect(loading).toContain('Memuat ringkasan eksekutif');
        expect(loading).not.toContain('<article');
    });
});
