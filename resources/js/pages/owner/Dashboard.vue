<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { RefreshCw } from '@lucide/vue';
import { computed, watch } from 'vue';
import DashboardFarmMap from '@/components/dashboard/DashboardFarmMap.vue';
import OwnerDashboardActivities from '@/components/owner/dashboard/OwnerDashboardActivities.vue';
import OwnerDashboardActivityChart from '@/components/owner/dashboard/OwnerDashboardActivityChart.vue';
import OwnerDashboardLandTable from '@/components/owner/dashboard/OwnerDashboardLandTable.vue';
import OwnerDashboardMetrics from '@/components/owner/dashboard/OwnerDashboardMetrics.vue';
import OwnerDashboardPlantingOverview from '@/components/owner/dashboard/OwnerDashboardPlantingOverview.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Button } from '@/components/ui/button';
import { useOwnerReadFilters } from '@/composables/useOwnerReadFilters';
import type { DashboardData } from '@/types/dashboard';
import type { OwnerDashboardLandTable as OwnerLandTableData } from '@/types/owner-dashboard';

const props = defineProps<{
    dashboard: DashboardData;
    landTable: OwnerLandTableData;
}>();
const page = usePage();
const welcomeName = computed(() => page.props.auth.user?.name?.trim() ?? '');
const { filters, loading, error, feedback, submit, sync } = useOwnerReadFilters(
    '/',
    {
        farm_id: props.dashboard.filters.farm_id ?? '',
        period: props.dashboard.filters.period,
    },
);
watch(
    () => props.dashboard.filters,
    (value) => sync({ farm_id: value.farm_id ?? '', period: value.period }),
);
const updatedAt = computed(() => props.dashboard.generatedAt);
</script>

<template>
    <Head title="Beranda Owner" />
    <div
        class="owner-dashboard min-w-0 space-y-4 lg:space-y-6"
        :aria-busy="loading"
    >
        <div class="owner-dashboard-header grid gap-4">
            <header>
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
            <form
                class="owner-dashboard-filters grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-end gap-2"
                @submit.prevent="submit()"
            >
                <label class="col-span-2 block min-w-0 text-xs font-medium"
                    ><span class="mb-1.5 block">Kebun</span>
                    <AppSelect
                        id="owner-overview-farm"
                        v-model="filters.farm_id"
                        :disabled="loading"
                        @change="submit()"
                        ><AppSelectOption value="">Semua kebun</AppSelectOption
                        ><AppSelectOption
                            v-for="farm in dashboard.farmOptions"
                            :key="farm.id"
                            :value="farm.id"
                            >{{ farm.name }}</AppSelectOption
                        ></AppSelect
                    >
                </label>
                <label class="block min-w-0 text-xs font-medium"
                    ><span class="mb-1.5 block">Periode aktivitas</span
                    ><AppSelect
                        id="owner-overview-period"
                        v-model="filters.period"
                        :disabled="loading"
                        @change="submit()"
                        ><AppSelectOption value="7"
                            >7 hari terakhir</AppSelectOption
                        ><AppSelectOption value="30"
                            >30 hari terakhir</AppSelectOption
                        ></AppSelect
                    ></label
                >
                <Button
                    type="submit"
                    variant="outline"
                    size="icon"
                    class="min-h-11 min-w-11"
                    :disabled="loading"
                    aria-label="Perbarui Beranda"
                    ><RefreshCw
                        class="size-4"
                        :class="loading ? 'motion-safe:animate-spin' : ''"
                        aria-hidden="true"
                /></Button>
            </form>
        </div>
        <p
            v-if="loading || error || feedback"
            :role="error ? 'alert' : 'status'"
            class="text-xs leading-5"
            :class="error ? 'text-error' : 'text-muted-foreground'"
        >
            {{ loading ? 'Memuat ringkasan kebun…' : error || feedback }}
        </p>
        <OwnerDashboardMetrics :dashboard="dashboard" />
        <div
            class="owner-dashboard-overview grid min-w-0 items-start gap-4 lg:gap-6"
            :class="{
                'owner-has-trend': page.props.ownerNavigation?.activities,
            }"
        >
            <OwnerDashboardLandTable :table="landTable" :loading="loading" />
            <OwnerDashboardActivityChart
                v-if="page.props.ownerNavigation?.activities"
                :trend="dashboard.trend"
            />
        </div>
        <OwnerDashboardPlantingOverview
            :cycles="dashboard.cycles"
            :commodities="dashboard.commodities"
        />
        <section
            class="min-w-0 rounded-xl border border-border bg-card p-4"
            aria-labelledby="owner-map-heading"
        >
            <div class="flex items-center justify-between gap-3">
                <h2 id="owner-map-heading" class="text-base font-semibold">
                    Denah kebun
                </h2>
                <Link
                    href="/kebun"
                    class="inline-flex min-h-11 items-center rounded-md text-sm font-medium text-secondary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
                    >Lihat kebun</Link
                >
            </div>
            <DashboardFarmMap
                :farms="dashboard.landMap.farms"
                class="h-56! lg:h-80! 2xl:h-96!"
            />
            <p class="mt-3 text-xs leading-5 text-muted-foreground">
                {{
                    dashboard.landMap.hasCoordinates
                        ? 'Marker menunjukkan lokasi kebun, bukan batas bidang atau subblok.'
                        : 'Koordinat lokasi kebun belum tercatat.'
                }}
            </p>
        </section>
        <p class="text-xs leading-5 text-muted-foreground">
            Diperbarui {{ updatedAt }}
        </p>
        <OwnerDashboardActivities
            v-if="page.props.ownerNavigation?.activities"
            :activities="dashboard.activities"
            :officers="dashboard.officers"
        />
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    .owner-dashboard-header {
        grid-template-columns: minmax(0, 1fr) minmax(420px, 540px);
        align-items: end;
        gap: 24px;
    }
    .owner-dashboard-filters {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 44px;
    }
    .owner-dashboard-filters > label:first-child {
        grid-column: auto;
    }
}
@media (min-width: 1280px) {
    .owner-dashboard-overview.owner-has-trend {
        grid-template-columns: minmax(0, 2.3fr) minmax(300px, 1fr);
    }
}
</style>
