<script setup lang="ts">
import { Check, RotateCcw } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import ExecutiveChart from './ExecutiveChart.vue';
import ExecutivePanel from './ExecutivePanel.vue';
import {
    useExecutiveClientFocus,
    type ExecutiveClient,
} from '@/composables/useExecutiveClientFocus';
import { executiveNumber as number } from '@/services/ownerExecutiveDemoService';

const props = defineProps<{
    clients: ExecutiveClient[];
    month: string;
}>();
const { selectedId, selectedClient, chartClients, toggle, selectBar } =
    useExecutiveClientFocus(() => props.clients);
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
        <ExecutiveChart
            :title="
                selectedClient
                    ? `Kontrak vs realisasi ${selectedClient.name}`
                    : 'Kontrak vs realisasi penjualan per client'
            "
            kind="horizontal"
            :labels="chartClients.map((client) => client.name)"
            :series="[
                {
                    label: 'Kontrak',
                    values: chartClients.map((client) => client.contracted),
                },
                {
                    label: 'Realisasi',
                    values: chartClients.map((client) => client.delivered),
                },
            ]"
            unit="ton/bulan"
            :compact="!!selectedClient"
            interactive
            @select="selectBar"
        />
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
            class="mt-3 grid gap-x-4 gap-y-1 sm:grid-cols-2"
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
    </ExecutivePanel>
</template>
