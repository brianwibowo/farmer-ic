<script setup lang="ts">
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { RefreshCw } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DashboardActivities from '@/components/dashboard/DashboardActivities.vue';
import DashboardActivityChart from '@/components/dashboard/DashboardActivityChart.vue';
import DashboardRisks from '@/components/dashboard/DashboardRisks.vue';
import DashboardFarmMap from '@/components/dashboard/DashboardFarmMap.vue';
import DashboardFarmPreview from '@/components/dashboard/DashboardFarmPreview.vue';
import DashboardWeather from '@/components/dashboard/DashboardWeather.vue';
import DashboardMetricCards from '@/components/dashboard/DashboardMetricCards.vue';
import DashboardPlantingOverview from '@/components/dashboard/DashboardPlantingOverview.vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { Button } from '@/components/ui/button';
import type { DashboardInvitation } from '@/types';
import type { AdminDashboardData } from '@/types/dashboard';

defineOptions({ inheritAttrs: false });
const page = usePage();
const welcomeName = computed(() => page.props.auth.user?.name?.trim() ?? '');
const props = defineProps<{
    pendingInvitations?: DashboardInvitation[];
    dashboard: AdminDashboardData;
}>();
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
const farmId = ref(props.dashboard.filters.farm_id ?? '');
const period = ref(props.dashboard.filters.period);
const refreshing = ref(false);
const feedback = ref('');
const failed = ref(false);
watch(
    () => props.dashboard.filters,
    (filters) => {
        farmId.value = filters.farm_id ?? '';
        period.value = filters.period;
    },
);
function reportFailure(): false {
    failed.value = true;
    feedback.value = 'Dashboard belum dapat diperbarui. Silakan coba lagi.';
    return false;
}
function loadDashboard(): void {
    if (refreshing.value) return;
    refreshing.value = true;
    feedback.value = '';
    failed.value = false;
    router.get(
        '/admin/dashboard',
        {
            ...(farmId.value ? { farm_id: farmId.value } : {}),
            period: period.value,
        },
        {
            only: ['dashboard'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: () => {
                feedback.value = 'Dashboard berhasil diperbarui.';
            },
            onError: reportFailure,
            onHttpException: reportFailure,
            onNetworkError: reportFailure,
            onFinish: () => {
                refreshing.value = false;
                if (failed.value) {
                    farmId.value = props.dashboard.filters.farm_id ?? '';
                    period.value = props.dashboard.filters.period;
                }
            },
        },
    );
}
</script>

<template>
    <Head title="Dashboard" />
    <PendingInvitationsModal
        v-if="pendingInvitations?.length"
        :invitations="pendingInvitations"
        admin-theme
    />
    <div
        class="admin-page-content admin-dashboard-overview space-y-[18px]"
        :aria-busy="refreshing"
    >
        <header class="flex flex-wrap items-center justify-between gap-4">
            <p
                class="min-w-0 flex-1 basis-64 text-xl leading-8 font-medium tracking-tight break-words text-foreground sm:text-2xl"
            >
                Selamat datang<template v-if="welcomeName"
                    >,
                    <strong class="font-bold text-primary">{{
                        welcomeName
                    }}</strong></template
                >!
            </p>
            <div
                class="grid w-full grid-cols-[minmax(0,1fr)_auto] items-end gap-2 sm:flex sm:w-auto sm:flex-wrap"
            >
                <label class="col-span-2 min-w-0 flex-1 sm:flex-none"
                    ><span class="sr-only">Kebun</span
                    ><AppSelect
                        v-model="farmId"
                        class="admin-field dashboard-filter w-full sm:max-w-56"
                        :disabled="refreshing"
                        @change="loadDashboard"
                    >
                        <AppSelectOption value="">Semua kebun</AppSelectOption>
                        <AppSelectOption
                            v-for="farm in dashboard.farmOptions"
                            :key="farm.id"
                            :value="farm.id"
                        >
                            {{ farm.name }}
                        </AppSelectOption>
                    </AppSelect></label
                >
                <label class="min-w-0 flex-1 sm:flex-none"
                    ><span class="sr-only">Periode aktivitas</span
                    ><AppSelect
                        v-model="period"
                        class="admin-field dashboard-filter w-full"
                        :disabled="refreshing"
                        @change="loadDashboard"
                    >
                        <AppSelectOption value="7"
                            >7 hari terakhir</AppSelectOption
                        >
                        <AppSelectOption value="30"
                            >30 hari terakhir</AppSelectOption
                        >
                    </AppSelect></label
                >
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    class="dashboard-refresh shrink-0 bg-card"
                    :disabled="refreshing"
                    aria-label="Perbarui dashboard"
                    @click="loadDashboard"
                    ><RefreshCw
                        :class="{
                            'animate-spin motion-reduce:animate-none':
                                refreshing,
                        }"
                        aria-hidden="true"
                /></Button>
            </div>
        </header>
        <p
            v-if="refreshing || feedback"
            role="status"
            class="text-xs"
            :class="failed ? 'text-error' : 'text-muted-foreground'"
        >
            {{ refreshing ? 'Memuat ringkasan kebun…' : feedback }}
        </p>
        <DashboardMetricCards :dashboard="dashboard" />
        <section
            class="grid items-stretch gap-[18px] lg:grid-cols-[minmax(0,2fr)_minmax(260px,1fr)]"
        >
            <DashboardActivityChart :trend="dashboard.trend" />
            <DashboardRisks :risks="dashboard.risks" />
        </section>
        <DashboardPlantingOverview
            :cycles="dashboard.cycles"
            :commodities="dashboard.commodities"
        />
        <div
            class="grid items-stretch gap-[18px] xl:grid-cols-[minmax(0,3fr)_minmax(260px,1fr)]"
        >
            <section
                id="dashboard-map"
                class="dashboard-panel flex min-w-0 flex-col"
                aria-labelledby="map-heading"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="map-heading" class="admin-section-title">
                            Denah kebun
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Lokasi kebun dari koordinat yang tersimpan, sesuai
                            filter dan cakupan akses.
                        </p>
                    </div>
                    <Button
                        v-if="$page.props.navigation?.farms"
                        as-child
                        variant="outline"
                        size="sm"
                        ><Link href="/admin/farm-management"
                            >Kelola kebun</Link
                        ></Button
                    >
                </div>
                <DashboardFarmMap
                    :farms="dashboard.landMap.farms"
                    selectable
                    @select="selectedMapFarmId = $event"
                    class="xl:min-h-96 xl:flex-1"
                />
                <p class="mt-3 text-xs leading-5 text-muted-foreground">
                    {{
                        dashboard.landMap.hasCoordinates
                            ? 'Marker menunjukkan lokasi kebun, bukan batas bidang atau subblok.'
                            : 'Lengkapi latitude dan longitude pada data kebun untuk menampilkan lokasi.'
                    }}
                </p>
            </section>
            <DashboardWeather :farms="dashboard.landMap.farms" />
        </div>
        <p class="text-xs text-muted-foreground">
            Diperbarui {{ dashboard.generatedAt }}
        </p>
        <DashboardActivities
            :activities="dashboard.activities"
            :officers="dashboard.officers"
        />
        <DashboardFarmPreview
            v-if="selectedMapFarm"
            v-model:open="mapPreviewOpen"
            :farm="selectedMapFarm"
            admin-theme
        />
    </div>
</template>
