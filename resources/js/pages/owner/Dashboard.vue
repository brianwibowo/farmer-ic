<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { RefreshCw } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DashboardFarmMap from '@/components/dashboard/DashboardFarmMap.vue';
import OwnerDashboardCommodityChart from '@/components/owner/dashboard/OwnerDashboardCommodityChart.vue';
import OwnerDashboardFarmPreview from '@/components/owner/dashboard/OwnerDashboardFarmPreview.vue';
import OwnerDashboardLandTable from '@/components/owner/dashboard/OwnerDashboardLandTable.vue';
import OwnerDashboardMetrics from '@/components/owner/dashboard/OwnerDashboardMetrics.vue';
import { Button } from '@/components/ui/button';
import { useOwnerReadFilters } from '@/composables/useOwnerReadFilters';
import type {
    OwnerDashboardData,
    OwnerDashboardLandTable as OwnerLandTableData,
} from '@/types/owner-dashboard';

const props = defineProps<{
    dashboard: OwnerDashboardData;
    landTable: OwnerLandTableData;
}>();
const page = usePage();
const welcomeName = computed(() => page.props.auth.user?.name?.trim() ?? '');
const { loading, error, feedback, submit } = useOwnerReadFilters('/', {});
const updatedAt = computed(() => props.dashboard.generatedAt);
const selectedMapFarmId = ref<string | null>(null);
const selectedMapFarm = computed(() =>
    props.dashboard.landMap.farms.find(
        (farm) => farm.id === selectedMapFarmId.value,
    ),
);
const mapPreviewOpen = computed({
    get: () => selectedMapFarm.value !== undefined,
    set: (open: boolean) => {
        if (!open) selectedMapFarmId.value = null;
    },
});
watch(
    () => props.dashboard.landMap.farms,
    (farms) => {
        if (!farms.some((farm) => farm.id === selectedMapFarmId.value))
            selectedMapFarmId.value = null;
    },
);
</script>

<template>
    <Head title="Beranda Owner" />
    <div
        class="owner-dashboard min-w-0 space-y-4 lg:space-y-6"
        :aria-busy="loading"
    >
        <div
            class="owner-dashboard-header flex items-center justify-between gap-3"
        >
            <header class="min-w-0">
                <h1
                    class="text-xl leading-7 font-semibold break-words lg:text-2xl lg:leading-9 2xl:text-3xl"
                >
                    Selamat datang<template v-if="welcomeName"
                        >,
                        <span class="text-secondary dark:text-primary">{{
                            welcomeName
                        }}</span></template
                    >!
                </h1>
            </header>
            <Button
                type="button"
                variant="outline"
                size="icon"
                class="min-h-11 min-w-11 shrink-0"
                :disabled="loading"
                aria-label="Perbarui Beranda"
                @click="submit()"
                ><RefreshCw
                    class="size-4"
                    :class="loading ? 'motion-safe:animate-spin' : ''"
                    aria-hidden="true"
            /></Button>
        </div>
        <p
            v-if="loading || error || feedback"
            :role="error ? 'alert' : 'status'"
            class="text-xs leading-5"
            :class="error ? 'text-error' : 'text-muted-foreground'"
        >
            {{ loading ? 'Memuat ringkasan kebun…' : error || feedback }}
        </p>
        <OwnerDashboardMetrics :table="landTable" />
        <OwnerDashboardLandTable :table="landTable" :loading="loading" />
        <div
            class="owner-dashboard-geography grid min-w-0 gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] lg:gap-6"
        >
            <OwnerDashboardCommodityChart
                :commodities="dashboard.commodities"
            />
            <section
                class="flex min-w-0 flex-col rounded-xl border border-border bg-card p-4 lg:p-5"
                aria-labelledby="owner-map-heading"
            >
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2
                        id="owner-map-heading"
                        class="text-base font-semibold 2xl:text-lg"
                    >
                        Peta lahan
                    </h2>
                    <Link
                        href="/kebun"
                        class="inline-flex min-h-11 items-center rounded-md text-sm font-medium text-secondary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
                        >Lihat kebun</Link
                    >
                </div>
                <DashboardFarmMap
                    :farms="dashboard.landMap.farms"
                    selectable
                    fit-on-resize
                    class="mt-0! h-64! grow lg:h-80! 2xl:h-96!"
                    @select="selectedMapFarmId = $event"
                />
                <p class="mt-3 text-xs leading-5 text-muted-foreground">
                    {{
                        dashboard.landMap.hasCoordinates
                            ? 'Marker menunjukkan lokasi lahan, bukan batas bidang atau subblok.'
                            : 'Koordinat lokasi lahan belum tercatat.'
                    }}
                </p>
            </section>
        </div>
        <p class="text-xs leading-5 text-muted-foreground">
            Diperbarui {{ updatedAt }}
        </p>
        <OwnerDashboardFarmPreview
            v-if="selectedMapFarm"
            v-model:open="mapPreviewOpen"
            :farm="selectedMapFarm"
        />
    </div>
</template>
