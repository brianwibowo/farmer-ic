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
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DashboardActivities from '@/components/dashboard/DashboardActivities.vue';
import DashboardFarmMap from '@/components/dashboard/DashboardFarmMap.vue';
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
};
type DashboardData = {
    generatedAt: string;
    metrics: Metric[];
    activity: { total: number; days: Array<{ label: string; value: number }> };
    monitoring: {
        activeArea: string;
        activeCycles: number;
        openRisks: number;
        farmsWithActivity: number;
    };
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
const userName = computed(() => page.props.auth.user?.name || 'Admin');
const maxActivity = computed(() =>
    Math.max(...props.dashboard.activity.days.map((item) => item.value), 1),
);
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
            <article
                v-for="metric in dashboard.metrics"
                :key="metric.key"
                class="min-w-0 rounded-xl border border-border bg-card p-5"
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
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article
                class="min-w-0 rounded-xl border border-border bg-card p-5"
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
                    <span
                        class="rounded-lg bg-muted px-3 py-1.5 text-xs font-semibold text-muted-foreground"
                        >7 hari terakhir</span
                    >
                </div>
                <div class="mt-6 flex items-baseline gap-2">
                    <strong class="text-3xl font-semibold text-foreground">{{
                        dashboard.activity.total
                    }}</strong
                    ><span class="text-sm text-muted-foreground"
                        >aktivitas</span
                    >
                </div>
                <p
                    v-if="dashboard.activity.total === 0"
                    class="mt-4 text-sm text-muted-foreground"
                    role="status"
                >
                    Belum ada aktivitas dalam tujuh hari terakhir.
                </p>
                <dl class="sr-only">
                    <template
                        v-for="day in dashboard.activity.days"
                        :key="day.label"
                        ><dt>{{ day.label }}</dt>
                        <dd>{{ day.value }} aktivitas</dd></template
                    >
                </dl>
                <div
                    aria-hidden="true"
                    class="mt-6 grid h-44 grid-cols-7 items-end gap-2 border-b border-border pb-1"
                >
                    <div
                        v-for="day in dashboard.activity.days"
                        :key="day.label"
                        class="flex h-full min-w-0 flex-col justify-end gap-2 text-center"
                    >
                        <span
                            class="text-xs font-medium text-muted-foreground"
                            >{{ day.value }}</span
                        >
                        <div class="flex flex-1 items-end justify-center">
                            <span
                                class="w-full max-w-8 rounded-t-md bg-primary"
                                :style="{
                                    height: `${Math.max((day.value / maxActivity) * 100, day.value ? 8 : 2)}%`,
                                }"
                            />
                        </div>
                        <span class="text-xs text-muted-foreground">{{
                            day.label
                        }}</span>
                    </div>
                </div>
            </article>
            <article
                class="min-w-0 rounded-xl border border-border bg-card p-5"
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
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article
                class="min-w-0 rounded-xl border border-border bg-card p-5"
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
                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl bg-muted/60 p-4">
                        <dt class="text-xs font-medium text-muted-foreground">
                            OPEX berjalan · {{ dashboard.financial.monthLabel }}
                        </dt>
                        <dd
                            class="mt-2 text-2xl font-semibold tracking-tight break-words text-foreground"
                        >
                            {{ dashboard.financial.opexThisMonth }}
                        </dd>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Transaksi pengeluaran pada bulan berjalan.
                        </p>
                    </div>
                    <div class="rounded-xl bg-muted/60 p-4">
                        <dt class="text-xs font-medium text-muted-foreground">
                            Proyeksi bersih aktif
                        </dt>
                        <dd
                            class="mt-2 text-2xl font-semibold tracking-tight break-words text-foreground"
                        >
                            {{ dashboard.financial.projectedNet }}
                        </dd>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Dari
                            {{ dashboard.financial.projectionCount }} periode
                            proyeksi yang sedang berlaku.
                        </p>
                    </div>
                </dl>
                <div
                    class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs text-muted-foreground"
                >
                    <span
                        >Proyeksi pemasukan:
                        <strong class="font-semibold text-foreground">{{
                            dashboard.financial.projectedIncome
                        }}</strong></span
                    >
                    <span
                        >Proyeksi biaya:
                        <strong class="font-semibold text-foreground">{{
                            dashboard.financial.projectedExpense
                        }}</strong></span
                    >
                </div>
                <Button as-child variant="link" size="sm" class="mt-4 px-0">
                    <Link href="/finance">Buka keuangan</Link>
                </Button>
            </article>
            <article
                class="min-w-0 rounded-xl border border-border bg-card p-5"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="admin-section-title text-foreground">
                            Status monitoring
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Cakupan data yang dapat Anda pantau.
                        </p>
                    </div>
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-success/15 text-success"
                        ><Sprout :size="20" aria-hidden="true"
                    /></span>
                </div>
                <dl class="mt-6 divide-y divide-border">
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-sm text-muted-foreground">
                            Area siklus aktif
                        </dt>
                        <dd class="font-semibold text-foreground">
                            {{ dashboard.monitoring.activeArea }} ha
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-sm text-muted-foreground">
                            Siklus tanam aktif
                        </dt>
                        <dd class="font-semibold text-foreground">
                            {{ dashboard.monitoring.activeCycles }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-sm text-muted-foreground">
                            Kebun aktif minggu ini
                        </dt>
                        <dd class="font-semibold text-foreground">
                            {{ dashboard.monitoring.farmsWithActivity }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-sm text-muted-foreground">
                            Risiko terbuka
                        </dt>
                        <dd
                            class="font-semibold"
                            :class="
                                dashboard.monitoring.openRisks > 0
                                    ? 'text-warning'
                                    : 'text-success'
                            "
                        >
                            {{ dashboard.monitoring.openRisks }}
                        </dd>
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
