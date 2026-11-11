import { createSSRApp, effectScope, nextTick, ref } from 'vue';
import { renderToString } from '@vue/server-renderer';
import ExecutiveSales from './ExecutiveSales.vue';
import { describe, expect, it } from 'vitest';
import { useExecutiveClientFocus } from '@/composables/useExecutiveClientFocus';

const clients = [
    {
        id: 'a',
        name: 'Client A',
        contracted: 400,
        delivered: 330,
        fulfillment: 82.5,
        crops: 'Jagung, Sorgum',
        commodities: [
            { id: 'jagung', name: 'Jagung', contracted: 250, delivered: 210 },
            { id: 'sorgum', name: 'Sorgum', contracted: 150, delivered: 120 },
        ],
        status: 'Aktif',
    },
    {
        id: 'b',
        name: 'Client B',
        contracted: 220,
        delivered: 200,
        fulfillment: 90.9,
        crops: 'Rumput',
        commodities: [
            { id: 'rumput', name: 'Rumput', contracted: 220, delivered: 200 },
        ],
        status: 'Aktif',
    },
];
function focus() {
    const source = ref(structuredClone(clients));
    const scope = effectScope();
    const selection = scope.run(() => useExecutiveClientFocus(source))!;
    return { ...selection, source, stop: () => scope.stop() };
}

describe('Sales panel client focus', () => {
    it('starts sales details collapsed while keeping every client and commodity available', async () => {
        const html = await renderToString(
            createSSRApp(ExecutiveSales, { clients, month: 'November 2026' }),
        );
        const details = html.match(/<details[^>]*>/)?.[0];
        expect(details).toBeDefined();
        expect(details).not.toMatch(/\sopen(?:[\s=>])/);
        expect(html).toContain('Lihat rincian penjualan');
        expect(html).toContain('Mitra / client');
        expect(html).toContain('Selisih (ton/bulan)');
        expect(html).toContain('Pemenuhan');
        expect(html).toContain('Rincian penjualan per mitra dan komoditas');
        expect(html).toContain('Warna komoditas');
        expect(html).toContain('Jenis bar');
        expect(
            html.match(/<tbody[\s\S]*?<\/tbody>/)?.[0].match(/<tr/g),
        ).toHaveLength(3);
        for (const client of clients) {
            expect(html).toContain(client.name);
            for (const crop of client.commodities)
                expect(html).toContain(crop.name);
        }
    });
    it('focuses a client without changing the available list, then toggles back to all', () => {
        const view = focus();
        expect(view.chartClients.value).toEqual(clients);
        view.toggle('b');
        expect(view.chartClients.value.map((client) => client.id)).toEqual([
            'b',
        ]);
        expect(view.selectedClient.value?.contracted).toBe(220);
        expect(view.selectedClient.value?.delivered).toBe(200);
        expect(view.source.value).toEqual(clients);
        view.toggle('b');
        expect(view.chartClients.value).toEqual(clients);
        expect(view.selectedClient.value).toBeUndefined();
        view.stop();
    });
    it('maps bar clicks against the visible clients and supports clearing the selection', () => {
        const view = focus();
        view.selectBar(2);
        expect(view.chartClients.value.map((client) => client.id)).toEqual([
            'b',
        ]);
        view.selectBar(0);
        expect(view.chartClients.value).toEqual(clients);
        view.selectBar(1);
        expect(view.selectedClient.value?.id).toBe('a');
        expect(view.chartGroups.value.map((group) => group.name)).toEqual([
            'Jagung',
            'Sorgum',
        ]);
        view.selectedId.value = '';
        expect(view.chartClients.value).toEqual(clients);
        view.stop();
    });
    it('keeps every client available when the portfolio grows and can focus the last group', () => {
        const view = focus();
        view.source.value = Array.from({ length: 20 }, (_, index) => ({
            ...structuredClone(clients[0]),
            id: `client-${index}`,
            name: `Client ${index}`,
        }));
        expect(view.chartClients.value).toHaveLength(20);
        expect(view.chartGroups.value).toHaveLength(40);
        view.selectBar(39);
        expect(view.selectedClient.value?.id).toBe('client-19');
        expect(view.chartGroups.value).toHaveLength(2);
        expect(view.source.value).toHaveLength(20);
        view.selectedId.value = '';
        expect(view.chartGroups.value).toHaveLength(40);
        view.stop();
    });
    it('clears a client removed by global filters and retains a valid selection with fresh totals', async () => {
        const view = focus();
        view.toggle('b');
        view.source.value = [{ ...clients[1], delivered: 210 }];
        await nextTick();
        expect(view.selectedClient.value?.delivered).toBe(210);
        view.source.value = [structuredClone(clients[0])];
        await nextTick();
        expect(view.selectedId.value).toBe('');
        expect(view.chartClients.value.map((client) => client.id)).toEqual([
            'a',
        ]);
        view.stop();
    });
});
