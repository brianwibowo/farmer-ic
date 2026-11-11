<script setup lang="ts">
import { computed } from 'vue';
import { Check, ChevronDown, RotateCcw } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import ExecutiveChart from './ExecutiveChart.vue';
import ExecutivePanel from './ExecutivePanel.vue';
import ExecutiveTable from './ExecutiveTable.vue';
import type { ExecutiveColumn } from '@/types/owner-executive';
import {
    useExecutiveClientFocus,
    type ExecutiveClient,
} from '@/composables/useExecutiveClientFocus';
import { executiveNumber as number } from '@/services/ownerExecutiveDemoService';

const props = defineProps<{
    clients: ExecutiveClient[];
    month: string;
}>();
const {
    selectedId,
    selectedClient,
    chartClients,
    chartGroups,
    toggle,
    selectBar,
} = useExecutiveClientFocus(() => props.clients);
const commodityTokens: Record<string, string> = {
    jagung: '--commodity-corn',
    rumput: '--commodity-grass',
    sorgum: '--commodity-grain',
    kedelai: '--commodity-grain',
};
const commodities = computed(() => [
    ...new Map(
        props.clients
            .flatMap((client) => client.commodities)
            .map((crop) => [crop.id, crop]),
    ).values(),
]);
const colorTokens = computed(() =>
    chartGroups.value.map((crop) => commodityTokens[crop.id] ?? '--chart-4'),
);
const contractColorTokens = computed(() =>
    chartGroups.value.map((crop) =>
        commodityTokens[crop.id]
            ? `${commodityTokens[crop.id]}-contract`
            : '--muted-foreground',
    ),
);
const columns: ExecutiveColumn[] = [
    { key: 'client', label: 'Mitra / client' },
    { key: 'commodity', label: 'Komoditas' },
    { key: 'contracted', label: 'Kontrak (ton/bulan)', numeric: true },
    { key: 'delivered', label: 'Realisasi (ton/bulan)', numeric: true },
    { key: 'difference', label: 'Selisih (ton/bulan)', numeric: true },
    { key: 'fulfillment', label: 'Pemenuhan', numeric: true },
];
const detailRows = computed(() =>
    props.clients.flatMap((client) =>
        client.commodities.map((crop) => ({
            id: `${client.id}-${crop.id}`,
            clientId: client.id,
            client: client.name,
            commodity: crop.name,
            contracted: number(crop.contracted),
            delivered: number(crop.delivered),
            difference: number(crop.delivered - crop.contracted),
            fulfillment: `${number(crop.contracted ? (crop.delivered / crop.contracted) * 100 : 0)}%`,
        })),
    ),
);
</script>

<template>
    <ExecutivePanel
        id="penjualan"
        title="Kerja sama penjualan per client"
        :description="`Kontrak dan realisasi · ${month} · ton/bulan`"
    >
        <template #actions
            ><Button
                v-if="selectedClient"
                variant="outline"
                @click="selectedId = ''"
                ><RotateCcw class="size-4" aria-hidden="true" />Tampilkan semua
                client</Button
            ></template
        >
        <p
            role="status"
            aria-live="polite"
            aria-atomic="true"
            class="mb-2 text-xs leading-5 text-muted-foreground"
        >
            {{
                selectedClient
                    ? `Menampilkan ${selectedClient.name}`
                    : 'Menampilkan semua client. Pilih nama di bawah atau batang diagram untuk fokus.'
            }}
        </p>
        <div
            class="mb-3 flex flex-wrap items-center justify-between gap-x-5 gap-y-2 text-xs"
        >
            <ul
                class="flex flex-wrap gap-x-4 gap-y-2"
                aria-label="Warna komoditas"
            >
                <li
                    v-for="crop in commodities"
                    :key="crop.id"
                    class="inline-flex items-center gap-1.5"
                >
                    <span
                        class="size-3 rounded-full"
                        :style="{
                            background: `var(${commodityTokens[crop.id] ?? '--chart-4'})`,
                        }"
                        aria-hidden="true"
                    />{{ crop.name }}
                </li>
            </ul>
            <ul class="flex gap-4 text-muted-foreground" aria-label="Jenis bar">
                <li class="inline-flex items-center gap-1.5">
                    <span
                        class="h-3 w-4 rounded-sm bg-foreground/35"
                        aria-hidden="true"
                    />Kontrak (muda)
                </li>
                <li class="inline-flex items-center gap-1.5">
                    <span
                        class="h-3 w-4 rounded-sm bg-foreground"
                        aria-hidden="true"
                    />Realisasi (pekat)
                </li>
            </ul>
        </div>
        <ExecutiveChart
            :title="
                selectedClient
                    ? `Kontrak vs realisasi ${selectedClient.name}`
                    : 'Kontrak vs realisasi penjualan per client'
            "
            kind="grouped"
            :show-legend="false"
            :labels="chartGroups.map((group) => group.name)"
            :details="
                chartGroups.map(
                    (group) =>
                        `${group.clientName} · Pemenuhan ${number(group.contracted ? (group.delivered / group.contracted) * 100 : 0)}%`,
                )
            "
            :category-groups="
                chartClients.map((client) => ({
                    label: client.name,
                    count: client.commodities.length,
                }))
            "
            :min-width="chartGroups.length > 3 ? chartGroups.length * 96 : 0"
            :series="[
                {
                    label: 'Kontrak',
                    colorTokens: contractColorTokens,
                    values: chartGroups.map((crop) => crop.contracted),
                },
                {
                    label: 'Realisasi',
                    colorTokens,
                    values: chartGroups.map((crop) => crop.delivered),
                },
            ]"
            unit="ton/bulan"
            interactive
            @select="selectBar"
        />
        <p
            v-if="chartGroups.length > 3"
            class="mt-2 text-xs text-muted-foreground xl:hidden"
        >
            Geser grafik untuk melihat semua client.
        </p>
        <dl
            v-if="selectedClient"
            class="mt-3 grid grid-cols-3 gap-3 border-y border-border py-3 text-xs text-muted-foreground"
        >
            <div>
                <dt>Kontrak</dt>
                <dd class="mt-1 font-semibold text-foreground tabular-nums">
                    {{ number(selectedClient.contracted) }} ton/bulan
                </dd>
            </div>
            <div>
                <dt>Realisasi</dt>
                <dd class="mt-1 font-semibold text-foreground tabular-nums">
                    {{ number(selectedClient.delivered) }} ton/bulan
                </dd>
            </div>
            <div>
                <dt>Pemenuhan</dt>
                <dd
                    class="mt-1 font-semibold text-secondary tabular-nums dark:text-primary"
                >
                    {{ number(selectedClient.fulfillment) }}%
                </dd>
            </div>
        </dl>
        <ul
            class="mt-3 grid gap-x-4 gap-y-1 sm:grid-cols-2 xl:grid-cols-4"
            aria-label="Pemenuhan kontrak client"
        >
            <li v-for="client in clients" :key="client.id">
                <button
                    type="button"
                    class="flex min-h-11 w-full items-center justify-between gap-3 rounded-lg px-2 py-2 text-left text-xs focus-visible:outline-2 focus-visible:outline-ring"
                    :class="
                        selectedId === client.id
                            ? 'bg-primary/15 font-semibold text-secondary hover:bg-primary/20 dark:text-primary'
                            : 'hover:bg-muted'
                    "
                    :aria-pressed="selectedId === client.id"
                    @click="toggle(client.id)"
                >
                    <span class="flex min-w-0 items-center gap-1.5"
                        ><Check
                            v-if="selectedId === client.id"
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        /><span>{{ client.name }}</span></span
                    ><strong
                        class="shrink-0 text-secondary tabular-nums dark:text-primary"
                        >{{ number(client.fulfillment) }}%</strong
                    >
                </button>
            </li>
        </ul>
        <p
            v-if="selectedClient"
            class="mt-2 text-xs leading-5 text-muted-foreground"
        >
            {{ selectedClient.crops }} · {{ selectedClient.status }}
        </p>
        <details class="group mt-4 border-t border-border pt-2">
            <summary
                class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 rounded-lg px-2 text-sm font-medium text-secondary hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
            >
                <span
                    >Lihat rincian penjualan
                    <span class="ml-1 font-normal text-muted-foreground"
                        >· {{ clients.length }} mitra</span
                    ></span
                >
                <ChevronDown
                    class="size-4 group-open:rotate-180"
                    aria-hidden="true"
                />
            </summary>
            <div class="mt-2">
                <p class="mb-3 text-xs text-muted-foreground">
                    Semua mitra · {{ month }}. Selisih = realisasi − kontrak.
                    Pilih nama mitra untuk fokus pada grafik.
                </p>
                <ExecutiveTable
                    :columns="columns"
                    :rows="detailRows"
                    :caption="`Rincian penjualan per mitra dan komoditas · ${month}`"
                    selectable
                    @select="toggle(String($event.clientId))"
                />
            </div>
        </details>
    </ExecutivePanel>
</template>
