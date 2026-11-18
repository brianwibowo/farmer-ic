import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { describe, expect, it } from 'vite-plus/test';
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
    it('keeps all partner areas in the chart while limiting the detail cards', async () => {
        const items = [10, 8, 6, 4, 2].map((area, index) => ({
            id: String(index),
            name: `Mitra ${index + 1}`,
            value: area,
        }));
        const render = (visibleItems: typeof items) =>
            renderToString(
                createSSRApp({
                    render: () =>
                        h(
                            OwnerDistributionChart,
                            {
                                items: visibleItems,
                                legendLimit: 3,
                                totalLabel: 'Luas lahan',
                            },
                            {
                                'legend-overflow': ({
                                    count,
                                }: {
                                    count: number;
                                }) => `${count} mitra lainnya`,
                            },
                        ),
                }),
            );

        const html = await render(items);
        expect(html).toContain('Total luas lahan 30 Ha.');
        expect(html).toContain('Mitra 5, 2 Ha, 6,7%');
        expect(html.match(/aria-pressed="false"/g)).toHaveLength(3);
        expect(html).toContain('2 mitra lainnya');

        expect(await render(items.slice(0, 3))).not.toContain('mitra lainnya');
    });
});
