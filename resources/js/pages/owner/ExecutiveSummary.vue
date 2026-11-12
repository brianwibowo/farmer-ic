<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ArrowDownToLine,
    ChartNoAxesCombined,
    Handshake,
    LandPlot,
    Package,
    RefreshCw,
    Sprout,
    TrendingUp,
    Wallet,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DashboardFarmMap from '@/components/dashboard/DashboardFarmMap.vue';
import OwnerDashboardFarmPreview from '@/components/owner/dashboard/OwnerDashboardFarmPreview.vue';
import ExecutiveSales from '@/components/owner/executive/ExecutiveSales.vue';
import ExecutiveCommoditySummary from '@/components/owner/executive/ExecutiveCommoditySummary.vue';
import ExecutiveFilters from '@/components/owner/executive/ExecutiveFilters.vue';
import ExecutiveFinance from '@/components/owner/executive/ExecutiveFinance.vue';
import ExecutiveHarvest from '@/components/owner/executive/ExecutiveHarvest.vue';
import ExecutiveLandSummary from '@/components/owner/executive/ExecutiveLandSummary.vue';
import ExecutivePanel from '@/components/owner/executive/ExecutivePanel.vue';
import { Button } from '@/components/ui/button';
import { useExecutiveSummary } from '@/composables/useExecutiveSummary';
import type { OwnerDashboardMapFarm } from '@/types/owner-dashboard';

const {
    data,
    loading,
    error,
    feedback,
    filters,
    rows,
    totals,
    years,
    cycles,
    regions,
    farmOptions,
    partnerSummary,
    landCategories,
    commodities,
    clients,
    mapFarms,
    reset,
    load,
    periodLabel,
    monthlyLabel,
    updatedLabel,
    number,
    money,
} = useExecutiveSummary();
const selectedMapId = ref<string | null>(null);
const selectedMapRow = computed(() =>
    rows.value.find((row) => row.farm.id === selectedMapId.value),
);
const selectedMapFarm = computed<OwnerDashboardMapFarm | undefined>(() => {
    const row = selectedMapRow.value;
    if (!row) return undefined;
    return {
        ...row.farm,
        location: `${row.farm.city}, ${row.farm.region}`,
        area: number(row.farm.areaHa),
        crops: [
            { id: row.commodity.id, name: row.commodity.name, varieties: [] },
        ],
        pic: null,
        detail_url: '/ringkasan-eksekutif',
    };
});
const mapPreviewOpen = computed({
    get: () => !!selectedMapFarm.value,
    set: (value: boolean) => {
        if (!value) selectedMapId.value = null;
    },
});
watch(rows, () => {
    if (!selectedMapRow.value) selectedMapId.value = null;
});
const metrics = computed(() => [
    {
        label: 'Kerja sama aktif',
        value: number(totals.value.agreements, 0),
        unit: 'perjanjian',
        note: `${partnerSummary.value.length} mitra · per kebun`,
        icon: Handshake,
    },
    {
        label: 'Total luas lahan',
        value: number(totals.value.area),
        unit: 'Ha',
        note: 'Lahan kerja sama terpilih',
        icon: LandPlot,
    },
    {
        label: 'Total luas tanam',
        value: number(totals.value.planted),
        unit: 'Ha',
        note: 'Area tertanam pada cycle ini',
        icon: Sprout,
    },
    {
        label: 'Utilisasi lahan',
        value: number(totals.value.utilization),
        unit: '%',
        note: `${number(totals.value.unused)} Ha belum ditanam`,
        icon: ChartNoAxesCombined,
    },
    {
        label: 'Kontrak penjualan',
        value: number(totals.value.contracts),
        unit: 'ton/bulan',
        note: monthlyLabel.value,
        icon: ArrowDownToLine,
    },
    {
        label: 'Stok panen tersedia',
        value: number(totals.value.stock),
        unit: 'ton',
        note: 'Setelah pengiriman & penyesuaian',
        icon: Package,
    },
    {
        label: 'Proyeksi omzet',
        value: money(totals.value.revenue, true),
        unit: '/ cycle',
        note: 'Harga jual per komoditas',
        icon: Wallet,
    },
    {
        label: 'Proyeksi laba kotor',
        value: money(totals.value.grossProfit, true),
        unit: '/ cycle',
        note: `${number(totals.value.grossMarginPercent)}% margin kotor`,
        icon: TrendingUp,
    },
]);
function focusFarm(id: string) {
    filters.farm = id;
    selectedMapId.value = null;
    document.getElementById('ringkasan-kpi')?.scrollIntoView({
        behavior: matchMedia('(prefers-reduced-motion: reduce)').matches
            ? 'auto'
            : 'smooth',
        block: 'start',
    });
}
</script>

<template>
    <Head title="Ringkasan Eksekutif" />
    <div
        class="executive-summary min-w-0 space-y-4 text-foreground lg:space-y-5"
        :aria-busy="loading"
    >
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1
                    class="text-2xl leading-tight font-semibold tracking-tight sm:text-3xl"
                >
                    Ringkasan Eksekutif
                </h1>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    Gambaran lahan, penjualan, dan potensi hasil usaha.
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Data per {{ updatedLabel }}
                </p>
            </div>
            <Button :disabled="loading" @click="load(true)"
                ><RefreshCw
                    class="size-4"
                    :class="loading ? 'motion-safe:animate-spin' : ''"
                    aria-hidden="true"
                />{{ loading ? 'Memuat…' : 'Refresh' }}</Button
            >
        </header>
        <p
            v-if="feedback"
            role="status"
            class="text-sm text-secondary dark:text-primary"
        >
            {{ feedback }}
        </p>
        <div
            v-if="error"
            role="alert"
            class="rounded-xl border border-error p-4"
        >
            <p class="text-sm text-error">{{ error }}</p>
            <Button
                class="mt-3"
                variant="outline"
                :disabled="loading"
                @click="load(Boolean(data))"
                >Coba lagi</Button
            >
        </div>
        <ExecutiveFilters
            v-if="data"
            :filters="filters"
            :data="data"
            :years="years"
            :cycles="cycles"
            :regions="regions"
            :farm-options="farmOptions"
            :loading="loading"
            :count="rows.length"
            @change="Object.assign(filters, $event)"
            @reset="reset"
        />
        <div v-if="loading" role="status" class="space-y-4">
            <span class="sr-only">Memuat ringkasan eksekutif…</span>
            <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                <div
                    v-for="i in 8"
                    :key="i"
                    class="h-28 rounded-xl bg-muted motion-safe:animate-pulse"
                />
            </div>
            <div class="h-72 rounded-xl bg-muted motion-safe:animate-pulse" />
        </div>
        <template v-else-if="data">
            <div
                id="ringkasan-kpi"
                class="grid grid-cols-2 gap-3 xl:grid-cols-4"
                aria-label="Indikator utama"
            >
                <article
                    v-for="metric in metrics"
                    :key="metric.label"
                    class="min-w-0 rounded-xl border border-border bg-card p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <h2
                            class="text-xs leading-5 font-medium text-muted-foreground sm:text-sm"
                        >
                            {{ metric.label }}
                        </h2>
                        <component
                            :is="metric.icon"
                            class="size-4 shrink-0 text-secondary dark:text-primary"
                            aria-hidden="true"
                        />
                    </div>
                    <p
                        class="mt-3 flex flex-wrap items-baseline gap-x-2 gap-y-1"
                    >
                        <span
                            class="text-xl leading-tight font-semibold break-words tabular-nums sm:text-2xl 2xl:text-3xl"
                            >{{ metric.value }}</span
                        ><span class="text-xs text-muted-foreground">{{
                            metric.unit
                        }}</span>
                    </p>
                    <p class="mt-1.5 text-xs leading-5 text-muted-foreground">
                        {{ metric.note }}
                    </p>
                </article>
            </div>
            <div
                v-if="!rows.length"
                role="status"
                class="rounded-xl border border-border bg-card px-5 py-10 text-center"
            >
                <Sprout
                    class="mx-auto mb-3 size-8 text-muted-foreground"
                    aria-hidden="true"
                />
                <h2 class="text-lg font-semibold">
                    Tidak ada kebun yang sesuai
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Ubah kombinasi filter untuk melihat ringkasan.
                </p>
                <Button class="mt-4" variant="outline" @click="reset"
                    >Reset filter</Button
                >
            </div>
            <template v-else>
                <ExecutiveLandSummary
                    :partners="partnerSummary"
                    :categories="landCategories"
                    :selected="filters.partner"
                    @select="filters.partner = $event"
                />
                <ExecutiveSales :clients="clients" :month="monthlyLabel" />
                <ExecutiveCommoditySummary
                    :commodities="commodities"
                    :selected="filters.commodity"
                    @select="filters.commodity = $event"
                />
                <ExecutiveHarvest
                    :rows="rows"
                    :period="periodLabel"
                    @select="focusFarm"
                />
                <ExecutiveFinance :rows="rows" />
                <ExecutivePanel
                    id="peta-kebun"
                    title="Sebaran lokasi kebun"
                    description="Pilih titik untuk membuka ringkasan kebun."
                    ><DashboardFarmMap
                        :farms="mapFarms"
                        selectable
                        fit-on-resize
                        class="mt-0! h-64! sm:h-80!"
                        @select="selectedMapId = $event"
                /></ExecutivePanel>
            </template>
            <footer
                class="border-t border-border pt-3 text-xs leading-5 text-muted-foreground"
            >
                Data contoh · {{ updatedLabel }} · {{ periodLabel }}. Stok
                merupakan saldo setelah pengiriman dan penyesuaian pada snapshot
                cycle terpilih.
            </footer>
        </template>
        <OwnerDashboardFarmPreview
            v-if="selectedMapFarm && selectedMapRow"
            v-model:open="mapPreviewOpen"
            :farm="selectedMapFarm"
        >
            <template #summary
                ><dl
                    class="grid grid-cols-2 gap-3 border-t border-border pt-3 text-sm"
                >
                    <div class="col-span-2">
                        <dt class="text-xs text-muted-foreground">
                            Mitra kerja sama
                        </dt>
                        <dd class="mt-1 font-medium">
                            {{ selectedMapRow.partner.name }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Luas tanam
                        </dt>
                        <dd class="mt-1 font-medium">
                            {{ number(selectedMapRow.plantedHa) }} Ha
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Status</dt>
                        <dd class="mt-1 font-medium">
                            {{ selectedMapRow.status }}
                        </dd>
                    </div>
                </dl></template
            >
            <template #footer
                ><Button
                    class="min-h-12 w-full"
                    @click="focusFarm(selectedMapFarm.id)"
                    >Lihat ringkasan kebun ini</Button
                ></template
            >
        </OwnerDashboardFarmPreview>
    </div>
</template>

<style>
.executive-summary .exec-label {
    display: grid;
    min-width: 0;
    gap: 0.375rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--muted-foreground);
}
.executive-summary .exec-field {
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
    min-height: 44px;
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    background: var(--card);
    padding: 0.625rem 0.75rem;
    font-size: 1rem;
    line-height: 1.4;
    color: var(--foreground);
}
.executive-summary .exec-field:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 2px;
}
.executive-summary .exec-field:disabled {
    opacity: 0.6;
    cursor: wait;
}
.executive-summary section,
.executive-summary [id] {
    scroll-margin-top: 1rem;
}
.executive-summary {
    accent-color: var(--primary);
    caret-color: var(--primary);
}
.executive-summary summary::-webkit-details-marker {
    display: none;
}
@media (min-width: 640px) {
    .executive-summary .exec-field {
        font-size: 0.875rem;
    }
}
</style>
