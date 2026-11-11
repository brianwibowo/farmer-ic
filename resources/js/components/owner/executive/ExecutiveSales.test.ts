import { effectScope, nextTick, ref } from 'vue';
import { describe, expect, it } from 'vitest';
import { useExecutiveClientFocus } from '@/composables/useExecutiveClientFocus';

const clients = [
    {
        id: 'a',
        name: 'Client A',
        contracted: 400,
        delivered: 330,
        fulfillment: 82.5,
        crops: 'Jagung',
        status: 'Aktif',
    },
    {
        id: 'b',
        name: 'Client B',
        contracted: 220,
        delivered: 200,
        fulfillment: 90.9,
        crops: 'Rumput',
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
        view.selectBar(1);
        expect(view.chartClients.value.map((client) => client.id)).toEqual([
            'b',
        ]);
        view.selectBar(0);
        expect(view.chartClients.value).toEqual(clients);
        view.toggle('a');
        view.selectedId.value = '';
        expect(view.chartClients.value).toEqual(clients);
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
