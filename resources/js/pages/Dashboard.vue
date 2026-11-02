<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarDays,
    LandPlot,
    MapPinned,
    RefreshCw,
    Sprout,
    WalletCards,
    Wheat,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DashboardActivities from '@/components/dashboard/DashboardActivities.vue';
import DashboardFarmMap from '@/components/dashboard/DashboardFarmMap.vue';
import MetricInfoTooltip from '@/components/MetricInfoTooltip.vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { Button } from '@/components/ui/button';
import type { DashboardInvitation } from '@/types';
import type { DashboardActivity, DashboardOfficer } from '@/types/dashboard';

defineOptions({ inheritAttrs: false });

type Metric = {
    key: 'farms' | 'area' | 'cycles' | 'risks';
    label: string;
    value: number | string;
    unit: string;
    note: string;
    breakdown?: { blocks: number; subBlocks: number | null };
};
type ActivityDay = { label: string; value: number };
type Commodity = {
    id: string;
    name: string;
    plantedArea: string;
    cycleCount: number;
};
type DashboardData = {
    generatedAt: string;
    metrics: Metric[];
    activity: {
        total: number;
        days: ActivityDay[];
        farms: Array<{ id: string; name: string; days: ActivityDay[] }>;
    };
    commodities: Commodity[];
    financial: {
        opexThisMonth: string;
        monthLabel: string;
        projectionCount: number;
        projectedIncome: string;
        projectedExpense: string;
        projectedNet: string;
    };
    landMap: {
        hasCoordinates: boolean;
        farms: Array<{
            id: string;
            name: string;
            location: string;
            area: string;
            latitude: number | null;
            longitude: number | null;
        }>;
    };
    activities: DashboardActivity[];
    officers: DashboardOfficer[];
    tasks: unknown[];
};

const props = defineProps<{
    pendingInvitations?: DashboardInvitation[];
    dashboard: DashboardData;
}>();
const page = usePage();
const refreshing = ref(false);
const refreshFeedback = ref('');
const refreshFailed = ref(false);
const activityFarmId = ref<string>('');
const userName = computed(() => page.props.auth.user?.name || 'Admin');
const selectedActivityFarm = computed(() =>
    props.dashboard.activity.farms.find(
        (farm) => farm.id === activityFarmId.value,
    ),
);
const selectedActivityDays = computed(
    () => selectedActivityFarm.value?.days ?? props.dashboard.activity.days,
);
const selectedActivityTotal = computed(() =>
    selectedActivityDays.value.reduce((total, day) => total + day.value, 0),
);
const maxActivity = computed(() =>
    Math.max(...selectedActivityDays.value.map((item) => item.value), 1),
);
const activityAxisTicks = computed(() => [
    { value: maxActivity.value, y: 18 },
    { value: Math.ceil(maxActivity.value / 2), y: 51 },
    { value: 0, y: 84 },
]);
const activityChartPoints = computed(() =>
    selectedActivityDays.value
        .map((day, index) => {
            const x = 8 + index * 14;
            const y = 84 - (day.value / maxActivity.value) * 66;

            return `${x},${y}`;
        })
        .join(' '),
);
const commoditySlots = computed<Array<Commodity | null>>(() => [
    ...props.dashboard.commodities,
    ...Array.from(
        { length: Math.max(0, 3 - props.dashboard.commodities.length) },
        () => null,
    ),
]);
const metricHref: Partial<Record<Metric['key'], string>> = {
    farms: '/admin/farm-management',
    area: '/admin/farm-management',
};
function reportRefreshFailure(): false {
    refreshFailed.value = true;
    refreshFeedback.value =
        'Ringkasan belum dapat diperbarui. Silakan coba lagi.';
    return false;
}
function refreshDashboard(): void {
    if (refreshing.value) return;
    refreshing.value = true;
    refreshFeedback.value = '';
    refreshFailed.value = false;
    router.reload({
        only: ['dashboard'],
        onSuccess: () => {
            refreshFeedback.value = 'Ringkasan berhasil diperbarui.';
        },
        onError: reportRefreshFailure,
        onHttpException: reportRefreshFailure,
        onNetworkError: reportRefreshFailure,
        onFinish: () => {
            refreshing.value = false;
        },
    });
}
const metricIcon = {
    farms: LandPlot,
    area: MapPinned,
    cycles: Sprout,
    risks: AlertTriangle,
};
</script>

<template>
    <Head title="Dashboard" />
    <PendingInvitationsModal
        v-if="pendingInvitations?.length"
        :invitations="pendingInvitations"
        admin-theme
    />

    <div
        class="mx-auto w-full max-w-screen-2xl space-y-6 p-4 md:p-6 lg:p-8"
        :aria-busy="refreshing"
    >
        <header
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
        >
            <div>
                <h1 class="admin-page-title tracking-tight">
                    Selamat datang, {{ userName }}
                </h1>
                <p
                    class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground"
                >
                    Pantau kebun, aktivitas, dan pekerjaan yang membutuhkan
                    tindak lanjut.
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-3">
                <p
                    class="inline-flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <CalendarDays :size="16" aria-hidden="true" />Diperbarui
                    {{ dashboard.generatedAt }}
                </p>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="refreshing"
                    @click="refreshDashboard"
                >
                    <RefreshCw
                        :class="
                            refreshing
                                ? 'animate-spin motion-reduce:animate-none'
                                : ''
                        "
                        aria-hidden="true"
                    />{{ refreshing ? 'Memperbarui…' : 'Perbarui' }}
                </Button>
            </div>
        </header>

        <p
            v-if="refreshFeedback"
            role="status"
            class="rounded-xl border border-border bg-card px-4 py-3 text-sm"
            :class="refreshFailed ? 'text-error' : 'text-success'"
        >
            {{ refreshFeedback }}
        </p>

        <section
            aria-label="Metrik utama"
            class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4"
        >
            <component
                v-for="metric in dashboard.metrics"
                :key="metric.key"
                :is="metricHref[metric.key] ? Link : 'article'"
                v-bind="
                    metricHref[metric.key]
                        ? { href: metricHref[metric.key] }
                        : {}
                "
                class="min-w-0 rounded-xl border border-border bg-card p-5 transition-shadow transition-transform hover:-translate-y-0.5 hover:shadow-md motion-reduce:transform-none motion-reduce:transition-none"
            >
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-medium text-muted-foreground">
                        {{ metric.label }}
                    </p>
                    <span
                        class="hidden size-9 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary sm:grid"
                        ><component
                            :is="metricIcon[metric.key]"
                            :size="20"
                            aria-hidden="true"
                    /></span>
                </div>
                <p class="admin-metric-value mt-4 break-words text-foreground">
                    {{ metric.value }}
                    <span class="text-sm font-medium text-muted-foreground">{{
                        metric.unit
                    }}</span>
                </p>
                <p class="mt-2 text-xs leading-5 text-muted-foreground">
                    {{ metric.note }}
                </p>
                <dl
                    v-if="metric.breakdown"
                    class="mt-3 flex flex-wrap gap-2 text-xs"
                >
                    <div
                        class="rounded-lg bg-muted px-2 py-1 text-muted-foreground"
                    >
                        <dt class="sr-only">Blok dipetakan</dt>
                        <dd>{{ metric.breakdown.blocks }} blok</dd>
                    </div>
                    <div
                        class="rounded-lg bg-muted px-2 py-1 text-muted-foreground"
                    >
                        <dt class="sr-only">Total sub-blok</dt>
                        <dd>
                            {{ metric.breakdown.subBlocks ?? '—' }}
                            sub-blok
                        </dd>
                    </div>
                </dl>
            </component>
        </section>

        <section aria-labelledby="commodities-heading">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2
                        id="commodities-heading"
                        class="admin-section-title text-foreground"
                    >
                        Komoditas tertanam
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Tiga komoditas dengan luas tanam terbesar pada cycle
                        aktif atau telah dipanen.
                    </p>
                </div>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                <article
                    v-for="(commodity, index) in commoditySlots"
                    :key="commodity?.id ?? `empty-commodity-${index}`"
                    class="min-w-0 rounded-xl border border-border bg-card p-5 transition-shadow transition-transform hover:-translate-y-0.5 hover:shadow-md motion-reduce:transform-none motion-reduce:transition-none"
                >
                    <template v-if="commodity">
                        <div class="flex items-start justify-between gap-3">
                            <span
                                class="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary"
                                ><Wheat :size="21" aria-hidden="true"
                            /></span>
                            <span
                                class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground"
                                >{{ commodity.cycleCount }} cycle</span
                            >
                        </div>
                        <h3 class="mt-5 text-lg font-semibold text-foreground">
                            {{ commodity.name }}
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Luas yang sudah ditanami
                        </p>
                        <p
                            class="mt-1 text-2xl font-semibold tracking-tight text-foreground"
                        >
                            {{ commodity.plantedArea }}
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >ha</span
                            >
                        </p>
                    </template>
                    <template v-else>
                        <div class="flex items-start justify-between gap-3">
                            <span
                                class="grid size-10 place-items-center rounded-xl bg-muted text-muted-foreground"
                                ><Wheat :size="21" aria-hidden="true" /></span
                            ><span class="text-xs text-muted-foreground"
                                >Belum ada data</span
                            >
                        </div>
                        <h3 class="mt-5 text-lg font-semibold text-foreground">
                            Komoditas belum tercatat
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            Komoditas akan muncul setelah cycle tanam aktif atau
                            panen dicatat.
                        </p>
                    </template>
                </article>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article
                class="order-3 min-w-0 rounded-xl border border-border bg-card p-5 transition-shadow transition-transform hover:-translate-y-0.5 hover:shadow-md motion-reduce:transform-none motion-reduce:transition-none"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="admin-section-title text-foreground">
                            Aktivitas lahan
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Aktivitas tercatat dalam tujuh hari terakhir.
                        </p>
                    </div>
                    <label class="space-y-1.5">
                        <span class="block text-xs font-medium text-foreground"
                            >Kebun</span
                        >
                        <select
                            v-model="activityFarmId"
                            class="admin-field h-9 min-w-44 py-1 text-sm"
                        >
                            <option value="">Semua kebun</option>
                            <option
                                v-for="farm in dashboard.activity.farms"
                                :key="farm.id"
                                :value="farm.id"
                            >
                                {{ farm.name }}
                            </option>
                        </select>
                    </label>
                </div>
                <div class="mt-6 flex items-baseline gap-2">
                    <strong class="text-3xl font-semibold text-foreground">{{
                        selectedActivityTotal
                    }}</strong
                    ><span class="text-sm text-muted-foreground"
                        >aktivitas</span
                    >
                </div>
                <p
                    v-if="selectedActivityTotal === 0"
                    class="mt-4 text-sm text-muted-foreground"
                    role="status"
                >
                    Belum ada aktivitas dalam tujuh hari terakhir.
                </p>
                <dl class="sr-only">
                    <template
                        v-for="day in selectedActivityDays"
                        :key="day.label"
                        ><dt>{{ day.label }}</dt>
                        <dd>{{ day.value }} aktivitas</dd></template
                    >
                </dl>
                <div
                    class="mt-6 flex items-center gap-2 text-xs font-medium text-muted-foreground"
                >
                    <span
                        class="size-2 rounded-full bg-primary"
                        aria-hidden="true"
                    />
                    Jumlah aktivitas per hari
                </div>
                <div class="mt-3 overflow-x-auto">
                    <div class="flex min-w-140 gap-3">
                        <div
                            class="flex h-52 w-8 shrink-0 flex-col justify-between pb-0.5 text-right text-xs text-muted-foreground"
                            aria-hidden="true"
                        >
                            <span
                                v-for="tick in activityAxisTicks"
                                :key="tick.y"
                                >{{ tick.value }}</span
                            >
                        </div>
                        <div class="min-w-0 flex-1">
                            <svg
                                viewBox="0 0 100 92"
                                preserveAspectRatio="none"
                                class="h-52 w-full"
                                aria-hidden="true"
                            >
                                <path
                                    d="M8 18H92M8 51H92M8 84H92"
                                    class="stroke-border"
                                    stroke-width="0.5"
                                    fill="none"
                                />
                                <polyline
                                    :points="activityChartPoints"
                                    class="fill-none stroke-primary"
                                    stroke-width="1.25"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    vector-effect="non-scaling-stroke"
                                />
                            </svg>
                            <div class="grid grid-cols-7 gap-1 text-center">
                                <div
                                    v-for="day in selectedActivityDays"
                                    :key="day.label"
                                    class="min-w-0"
                                >
                                    <span
                                        class="block text-xs font-semibold text-foreground"
                                        >{{ day.value }}</span
                                    ><span
                                        class="mt-1 block text-xs text-muted-foreground"
                                        >{{ day.label }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
            <article
                class="order-1 min-w-0 rounded-xl border border-border bg-card p-5 transition-shadow transition-transform hover:-translate-y-0.5 hover:shadow-md motion-reduce:transform-none motion-reduce:transition-none xl:col-span-2"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="admin-section-title text-foreground">
                            Denah kebun
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Representasi posisi dan luas kebun dalam cakupan
                            Anda.
                        </p>
                    </div>
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary"
                        ><MapPinned :size="20" aria-hidden="true"
                    /></span>
                </div>
                <DashboardFarmMap :farms="dashboard.landMap.farms" />
                <p class="mt-3 text-xs leading-5 text-muted-foreground">
                    <template v-if="dashboard.landMap.hasCoordinates"
                        >Marker memakai titik latitude/longitude yang tersimpan
                        pada kebun.</template
                    >
                    <template v-else
                        >Koordinat belum tersedia. Tambahkan latitude dan
                        longitude untuk menampilkan lokasi kebun pada
                        peta.</template
                    >
                </p>
            </article>
            <article
                class="order-2 min-w-0 rounded-xl border border-border bg-card p-5 transition-shadow transition-transform hover:-translate-y-0.5 hover:shadow-md motion-reduce:transform-none motion-reduce:transition-none"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="admin-section-title text-foreground">
                            OPEX & proyeksi
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Ringkasan finansial dari data yang dapat Anda akses.
                        </p>
                    </div>
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary"
                        ><WalletCards :size="20" aria-hidden="true"
                    /></span>
                </div>
                <dl class="mt-6 grid gap-3 sm:grid-cols-2">
                    <div
                        class="rounded-xl border border-border bg-muted/60 p-4"
                    >
                        <dt
                            class="flex items-start justify-between gap-2 text-xs font-medium text-muted-foreground"
                        >
                            <span
                                >OPEX berjalan ·
                                {{ dashboard.financial.monthLabel }}</span
                            >
                            <MetricInfoTooltip
                                label="Sumber OPEX berjalan"
                                description="Nominal transaksi bertipe pengeluaran yang tanggal transaksinya berada pada bulan berjalan."
                                :period="dashboard.financial.monthLabel"
                            />
                        </dt>
                        <dd
                            class="mt-2 text-2xl font-semibold tracking-tight break-words text-foreground"
                        >
                            {{ dashboard.financial.opexThisMonth }}
                        </dd>
                        <p class="mt-2 text-xs leading-5 text-muted-foreground">
                            Total pengeluaran yang sudah dicatat pada bulan
                            berjalan.
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-border bg-muted/60 p-4"
                    >
                        <dt
                            class="flex items-start justify-between gap-2 text-xs font-medium text-muted-foreground"
                        >
                            <span>Proyeksi pemasukan aktif</span>
                            <MetricInfoTooltip
                                label="Sumber proyeksi pemasukan"
                                description="Total kolom proyeksi pemasukan dari periode proyeksi yang mencakup hari ini."
                                period="Periode proyeksi aktif"
                            />
                        </dt>
                        <dd
                            class="mt-2 text-2xl font-semibold tracking-tight break-words text-foreground"
                        >
                            {{ dashboard.financial.projectedIncome }}
                        </dd>
                        <p class="mt-2 text-xs leading-5 text-muted-foreground">
                            Estimasi pemasukan untuk periode proyeksi yang
                            sedang berlaku.
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-border bg-muted/60 p-4"
                    >
                        <dt
                            class="flex items-start justify-between gap-2 text-xs font-medium text-muted-foreground"
                        >
                            <span>Proyeksi biaya aktif</span>
                            <MetricInfoTooltip
                                label="Sumber proyeksi biaya"
                                description="Total kolom proyeksi biaya dari periode proyeksi yang mencakup hari ini."
                                period="Periode proyeksi aktif"
                            />
                        </dt>
                        <dd
                            class="mt-2 text-2xl font-semibold tracking-tight break-words text-foreground"
                        >
                            {{ dashboard.financial.projectedExpense }}
                        </dd>
                        <p class="mt-2 text-xs leading-5 text-muted-foreground">
                            Estimasi biaya untuk periode proyeksi yang sedang
                            berlaku.
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-border bg-muted/60 p-4"
                    >
                        <dt
                            class="flex items-start justify-between gap-2 text-xs font-medium text-muted-foreground"
                        >
                            <span>Proyeksi bersih aktif</span>
                            <MetricInfoTooltip
                                label="Perhitungan proyeksi bersih"
                                description="Total proyeksi pemasukan dikurangi total proyeksi biaya dari periode yang sedang berlaku."
                                :period="`${dashboard.financial.projectionCount} periode proyeksi aktif`"
                            />
                        </dt>
                        <dd
                            class="mt-2 text-2xl font-semibold tracking-tight break-words text-foreground"
                        >
                            {{ dashboard.financial.projectedNet }}
                        </dd>
                        <p class="mt-2 text-xs leading-5 text-muted-foreground">
                            Estimasi sisa pemasukan setelah seluruh biaya
                            proyeksi dikurangi.
                        </p>
                    </div>
                </dl>
            </article>
        </section>

        <DashboardActivities
            :activities="dashboard.activities"
            :officers="dashboard.officers"
        />
    </div>
</template>
