<script setup lang="ts">
import { computed } from 'vue';
import ExecutiveChart from './ExecutiveChart.vue';
import ExecutivePanel from './ExecutivePanel.vue';
import {
    executiveCommodities,
    executiveMoney as money,
    executiveNumber as number,
    executiveTotals,
} from '@/services/ownerExecutiveDemoService';
import type { ExecutiveRow } from '@/types/owner-executive';

const props = defineProps<{ rows: ExecutiveRow[] }>();
const totals = computed(() => executiveTotals(props.rows));
const commodities = computed(() => executiveCommodities(props.rows));
const metrics = computed(() => [
    { label: 'Proyeksi omzet', value: money(totals.value.revenue, true) },
    { label: 'HPP + transport', value: money(totals.value.costs, true) },
    { label: 'Laba kotor', value: money(totals.value.grossProfit, true) },
    {
        label: 'Margin kotor',
        value: `${number(totals.value.grossMarginPercent)}%`,
    },
]);
</script>

<template>
    <ExecutivePanel
        id="proyeksi-keuangan"
        title="Proyeksi keuangan satu cycle"
        description="Harga jual berbeda per komoditas. Perhitungan mengikuti luas tanam terpilih."
    >
        <dl
            class="mb-5 grid grid-cols-2 gap-4 border-b border-border pb-5 lg:grid-cols-4"
        >
            <div v-for="metric in metrics" :key="metric.label">
                <dt class="text-xs text-muted-foreground">
                    {{ metric.label }}
                </dt>
                <dd
                    class="mt-1.5 text-xl font-semibold tabular-nums sm:text-2xl"
                >
                    {{ metric.value }}
                </dd>
            </div>
        </dl>
        <div
            class="grid items-center gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]"
        >
            <div>
                <p class="mb-1 text-xs text-muted-foreground">
                    Komponen omzet · Rp juta
                </p>
                <ExecutiveChart
                    title="Komponen omzet dan laba kotor per komoditas"
                    kind="horizontal"
                    stacked
                    :labels="commodities.map((crop) => crop.name)"
                    :series="[
                        {
                            label: 'HPP',
                            values: commodities.map(
                                (crop) => crop.totalHpp / 1000000,
                            ),
                        },
                        {
                            label: 'Transport',
                            values: commodities.map(
                                (crop) => crop.totalTransport / 1000000,
                            ),
                        },
                        {
                            label: 'Laba kotor',
                            values: commodities.map(
                                (crop) => crop.grossProfit / 1000000,
                            ),
                        },
                    ]"
                    unit="juta rupiah"
                />
            </div>
            <ul
                class="divide-y divide-border"
                aria-label="Ringkasan keuangan per komoditas"
            >
                <li
                    v-for="crop in commodities"
                    :key="crop.id"
                    class="py-3 first:pt-0 last:pb-0"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <div>
                            <h3 class="text-sm font-semibold">
                                {{ crop.name }}
                            </h3>
                            <p
                                class="mt-1 text-xs leading-5 text-muted-foreground"
                            >
                                Jual {{ money(crop.pricing.sellingPrice) }}/kg ·
                                margin {{ money(crop.marginPerKg) }}/kg
                            </p>
                        </div>
                        <div class="text-right">
                            <p
                                class="text-sm font-semibold text-secondary tabular-nums dark:text-primary"
                            >
                                {{ money(crop.grossProfit, true) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                laba kotor
                            </p>
                        </div>
                    </div>
                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                        HPP {{ money(crop.pricing.hpp) }}/kg · transport
                        {{ money(crop.pricing.transport) }}/kg · omzet
                        {{ money(crop.revenue, true) }}
                    </p>
                </li>
            </ul>
        </div>
        <p class="mt-4 text-xs leading-5 text-muted-foreground">
            Asumsi harga contoh; belum termasuk pajak dan biaya lain. Margin =
            harga jual − HPP − transport.
        </p>
    </ExecutivePanel>
</template>
