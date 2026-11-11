import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { describe, expect, it } from 'vitest';
import OwnerDistributionChart from './OwnerDistributionChart.vue';
import OwnerDashboardCommodityChart from './dashboard/OwnerDashboardCommodityChart.vue';

describe('Shared Owner distribution chart', () => {
    it('retains the dashboard commodity totals, percentages, and native legend controls', async () => {
        const html = await renderToString(
            createSSRApp(OwnerDashboardCommodityChart, {
                commodities: [
                    {
                        id: 'jagung',
                        name: 'Jagung',
                        plantedArea: '10',
                        areaValue: 10,
                        cycleCount: 1,
                    },
                    {
                        id: 'rumput',
                        name: 'Rumput',
                        plantedArea: '5',
                        areaValue: 5,
                        cycleCount: 1,
                    },
                ],
            }),
        );
        expect(html).toContain('Total luas tanam 15 ha.');
        expect(html).toContain('66,7%');
        expect(html).toContain('33,3%');
        expect(html.match(/aria-pressed="false"/g)).toHaveLength(2);
        expect(html).not.toContain('NaN');
    });
    it('uses the supplied production unit in the center and accessible descriptions', async () => {
        const html = await renderToString(
            createSSRApp(OwnerDistributionChart, {
                items: [{ id: 'jagung', name: 'Jagung', value: 400 }],
                title: 'Panen',
                unit: 'ton',
                totalLabel: 'Panen aktual',
                categoryLabel: 'komoditas',
                embedded: true,
                compact: true,
            }),
        );
        expect(html).toContain('Total panen aktual 400 ton.');
        expect(html).toContain('Jagung, 400 ton, 100%');
        expect(html).not.toContain('hektar');
        expect(html).not.toContain('NaN');
    });
});
