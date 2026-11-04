<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    ClipboardList,
    MapPin,
    Sprout,
    Users,
    WalletCards,
} from '@lucide/vue';
import { computed, watch } from 'vue';
import DashboardFarmMap from '@/components/dashboard/DashboardFarmMap.vue';
import OwnerPageHeader from '@/components/owner/OwnerPageHeader.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Button } from '@/components/ui/button';
import { useOwnerReadFilters } from '@/composables/useOwnerReadFilters';
import type { DashboardData } from '@/types/dashboard';

const props = defineProps<{ dashboard: DashboardData }>();
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
const chartMax = computed(() =>
    Math.max(1, ...props.dashboard.trend.days.map((day) => day.value)),
);
const finance = computed(() => [
    {
        label: 'Pengeluaran tercatat',
        value: props.dashboard.financial.opexThisMonth,
        note: props.dashboard.financial.monthLabel,
    },
    {
        label: 'Proyeksi pemasukan',
        value: props.dashboard.financial.projectedIncome,
        note: 'Periode proyeksi aktif',
    },
    {
        label: 'Proyeksi biaya',
        value: props.dashboard.financial.projectedExpense,
        note: 'Periode proyeksi aktif',
    },
    {
        label: 'Proyeksi bersih',
        value: props.dashboard.financial.projectedNet,
        note: 'Pemasukan dikurangi biaya proyeksi',
    },
]);
const page = usePage();
const shortcuts = computed(() =>
    [
        {
            label: 'Aktivitas',
            href: '/aktivitas',
            icon: ClipboardList,
            allowed: page.props.ownerNavigation?.activities,
        },
        {
            label: 'SOP',
            href: '/lainnya/sop',
            icon: Sprout,
            allowed: page.props.ownerNavigation?.sops,
        },
        {
            label: 'Pengguna',
            href: '/lainnya/pengguna',
            icon: Users,
            allowed: page.props.ownerNavigation?.users,
        },
    ].filter((item) => item.allowed),
);
</script>

<template>
    <Head title="Beranda Owner" />
    <OwnerPageHeader
        title="Ringkasan kebun"
        description="Pantau lahan, kegiatan, dan keuangan tenant aktif."
    />
    <form
        class="space-y-3 rounded-xl border border-border bg-card p-4"
        :aria-busy="loading"
        @submit.prevent="submit()"
    >
        <div>
            <label for="overview-farm" class="mb-1 block text-xs font-medium"
                >Kebun</label
            ><AppSelect
                id="overview-farm"
                v-model="filters.farm_id"
                :disabled="loading"
                ><AppSelectOption value="">Semua kebun</AppSelectOption
                ><AppSelectOption
                    v-for="farm in dashboard.farmOptions"
                    :key="farm.id"
                    :value="farm.id"
                    >{{ farm.name }}</AppSelectOption
                ></AppSelect
            >
        </div>
        <div class="flex items-end gap-2">
            <div class="min-w-0 flex-1">
                <label
                    for="overview-period"
                    class="mb-1 block text-xs font-medium"
                    >Periode tren aktivitas</label
                ><AppSelect
                    id="overview-period"
                    v-model="filters.period"
                    :disabled="loading"
                    ><AppSelectOption value="7">7 hari terakhir</AppSelectOption
                    ><AppSelectOption value="30"
                        >30 hari terakhir</AppSelectOption
                    ></AppSelect
                >
            </div>
            <Button class="min-h-11" :disabled="loading" type="submit">{{
                loading ? 'Memuat…' : 'Terapkan'
            }}</Button>
        </div>
        <p
            v-if="error || feedback"
            :role="error ? 'alert' : 'status'"
            class="text-xs"
            :class="error ? 'text-error' : 'text-muted-foreground'"
        >
            {{ error || feedback }}
        </p>
    </form>
    <p
        v-if="!dashboard.farmOptions.length"
        role="status"
        class="rounded-xl bg-muted p-4 text-sm leading-6 text-muted-foreground"
    >
        Belum ada kebun pada tenant ini. Data akan muncul setelah dicatat oleh
        Admin.
    </p>
    <div class="grid grid-cols-2 gap-3">
        <article
            v-for="metric in dashboard.metrics"
            :key="metric.key"
            class="min-w-0 rounded-xl border border-border bg-card p-4"
        >
            <p class="text-xs leading-5 text-muted-foreground">
                {{ metric.label }}
            </p>
            <p
                class="mt-2 text-2xl leading-8 font-semibold break-words tabular-nums"
            >
                {{ metric.value }}
                <span class="text-xs font-normal text-muted-foreground">{{
                    metric.unit
                }}</span>
            </p>
            <p class="mt-2 text-xs leading-5 text-muted-foreground">
                {{ metric.note }}
            </p>
        </article>
    </div>
    <nav aria-label="Pintasan pemantauan" class="grid grid-cols-3 gap-2">
        <Link
            v-for="item in shortcuts"
            :key="item.href"
            :href="item.href"
            class="flex min-h-20 flex-col items-center justify-center gap-2 rounded-xl bg-muted p-2 text-xs font-medium hover:bg-primary/10 focus-visible:outline-2 focus-visible:outline-ring"
            ><component
                :is="item.icon"
                class="size-5 text-secondary dark:text-primary"
                aria-hidden="true"
            />{{ item.label }}</Link
        >
    </nav>
    <section
        class="rounded-xl border border-border bg-card p-4"
        aria-labelledby="owner-attention"
    >
        <h2 id="owner-attention" class="text-base font-semibold">
            Perlu diperhatikan
        </h2>
        <dl class="mt-3 divide-y divide-border">
            <div
                v-for="item in dashboard.attention"
                :key="item.key"
                class="flex items-start justify-between gap-3 py-3"
            >
                <div>
                    <dt class="text-sm font-medium">{{ item.label }}</dt>
                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                        {{ item.description }}
                    </p>
                </div>
                <dd
                    class="shrink-0 rounded-lg bg-muted px-2.5 py-1 text-sm font-semibold tabular-nums"
                >
                    {{ item.count }}
                </dd>
            </div>
        </dl>
    </section>
    <section
        class="rounded-xl border border-border bg-card p-4"
        aria-labelledby="owner-cycles"
    >
        <h2 id="owner-cycles" class="text-base font-semibold">
            Perjalanan tanam
        </h2>
        <p class="mt-1 text-xs leading-5 text-muted-foreground">
            {{ dashboard.cycles.planned }} rencana ·
            {{ dashboard.cycles.active }} aktif ·
            {{ dashboard.cycles.harvested }} sudah dipanen
        </p>
        <dl class="mt-4 space-y-3">
            <div
                v-for="stage in dashboard.cycles.stages"
                :key="stage.code"
                class="flex justify-between gap-3 text-sm"
            >
                <dt>{{ stage.label }}</dt>
                <dd class="font-medium">{{ stage.count }} batch</dd>
            </div>
        </dl>
        <p
            v-if="dashboard.cycles.withoutTimeline"
            class="mt-4 rounded-xl bg-muted p-3 text-xs leading-5 text-muted-foreground"
        >
            {{ dashboard.cycles.withoutTimeline }} siklus berjalan belum
            memiliki timeline tercatat.
        </p>
        <div
            v-if="dashboard.commodities.length"
            class="mt-4 space-y-2 border-t border-border pt-4"
        >
            <p class="text-xs text-muted-foreground">
                Komoditas pada siklus aktif atau sudah dipanen
            </p>
            <p
                v-for="commodity in dashboard.commodities"
                :key="commodity.id"
                class="flex justify-between gap-3 text-sm"
            >
                <span>{{ commodity.name }}</span
                ><span>{{ commodity.plantedArea }} ha</span>
            </p>
        </div>
    </section>
    <section
        v-if="page.props.ownerNavigation?.activities"
        class="rounded-xl border border-border bg-card p-4"
        aria-labelledby="owner-trend"
    >
        <div class="flex justify-between gap-3">
            <h2 id="owner-trend" class="text-base font-semibold">
                Tren aktivitas
            </h2>
            <strong>{{ dashboard.trend.total }}</strong>
        </div>
        <p class="mt-1 text-xs text-muted-foreground">
            {{ dashboard.trend.periodLabel }}
        </p>
        <p
            v-if="!dashboard.trend.total"
            class="mt-4 text-sm text-muted-foreground"
        >
            Belum ada aktivitas pada periode ini.
        </p>
        <ol
            v-else
            class="mt-5 flex items-end gap-2"
            aria-label="Jumlah aktivitas per periode"
        >
            <li
                v-for="day in dashboard.trend.days"
                :key="day.date"
                class="min-w-0 flex-1 text-center"
            >
                <span class="text-xs font-medium">{{ day.value }}</span>
                <div
                    class="mt-1 flex h-24 items-end rounded-t-lg bg-muted"
                    aria-hidden="true"
                >
                    <span
                        class="w-full rounded-t-lg bg-primary"
                        :style="{ height: `${(day.value / chartMax) * 100}%` }"
                    />
                </div>
                <span class="mt-2 block text-[10px] text-muted-foreground">{{
                    day.label
                }}</span>
            </li>
        </ol>
        <Link
            href="/aktivitas"
            class="mt-4 inline-flex min-h-11 items-center gap-1 text-sm font-medium text-secondary dark:text-primary"
            >Lihat catatan aktivitas<ArrowUpRight class="size-4"
        /></Link>
    </section>
    <section
        v-if="page.props.ownerNavigation?.finance"
        class="rounded-xl border border-border bg-card p-4"
        aria-labelledby="owner-finance"
    >
        <h2
            id="owner-finance"
            class="flex items-center gap-2 text-base font-semibold"
        >
            <WalletCards class="size-5 text-secondary dark:text-primary" />Biaya
            & proyeksi
        </h2>
        <dl class="mt-4 space-y-4">
            <div v-for="item in finance" :key="item.label">
                <dt class="text-xs text-muted-foreground">{{ item.label }}</dt>
                <dd class="mt-1 text-lg font-semibold break-words tabular-nums">
                    {{ item.value }}
                </dd>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ item.note }}
                </p>
            </div>
        </dl>
        <p class="mt-4 text-xs leading-5 text-muted-foreground">
            {{ dashboard.financial.projectionCount }} periode proyeksi aktif.
            Proyeksi bersih merupakan estimasi, terpisah dari transaksi kas.
        </p>
        <Link
            :href="
                dashboard.filters.farm_id
                    ? `/finance?farm_id=${dashboard.filters.farm_id}`
                    : '/finance'
            "
            class="mt-3 inline-flex min-h-11 items-center gap-1 text-sm font-medium text-secondary dark:text-primary"
            >Buka keuangan<ArrowUpRight class="size-4"
        /></Link>
    </section>
    <section aria-labelledby="owner-map">
        <h2
            id="owner-map"
            class="flex items-center gap-2 text-base font-semibold"
        >
            <MapPin class="size-5 text-secondary dark:text-primary" />Lokasi
            kebun
        </h2>
        <DashboardFarmMap
            :farms="dashboard.landMap.farms"
            class="mt-3! h-56!"
        />
    </section>
    <section
        v-if="page.props.ownerNavigation?.activities"
        aria-labelledby="owner-recent"
    >
        <div class="flex items-center justify-between gap-3">
            <h2 id="owner-recent" class="text-base font-semibold">
                Aktivitas terbaru
            </h2>
            <Link
                href="/aktivitas"
                class="min-h-11 content-center text-xs font-medium text-secondary dark:text-primary"
                >Lihat semua</Link
            >
        </div>
        <div class="space-y-3">
            <p
                v-if="!dashboard.activities.length"
                class="rounded-xl bg-muted p-4 text-sm text-muted-foreground"
            >
                Belum ada aktivitas tercatat.
            </p>
            <Link
                v-for="activity in dashboard.activities.slice(0, 5)"
                :key="activity.id"
                :href="`/aktivitas/${activity.id}`"
                class="block space-y-1 rounded-xl border border-border p-4 hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
                ><h3 class="text-sm font-semibold">{{ activity.title }}</h3>
                <p class="text-xs text-muted-foreground">
                    {{ activity.farm }} · {{ activity.date }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ activity.officerName }}
                </p>
                <p v-if="activity.description" class="text-sm leading-6">
                    {{ activity.description }}
                </p></Link
            >
        </div>
    </section>
</template>
