<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    LandPlot,
    MapPinned,
    Search,
    Sprout,
    WalletCards,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DashboardFarmMap from '@/components/dashboard/DashboardFarmMap.vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import type { DashboardInvitation } from '@/types';

type Metric = {
    key: 'farms' | 'area' | 'cycles' | 'risks';
    label: string;
    value: number | string;
    unit: string;
    note: string;
};
type DashboardActivity = {
    id: string;
    title: string;
    description: string | null;
    farm: string;
    officerId: string;
    officerName: string;
    date: string;
    status: string;
};
type DashboardOfficer = {
    id: string;
    name: string;
    avatarUrl: string | null;
    activityCount: number;
    latestActivityAt: string | null;
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
const activitySearch = ref('');
const selectedOfficerId = ref<string | null>(null);
const userName = computed(() => page.props.auth.user?.name || 'Admin');
const maxActivity = computed(() =>
    Math.max(...props.dashboard.activity.days.map((item) => item.value), 1),
);
const visibleActivities = computed(() => {
    const query = activitySearch.value.trim().toLocaleLowerCase('id-ID');
    return props.dashboard.activities.filter((activity) => {
        const matchesOfficer =
            selectedOfficerId.value === null ||
            activity.officerId === selectedOfficerId.value;
        const matchesSearch =
            `${activity.title} ${activity.description ?? ''} ${activity.farm} ${activity.officerName}`
                .toLocaleLowerCase('id-ID')
                .includes(query);
        return matchesOfficer && matchesSearch;
    });
});
const metricIcon = {
    farms: LandPlot,
    area: MapPinned,
    cycles: Sprout,
    risks: AlertTriangle,
};
function activityTone(status: string): string {
    const normalized = status.toLocaleLowerCase('id-ID');
    return normalized.includes('selesai') || normalized.includes('done')
        ? 'bg-success/15 text-success'
        : normalized.includes('proses') || normalized.includes('progress')
          ? 'bg-primary/15 text-primary'
          : 'bg-muted text-muted-foreground';
}
function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}
</script>

<template>
    <Head title="Dashboard" />
    <PendingInvitationsModal
        v-if="pendingInvitations?.length"
        :invitations="pendingInvitations"
        admin-theme
    />

    <main class="mx-auto w-full max-w-screen-2xl space-y-6 p-4 md:p-6 lg:p-8">
        <header
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-widest text-primary uppercase"
                >
                    Ringkasan operasional
                </p>
                <h1 class="admin-page-title mt-2 tracking-tight">
                    Selamat datang, {{ userName }}
                </h1>
                <p
                    class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground"
                >
                    Pantau kebun, aktivitas, dan pekerjaan yang membutuhkan
                    tindak lanjut.
                </p>
            </div>
            <div
                class="inline-flex w-fit items-center gap-2 rounded-xl border border-border bg-card px-3 py-2 text-xs font-medium text-muted-foreground"
            >
                <CalendarDays :size="16" aria-hidden="true" />Diperbarui
                {{ dashboard.generatedAt }}
            </div>
        </header>

        <section
            aria-label="Metrik utama"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <article
                v-for="metric in dashboard.metrics"
                :key="metric.key"
                class="rounded-xl border border-border bg-card p-5 shadow-sm"
            >
                <div class="flex items-start justify-between gap-4">
                    <p class="text-sm font-medium text-muted-foreground">
                        {{ metric.label }}
                    </p>
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary"
                        ><component
                            :is="metricIcon[metric.key]"
                            :size="20"
                            aria-hidden="true"
                    /></span>
                </div>
                <p
                    class="mt-5 text-3xl font-semibold tracking-tight text-foreground"
                >
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
                class="rounded-xl border border-border bg-card p-5 shadow-sm"
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
                <div
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
                                class="w-full max-w-8 rounded-t-md bg-primary transition-[height]"
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
                class="rounded-xl border border-border bg-card p-5 shadow-sm"
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
                class="rounded-xl border border-border bg-card p-5 shadow-sm"
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
                            class="mt-2 text-2xl font-semibold tracking-tight text-foreground"
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
                            class="mt-2 text-2xl font-semibold tracking-tight text-foreground"
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
                class="rounded-xl border border-border bg-card p-5 shadow-sm"
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

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
            <article
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                aria-labelledby="activities-heading"
            >
                <div
                    class="flex flex-col justify-between gap-4 p-5 lg:flex-row lg:items-end"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-widest text-primary uppercase"
                        >
                            Kegiatan operasional
                        </p>
                        <h2
                            id="activities-heading"
                            class="admin-section-title mt-2 text-foreground"
                        >
                            Aktivitas lapangan
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Aktivitas terbaru yang dicatat oleh petugas pada
                            kebun dalam cakupan Anda.
                        </p>
                    </div>
                    <label class="relative"
                        ><span class="sr-only">Cari kegiatan</span
                        ><Search
                            :size="17"
                            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true" /><input
                            v-model="activitySearch"
                            type="search"
                            class="admin-field pl-10"
                            placeholder="Cari aktivitas atau kebun"
                    /></label>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left">
                        <thead
                            class="border-y border-border bg-muted/60 text-muted-foreground"
                        >
                            <tr>
                                <th class="px-5 py-3">Kegiatan</th>
                                <th class="px-5 py-3">Kebun</th>
                                <th class="px-5 py-3">Petugas</th>
                                <th class="px-5 py-3">Tanggal</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="activityItem in visibleActivities"
                                :key="activityItem.id"
                                class="hover:bg-muted/50"
                            >
                                <td
                                    class="px-5 py-4 font-medium text-foreground"
                                >
                                    <span class="inline-flex items-center gap-2"
                                        ><CheckCircle2
                                            :size="18"
                                            class="text-primary"
                                            aria-hidden="true"
                                        />{{ activityItem.title }}</span
                                    >
                                    <p
                                        v-if="activityItem.description"
                                        class="mt-1 max-w-sm truncate text-xs font-normal text-muted-foreground"
                                    >
                                        {{ activityItem.description }}
                                    </p>
                                </td>
                                <td
                                    class="px-5 py-4 text-sm text-muted-foreground"
                                >
                                    {{ activityItem.farm }}
                                </td>
                                <td
                                    class="px-5 py-4 text-sm text-muted-foreground"
                                >
                                    {{ activityItem.officerName }}
                                </td>
                                <td
                                    class="px-5 py-4 text-sm text-muted-foreground"
                                >
                                    {{ activityItem.date }}
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="
                                            activityTone(activityItem.status)
                                        "
                                        >{{ activityItem.status }}</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div
                        v-if="visibleActivities.length === 0"
                        class="flex flex-col items-center gap-2 p-10 text-center"
                    >
                        <ClipboardList
                            :size="28"
                            class="text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p class="font-medium text-foreground">
                            Tidak ada aktivitas yang cocok.
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Pilih petugas lain atau ubah kata kunci pencarian.
                        </p>
                    </div>
                </div>
                <footer
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3 text-xs text-muted-foreground"
                >
                    <span
                        >Menampilkan {{ visibleActivities.length }} dari
                        {{ dashboard.activities.length }} aktivitas
                        terbaru.</span
                    ><Button
                        v-if="$page.props.navigation?.farms"
                        as-child
                        variant="link"
                        size="sm"
                        ><Link href="/admin/farm-management"
                            >Kelola kebun</Link
                        ></Button
                    >
                </footer>
            </article>

            <aside
                class="rounded-xl border border-border bg-card p-5 shadow-sm"
                aria-labelledby="officers-heading"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-widest text-primary uppercase"
                        >
                            Filter kegiatan
                        </p>
                        <h2
                            id="officers-heading"
                            class="admin-section-title mt-2 text-foreground"
                        >
                            Petugas
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Pilih petugas untuk melihat kegiatan yang telah
                            dicatat.
                        </p>
                    </div>
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary"
                        ><LandPlot :size="20" aria-hidden="true"
                    /></span>
                </div>
                <div class="mt-5 space-y-2">
                    <Button
                        type="button"
                        :variant="
                            selectedOfficerId === null ? 'default' : 'ghost'
                        "
                        class="h-auto w-full justify-start rounded-xl px-3 py-2.5 text-left"
                        :aria-pressed="selectedOfficerId === null"
                        @click="selectedOfficerId = null"
                        ><span
                            class="grid size-9 place-items-center rounded-full bg-background/20 text-xs font-bold"
                            >#</span
                        ><span class="min-w-0 flex-1"
                            ><span class="block font-semibold"
                                >Semua petugas</span
                            ><span class="block text-xs opacity-80"
                                >{{ dashboard.activities.length }} aktivitas
                                terbaru</span
                            ></span
                        ></Button
                    >
                    <Button
                        v-for="(officer, index) in dashboard.officers"
                        :key="officer.id"
                        type="button"
                        :variant="
                            selectedOfficerId === officer.id
                                ? 'default'
                                : 'ghost'
                        "
                        class="h-auto w-full justify-start rounded-xl px-3 py-2.5 text-left"
                        :aria-pressed="selectedOfficerId === officer.id"
                        @click="selectedOfficerId = officer.id"
                        ><span
                            class="grid size-6 shrink-0 place-items-center text-xs font-semibold text-muted-foreground"
                            >{{ index + 1 }}</span
                        ><Avatar class="size-9 shrink-0 rounded-full"
                            ><AvatarImage
                                v-if="officer.avatarUrl"
                                :src="officer.avatarUrl"
                                :alt="officer.name"
                            /><AvatarFallback
                                class="rounded-full bg-muted text-xs font-semibold text-muted-foreground"
                                >{{ initials(officer.name) }}</AvatarFallback
                            ></Avatar
                        ><span class="min-w-0 flex-1"
                            ><span class="block truncate font-semibold">{{
                                officer.name
                            }}</span
                            ><span class="block text-xs opacity-80"
                                >{{ officer.activityCount }} aktivitas<span
                                    v-if="officer.latestActivityAt"
                                >
                                    · terakhir
                                    {{ officer.latestActivityAt }}</span
                                ></span
                            ></span
                        ></Button
                    >
                    <div
                        v-if="dashboard.officers.length === 0"
                        class="rounded-xl border border-dashed border-border p-5 text-center text-sm text-muted-foreground"
                    >
                        Belum ada petugas aktif yang dapat ditampilkan untuk
                        cakupan kebun ini.
                    </div>
                </div>
            </aside>
        </section>
    </main>
</template>
