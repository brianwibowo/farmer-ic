import { computed, ref, toValue, watch, type MaybeRefOrGetter } from 'vue';

export type ExecutiveClient = {
    id: string;
    name: string;
    contracted: number;
    delivered: number;
    fulfillment: number;
    crops: string;
    commodities: {
        id: string;
        name: string;
        contracted: number;
        delivered: number;
    }[];
    status: string;
};

// Panel-local UI state: never writes to the source list or global page filters.
export function useExecutiveClientFocus(
    source: MaybeRefOrGetter<ExecutiveClient[]>,
) {
    const selectedId = ref('');
    const selectedClient = computed(() =>
        toValue(source).find((client) => client.id === selectedId.value),
    );
    const chartClients = computed(() =>
        selectedClient.value ? [selectedClient.value] : toValue(source),
    );
    const chartGroups = computed(() =>
        chartClients.value.flatMap((client) =>
            client.commodities.map((crop) => ({
                ...crop,
                clientId: client.id,
                clientName: client.name,
            })),
        ),
    );
    function toggle(id: string) {
        selectedId.value = selectedId.value === id ? '' : id;
    }
    function selectBar(index: number) {
        const group = chartGroups.value[index];
        if (group) toggle(group.clientId);
    }
    watch(
        () => toValue(source),
        (clients) => {
            if (!clients.some((client) => client.id === selectedId.value))
                selectedId.value = '';
        },
    );
    return {
        selectedId,
        selectedClient,
        chartClients,
        chartGroups,
        toggle,
        selectBar,
    };
}
