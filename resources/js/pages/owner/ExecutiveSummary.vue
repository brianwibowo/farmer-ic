<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Sprout } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DashboardFarmMap from '@/components/dashboard/DashboardFarmMap.vue';
import OwnerDashboardFarmPreview from '@/components/owner/dashboard/OwnerDashboardFarmPreview.vue';
import ExecutiveSummaryCards from '@/components/owner/executive/ExecutiveSummaryCards.vue';
import ExecutiveSales from '@/components/owner/executive/ExecutiveSales.vue';
import ExecutiveCommoditySummary from '@/components/owner/executive/ExecutiveCommoditySummary.vue';
import ExecutiveFilters from '@/components/owner/executive/ExecutiveFilters.vue';
import ExecutiveFinance from '@/components/owner/executive/ExecutiveFinance.vue';
import ExecutiveHarvest from '@/components/owner/executive/ExecutiveHarvest.vue';
import ExecutiveLandSummary from '@/components/owner/executive/ExecutiveLandSummary.vue';
import ExecutivePanel from '@/components/owner/executive/ExecutivePanel.vue';
import { Button } from '@/components/ui/button';
import { useExecutiveSummary } from '@/composables/useExecutiveSummary';
import type { ExecutiveSummaryData } from '@/types/owner-executive';
import type { OwnerDashboardMapFarm } from '@/types/owner-dashboard';

defineOptions({ inheritAttrs: false });
const props = defineProps<{ summary: ExecutiveSummaryData }>();

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
    snapshotLabel,
    monthlyLabel,
    updatedLabel,
    number,
} = useExecutiveSummary(() => props.summary);
// Keep commercial summary sections in the codebase, but hide them from the
// Executive Summary until the business content is ready to be shown again.
const showCommercialSections = false;
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
        crops: rows.value
            .filter((item) => item.farm.id === row.farm.id && item.commodity.id)
            .map((item) => ({
                id: item.commodity.id,
                name: item.commodity.name,
                varieties: [],
            })),
        pic: null,
        detail_url: row.farm.detail_url,
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
            :count="new Set(rows.map((row) => row.farm.id)).size"
            @change="Object.assign(filters, $event)"
            @reset="reset"
            @refresh="load(true)"
        />
        <div v-if="loading" class="space-y-4">
            <ExecutiveSummaryCards
                :totals="totals"
                :commodities="commodities"
                :partner-count="partnerSummary.length"
                :month="monthlyLabel"
                :period="periodLabel"
                :show-commercial-card="showCommercialSections"
                loading
            />
            <div class="h-72 rounded-xl bg-muted motion-safe:animate-pulse" />
        </div>
        <template v-else-if="data">
            <ExecutiveSummaryCards
                :totals="totals"
                :commodities="commodities"
                :partner-count="partnerSummary.length"
                :month="monthlyLabel"
                :period="periodLabel"
                :show-commercial-card="showCommercialSections"
            />
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
                    Tidak ada lahan yang sesuai
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
                <ExecutiveSales
                    v-if="showCommercialSections"
                    :clients="clients"
                    :month="monthlyLabel"
                />
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
                <ExecutiveFinance
                    v-if="showCommercialSections"
                    :rows="rows"
                    :annual="filters.cycle === 'all'"
                />
                <ExecutivePanel
                    id="peta-kebun"
                    title="Sebaran lokasi lahan"
                    description="Pilih titik untuk membuka ringkasan lahan."
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
                Data database · diperbarui {{ updatedLabel }} ·
                {{ periodLabel }}. Panen aktual mengikuti tanggal panen;
                proyeksi memakai estimasi bobot dan jadwal panen yang tercatat.
                Luas lahan dan tanam aktif menunjukkan posisi saat ini ({{
                    snapshotLabel
                }}), bukan riwayat tahunan.
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
                            {{
                                number(
                                    rows
                                        .filter(
                                            (row) =>
                                                row.farm.id ===
                                                selectedMapFarm?.id,
                                        )
                                        .reduce(
                                            (sum, row) => sum + row.plantedHa,
                                            0,
                                        ),
                                )
                            }}
                            Ha
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Status</dt>
                        <dd class="mt-1 font-medium">
                            {{
                                rows.some(
                                    (row) =>
                                        row.farm.id === selectedMapFarm?.id &&
                                        row.plantedHa > 0,
                                )
                                    ? 'Tanam aktif'
                                    : 'Tidak ada tanam aktif'
                            }}
                        </dd>
                    </div>
                </dl></template
            >
            <template #footer
                ><Button
                    class="min-h-12 w-full"
                    @click="focusFarm(selectedMapFarm.id)"
                    >Lihat ringkasan lahan ini</Button
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
</style>
