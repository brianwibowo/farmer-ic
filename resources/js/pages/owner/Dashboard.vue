<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { RefreshCw, SlidersHorizontal } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import OwnerActivityComparison from '@/components/owner/dashboard/OwnerActivityComparison.vue';
import OwnerBatchTimeline from '@/components/owner/dashboard/OwnerBatchTimeline.vue';
import OwnerHarvestForecast from '@/components/owner/dashboard/OwnerHarvestForecast.vue';
import OwnerLandComparison from '@/components/owner/dashboard/OwnerLandComparison.vue';
import OwnerPortfolioOverview from '@/components/owner/dashboard/OwnerPortfolioOverview.vue';
import OwnerPageHeader from '@/components/owner/OwnerPageHeader.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Button } from '@/components/ui/button';
import {
    getOwnerDashboardDemo,
    ownerDashboardDemoFarms,
} from '@/data/ownerDashboardDemo';

const filterPanel = ref<HTMLDetailsElement | null>(null);
const filters = ref({ farm_id: '', period: '7' });
const appliedFilters = ref({ ...filters.value });
const loading = ref(false);
const error = ref('');
const feedback = ref('');
let previewTimer: ReturnType<typeof setTimeout> | undefined;
const dashboard = computed(() =>
    getOwnerDashboardDemo(
        appliedFilters.value.farm_id,
        appliedFilters.value.period,
    ),
);
const insight = computed(() => dashboard.value.insights);
function submit(): void {
    if (loading.value) return;
    error.value = '';
    feedback.value = '';
    if (
        !['7', '30'].includes(filters.value.period) ||
        (filters.value.farm_id &&
            !ownerDashboardDemoFarms.some(
                (farm) => farm.id === filters.value.farm_id,
            ))
    ) {
        error.value = 'Pilih kebun dan periode yang tersedia.';
        return;
    }
    const nextFilters = { ...filters.value };
    loading.value = true;
    previewTimer = setTimeout(() => {
        appliedFilters.value = nextFilters;
        loading.value = false;
        feedback.value = 'Pratinjau grafik diperbarui.';
        if (filterPanel.value) filterPanel.value.open = false;
    }, 350);
}
onBeforeUnmount(() => clearTimeout(previewTimer));
const selectedFarm = computed(
    () =>
        dashboard.value.farmOptions.find(
            (farm) => farm.id === dashboard.value.filters.farm_id,
        )?.name ?? 'Semua kebun',
);
const updatedAt = computed(() =>
    new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(insight.value.asOf)),
);
</script>

<template>
    <Head title="Beranda Owner" />
    <div class="owner-dashboard space-y-5" :aria-busy="loading">
        <header class="space-y-3">
            <div
                class="flex items-center justify-between gap-2 text-[11px] text-muted-foreground"
            >
                <span class="font-semibold tracking-[0.12em] uppercase"
                    >Beranda</span
                ><span>{{ updatedAt }}</span>
            </div>
            <OwnerPageHeader
                title="Pemantauan kebun"
                description="Kondisi tanam hari ini, perubahan kegiatan, dan arah panen berikutnya."
            />
            <p
                class="rounded-xl border border-border bg-muted/60 px-3 py-2.5 text-xs leading-5 text-muted-foreground"
            >
                <span class="font-semibold text-foreground"
                    >Data ilustrasi.</span
                >
                Data contoh untuk diskusi tampilan, bukan data kebun Anda.
            </p>
        </header>
        <details
            ref="filterPanel"
            class="rounded-xl border border-border bg-card px-3.5 py-1.5"
        >
            <summary
                class="min-h-11 cursor-pointer content-center rounded-lg text-xs focus-visible:outline-2 focus-visible:outline-ring"
            >
                <span
                    class="ml-1 inline-flex items-center gap-1.5 font-semibold"
                    ><SlidersHorizontal
                        class="size-3.5"
                        aria-hidden="true"
                    />Filter grafik</span
                ><span class="ml-2 text-muted-foreground"
                    >{{ selectedFarm }} · {{ appliedFilters.period }} hari</span
                >
            </summary>
            <form class="pb-2" @submit.prevent="submit()">
                <div class="owner-dashboard-filters mt-3 grid min-w-0 gap-3">
                    <div class="min-w-0">
                        <label
                            for="overview-farm"
                            class="mb-1.5 block text-xs text-muted-foreground"
                            >Kebun</label
                        ><AppSelect
                            id="overview-farm"
                            v-model="filters.farm_id"
                            :disabled="loading"
                            ><AppSelectOption value=""
                                >Semua kebun</AppSelectOption
                            ><AppSelectOption
                                v-for="farm in dashboard.farmOptions"
                                :key="farm.id"
                                :value="farm.id"
                                >{{ farm.name }}</AppSelectOption
                            ></AppSelect
                        >
                    </div>
                    <div class="flex min-w-0 items-end gap-2">
                        <div class="min-w-0 flex-1">
                            <label
                                for="overview-period"
                                class="mb-1.5 block text-xs text-muted-foreground"
                                >Periode komparasi aktivitas</label
                            ><AppSelect
                                id="overview-period"
                                v-model="filters.period"
                                :disabled="loading"
                                ><AppSelectOption value="7"
                                    >7 hari terakhir</AppSelectOption
                                ><AppSelectOption value="30"
                                    >30 hari terakhir</AppSelectOption
                                ></AppSelect
                            >
                        </div>
                        <Button
                            class="min-h-11 shrink-0"
                            :disabled="loading"
                            type="submit"
                            ><RefreshCw
                                class="size-4"
                                :class="
                                    loading ? 'motion-safe:animate-spin' : ''
                                "
                                aria-hidden="true"
                            />{{ loading ? 'Memuat…' : 'Terapkan' }}</Button
                        >
                    </div>
                </div>
            </form>
        </details>
        <p
            v-if="error || feedback"
            class="mt-3 text-xs leading-5"
            :role="error ? 'alert' : 'status'"
            :class="error ? 'text-error' : 'text-muted-foreground'"
        >
            {{ error || feedback }}
        </p>
        <p
            v-if="!dashboard.farmOptions.length"
            role="status"
            class="rounded-xl bg-muted p-4 text-sm leading-6 text-muted-foreground"
        >
            Belum ada kebun dalam pratinjau ini.
        </p>
        <div
            class="owner-dashboard-grid grid min-w-0 items-start gap-4"
            :class="loading ? 'opacity-60' : ''"
        >
            <OwnerPortfolioOverview
                :statuses="insight.batchStatus"
                :risks="insight.risks"
            />
            <OwnerBatchTimeline
                :timeline="insight.timeline"
                :as-of="insight.asOf"
            />
            <OwnerHarvestForecast :forecast="insight.harvestForecast" />
            <OwnerActivityComparison :comparison="insight.activityComparison" />
            <OwnerLandComparison
                :land="insight.landUse"
                class="owner-land-comparison"
            />
        </div>
        <p class="pb-2 text-center text-[11px] leading-5 text-muted-foreground">
            Data contoh untuk meninjau tampilan grafik. Pilihan kebun dan
            periode hanya mengubah pratinjau ini.
        </p>
    </div>
</template>

<style scoped>
.owner-dashboard {
    container-name: owner-dashboard;
    container-type: inline-size;
}
@container owner-dashboard (min-width: 620px) {
    .owner-dashboard-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .owner-dashboard-filters {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr);
    }
    .owner-land-comparison {
        grid-column: 1 / -1;
    }
}
</style>
