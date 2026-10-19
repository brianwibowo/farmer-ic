<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarClock,
    CheckCircle2,
    Download,
    Droplets,
    RefreshCw,
    TrendingDown,
    TrendingUp,
    Wheat,
} from '@lucide/vue';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import { clearDemoStorage } from '@/composables/useDemoStorage';
import type { FarmDemoState } from '@/data/farmDemo';
import {
    buildFarmReport,
    loadFarmDemoState,
    type FarmReportCommodity,
    type FarmReportPeriod,
} from '@/services/farmDemoService';

const state = ref<FarmDemoState>();
const isLoading = ref(true);
const errorMessage = ref('');
const statusMessage = ref('');
const filters = reactive<{
    period: FarmReportPeriod;
    month: number;
    quarter: 1 | 2 | 3 | 4;
    year: number;
    kebunId: string;
    commodity: FarmReportCommodity;
}>({
    period: 'quarter',
    month: 12,
    quarter: 2,
    year: 2026,
    kebunId: 'all',
    commodity: 'all',
});

const report = computed(() =>
    state.value ? buildFarmReport(state.value, filters) : undefined,
);
const kebunOptions = computed(() => state.value?.kebun ?? []);
const monthOptions = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
];
const yearOptions = [2026];

watch(
    () => filters.kebunId,
    (id) => {
        const selected = state.value?.kebun.find((item) => item.id === id);
        filters.commodity = selected?.seedType ?? 'all';
    },
);

const formatMoney = (value: number): string => {
    if (value === 0) return 'Rp 0';
    if (value >= 1_000_000_000) {
        return `Rp ${(value / 1_000_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} M`;
    }
    if (value >= 1_000_000) {
        return `Rp ${(value / 1_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} jt`;
    }
    return `Rp ${(value / 1_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} rb`;
};

function formatNumber(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value);
}

async function loadReport(): Promise<void> {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        state.value = await loadFarmDemoState();
    } catch {
        errorMessage.value = 'Data laporan belum dapat dimuat. Coba lagi.';
    } finally {
        isLoading.value = false;
    }
}

function downloadReport(): void {
    if (!report.value) return;

    const data = report.value;
    const lines = [
        `LAPORAN KEBUN · ${data.periodLabel}`,
        `Cakupan: ${data.scopeLabel}`,
        `Dibuat: ${data.generatedAt}`,
        '',
        'RINGKASAN',
        `Kebun: ${data.summary.totalKebun}`,
        `Sehat: ${data.summary.healthy} · Perlu perhatian: ${data.summary.attention} · Kritis: ${data.summary.critical}`,
        `Pendapatan bersih: ${formatMoney(data.finance.net)}`,
        `Total panen: ${formatNumber(data.harvest.totalWeight)} Ton`,
        `Panen terdekat: ${data.harvest.nextKebun} · ${data.harvest.nextDate}`,
        `Mulai tanam: ${data.growthTimeline.plantedAt}`,
        `Update fase terakhir: ${data.growthTimeline.lastUpdatedAt}`,
        `Proyeksi panen: ${data.growthTimeline.harvestAt}`,
        '',
        'ANALISA AI',
        `Keuangan: ${data.ai.finance}`,
        `Kebun: ${data.ai.farm}`,
    ];
    const blob = new Blob([lines.join('\n')], {
        type: 'text/plain;charset=utf-8',
    });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `laporan-kebun-${new Date().toISOString().slice(0, 10)}.txt`;
    anchor.click();
    URL.revokeObjectURL(url);
}

async function resetDemoData(): Promise<void> {
    clearDemoStorage();
    filters.period = 'quarter';
    filters.month = 12;
    filters.quarter = 2;
    filters.year = 2026;
    filters.kebunId = 'all';
    filters.commodity = 'all';
    statusMessage.value = 'Data demo laporan telah direset.';
    await loadReport();
}

onMounted(() => void loadReport());
</script>

<template>
    <Head title="Laporan" />

    <FarmerMobileShell active-key="report">
        <div class="space-y-5 px-5 pt-7 pb-6">
            <header class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-secondary">
                        Ringkasan operasional
                    </p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                        Laporan Kebun
                    </h1>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-success underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    @click="void resetDemoData()"
                >
                    <RefreshCw :size="14" aria-hidden="true" />
                    Reset
                </button>
            </header>

            <section
                class="sticky top-0 z-10 -mx-1 space-y-2 rounded-2xl bg-card/95 p-1 pb-2 backdrop-blur"
                aria-label="Filter laporan"
            >
                <div class="grid grid-cols-3 gap-2" role="tablist">
                    <button
                        v-for="period in [
                            { value: 'month', label: 'Bulan' },
                            { value: 'quarter', label: 'Kuartal' },
                            { value: 'year', label: 'Tahun' },
                        ] as Array<{ value: FarmReportPeriod; label: string }>"
                        :key="period.value"
                        type="button"
                        role="tab"
                        :aria-selected="filters.period === period.value"
                        :class="[
                            'rounded-xl border px-2 py-2 text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                            filters.period === period.value
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-muted text-muted-foreground hover:bg-card',
                        ]"
                        @click="filters.period = period.value"
                    >
                        {{ period.label }}
                    </button>
                </div>
                <div
                    v-if="filters.period === 'month'"
                    class="grid grid-cols-2 gap-2"
                >
                    <label class="sr-only" for="report-month">Bulan</label>
                    <select
                        id="report-month"
                        v-model.number="filters.month"
                        class="h-10 rounded-xl border border-border bg-card px-3 text-xs text-foreground outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                    >
                        <option
                            v-for="month in monthOptions"
                            :key="month.value"
                            :value="month.value"
                        >
                            {{ month.label }}
                        </option>
                    </select>
                    <label class="sr-only" for="report-month-year">Tahun</label>
                    <select
                        id="report-month-year"
                        v-model.number="filters.year"
                        class="h-10 rounded-xl border border-border bg-card px-3 text-xs text-foreground outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                    >
                        <option
                            v-for="year in yearOptions"
                            :key="year"
                            :value="year"
                        >
                            {{ year }}
                        </option>
                    </select>
                </div>
                <div
                    v-else-if="filters.period === 'quarter'"
                    class="grid grid-cols-4 gap-1.5"
                >
                    <button
                        v-for="quarter in [1, 2, 3, 4]"
                        :key="quarter"
                        type="button"
                        :class="[
                            'rounded-xl border px-2 py-2 text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                            filters.quarter === quarter
                                ? 'border-secondary bg-secondary text-secondary-foreground'
                                : 'border-border bg-muted text-muted-foreground hover:bg-card',
                        ]"
                        @click="filters.quarter = quarter as 1 | 2 | 3 | 4"
                    >
                        Q{{ quarter }}
                    </button>
                </div>
                <div v-else class="grid grid-cols-1 gap-2">
                    <label class="sr-only" for="report-year">Tahun</label>
                    <select
                        id="report-year"
                        v-model.number="filters.year"
                        class="h-10 rounded-xl border border-border bg-card px-3 text-xs text-foreground outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                    >
                        <option
                            v-for="year in yearOptions"
                            :key="year"
                            :value="year"
                        >
                            {{ year }}
                        </option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <label class="sr-only" for="report-kebun">Kebun</label>
                    <select
                        id="report-kebun"
                        v-model="filters.kebunId"
                        class="h-10 min-w-0 rounded-xl border border-border bg-card px-3 text-xs text-foreground outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                    >
                        <option value="all">Semua kebun</option>
                        <option
                            v-for="item in kebunOptions"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </option>
                    </select>
                    <label class="sr-only" for="report-commodity">
                        Komoditas
                    </label>
                    <select
                        id="report-commodity"
                        v-model="filters.commodity"
                        :disabled="filters.kebunId !== 'all'"
                        class="h-10 min-w-0 rounded-xl border border-border bg-card px-3 text-xs text-foreground outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                    >
                        <option value="all">Semua komoditas</option>
                        <option value="Jagung">Jagung</option>
                        <option value="Rumput">Rumput odot</option>
                        <option value="Kedelai">Kedelai</option>
                    </select>
                </div>
                <p
                    v-if="filters.kebunId !== 'all'"
                    class="text-[10px] text-muted-foreground"
                >
                    Komoditas mengikuti kebun yang dipilih.
                </p>
            </section>

            <div
                v-if="statusMessage"
                class="rounded-xl bg-muted px-3 py-2 text-xs text-success"
                role="status"
            >
                {{ statusMessage }}
            </div>

            <div
                v-if="isLoading"
                class="grid grid-cols-2 gap-3"
                role="status"
                aria-label="Memuat laporan"
            >
                <div
                    v-for="item in 6"
                    :key="item"
                    class="h-28 animate-pulse rounded-2xl bg-muted"
                />
            </div>
            <p
                v-else-if="errorMessage"
                class="rounded-xl bg-error/10 px-3 py-3 text-sm text-error"
            >
                {{ errorMessage }}
            </p>

            <template v-else-if="report">
                <div
                    class="flex items-center justify-between gap-3 text-xs text-muted-foreground"
                >
                    <span>{{ report.periodLabel }}</span>
                    <span class="truncate text-right">{{
                        report.scopeLabel
                    }}</span>
                </div>

                <section
                    class="grid grid-cols-2 gap-3"
                    aria-label="Ringkasan utama"
                >
                    <article
                        class="rounded-2xl border border-border bg-card p-4 shadow-sm"
                    >
                        <div class="flex items-center gap-2 text-secondary">
                            <TrendingUp :size="18" aria-hidden="true" />
                            <h2 class="text-xs font-medium">Bersih</h2>
                        </div>
                        <p class="mt-3 text-xl font-semibold text-secondary">
                            {{ formatMoney(report.finance.net) }}
                        </p>
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            Margin {{ report.finance.margin }}%
                        </p>
                    </article>
                    <article
                        class="rounded-2xl border border-border bg-card p-4 shadow-sm"
                    >
                        <div class="flex items-center gap-2 text-error">
                            <TrendingDown :size="18" aria-hidden="true" />
                            <h2 class="text-xs font-medium">OPEX</h2>
                        </div>
                        <p class="mt-3 text-xl font-semibold text-error">
                            {{ formatMoney(report.finance.expenses) }}
                        </p>
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            Pengeluaran operasional
                        </p>
                    </article>
                    <article
                        class="rounded-2xl border border-border bg-card p-4 shadow-sm"
                    >
                        <div class="flex items-center gap-2 text-secondary">
                            <TrendingUp :size="18" aria-hidden="true" />
                            <h2 class="text-xs font-medium">
                                Pendapatan kotor
                            </h2>
                        </div>
                        <p class="mt-3 text-xl font-semibold text-secondary">
                            {{ formatMoney(report.finance.revenue) }}
                        </p>
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            Sebelum dikurangi OPEX
                        </p>
                    </article>
                    <article
                        class="rounded-2xl border border-border bg-card p-4 shadow-sm"
                    >
                        <div class="flex items-center gap-2 text-warning">
                            <CalendarClock :size="18" aria-hidden="true" />
                            <h2 class="text-xs font-medium">Panen terdekat</h2>
                        </div>
                        <p
                            class="mt-3 truncate text-sm font-semibold text-foreground"
                        >
                            {{ report.harvest.nextKebun }}
                        </p>
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            {{ report.harvest.nextDate }}
                        </p>
                    </article>
                </section>

                <section
                    class="rounded-2xl border border-border bg-card p-4 shadow-sm"
                    aria-labelledby="report-alert-title"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h2
                            id="report-alert-title"
                            class="text-sm font-semibold"
                        >
                            Perlu perhatian
                        </h2>
                        <span class="text-xs text-error">
                            {{
                                report.summary.critical +
                                report.summary.attention
                            }}
                            kebun
                        </span>
                    </div>
                    <div v-if="report.risks.length" class="mt-3 space-y-2">
                        <div
                            v-for="risk in report.risks"
                            :key="risk.id"
                            class="flex items-start gap-2 rounded-xl bg-error/5 px-3 py-2.5"
                        >
                            <AlertTriangle
                                :size="16"
                                class="mt-0.5 shrink-0 text-error"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <p
                                    class="text-xs font-semibold text-foreground"
                                >
                                    {{ risk.name }} · {{ risk.status }}
                                </p>
                                <p
                                    class="mt-0.5 text-[11px] leading-4 text-muted-foreground"
                                >
                                    {{ risk.message }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="mt-3 flex items-center gap-2 rounded-xl bg-primary/10 px-3 py-2.5 text-xs text-success"
                    >
                        <CheckCircle2 :size="16" aria-hidden="true" />
                        Semua kebun dalam kondisi terpantau.
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-border bg-card p-4 shadow-sm"
                    aria-labelledby="finance-report-title"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <h2
                                id="finance-report-title"
                                class="text-sm font-semibold"
                            >
                                Keuangan
                            </h2>
                            <p class="mt-1 text-[11px] text-muted-foreground">
                                Pendapatan vs pengeluaran
                            </p>
                        </div>
                        <div
                            class="flex gap-3 text-[10px] text-muted-foreground"
                        >
                            <span class="flex items-center gap-1"
                                ><i
                                    class="size-2 rounded-full bg-secondary"
                                />Gross</span
                            >
                            <span class="flex items-center gap-1"
                                ><i
                                    class="size-2 rounded-full bg-warning"
                                />OPEX</span
                            >
                        </div>
                    </div>
                    <div class="mt-4 space-y-3">
                        <article
                            v-for="(farm, index) in report.finance.farms"
                            :key="farm.id"
                            class="grid grid-cols-[minmax(0,1fr)_minmax(130px,1.25fr)_50px] items-center gap-2"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-xs font-semibold">
                                    {{ index + 1 }}. {{ farm.name }}
                                </p>
                                <p
                                    class="truncate text-[10px] text-muted-foreground"
                                >
                                    {{ farm.crop }} · {{ farm.phase }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <div class="flex items-center gap-1.5">
                                    <div
                                        class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full bg-secondary"
                                            :style="{
                                                width: `${report.finance.revenue ? Math.min(100, (farm.revenue / report.finance.revenue) * 100) : 0}%`,
                                            }"
                                        />
                                    </div>
                                    <span class="text-[9px] text-secondary">{{
                                        formatMoney(farm.revenue)
                                    }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div
                                        class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full bg-warning"
                                            :style="{
                                                width: `${report.finance.expenses ? Math.min(100, (farm.expenses / report.finance.expenses) * 100) : 0}%`,
                                            }"
                                        />
                                    </div>
                                    <span class="text-[9px] text-warning">{{
                                        formatMoney(farm.expenses)
                                    }}</span>
                                </div>
                            </div>
                            <span
                                class="rounded-lg border border-primary/40 bg-primary/10 px-1 py-2 text-center text-[9px] font-semibold text-secondary"
                                >{{ farm.roi }}% ROI</span
                            >
                        </article>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-border bg-card p-4 shadow-sm"
                    aria-labelledby="harvest-report-title"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h2
                            id="harvest-report-title"
                            class="text-sm font-semibold"
                        >
                            Panen & pertumbuhan
                        </h2>
                        <span class="text-xs text-secondary"
                            >{{ report.summary.growth }}% rata-rata</span
                        >
                    </div>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Fase aktif: {{ report.growthTimeline.currentStage }} ·
                        Update terakhir
                        {{ report.growthTimeline.lastUpdatedAt }} · Tanam
                        {{ report.growthTimeline.plantedAt }} · Proyeksi panen
                        {{ report.growthTimeline.harvestAt }}
                    </p>
                    <div class="relative mt-5 px-2">
                        <div
                            class="absolute top-3 right-5 left-5 h-1 rounded-full bg-muted"
                            aria-hidden="true"
                        >
                            <div
                                class="h-full rounded-full bg-primary"
                                :style="{
                                    width: `${Math.min(100, report.summary.growth)}%`,
                                }"
                            />
                        </div>
                        <div class="relative grid grid-cols-5 gap-1">
                            <div
                                v-for="stage in report.growthStages"
                                :key="stage.label"
                                class="min-w-0 text-center"
                            >
                                <span
                                    :class="[
                                        'mx-auto flex size-7 items-center justify-center rounded-full border-2 text-[10px] font-bold',
                                        stage.active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-card text-muted-foreground',
                                    ]"
                                >
                                    {{ stage.percent }}%
                                </span>
                                <p
                                    class="mt-2 text-[9px] font-semibold text-secondary"
                                >
                                    {{ stage.count }} kebun
                                </p>
                                <p
                                    class="mt-0.5 line-clamp-2 text-[9px] leading-3 text-muted-foreground"
                                >
                                    {{ stage.label }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 space-y-2">
                        <div
                            v-for="item in report.harvest.items"
                            :key="item.id"
                            class="flex items-center gap-3 rounded-xl bg-muted px-3 py-2.5"
                        >
                            <Wheat
                                :size="17"
                                class="shrink-0 text-secondary"
                                aria-hidden="true"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold">
                                    {{ item.name }}
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    {{ item.crop }} · Panen {{ item.date }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full bg-card px-2 py-1 text-[10px] font-medium text-secondary"
                                >{{ item.status }}</span
                            >
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-border bg-card p-4 shadow-sm"
                    aria-labelledby="ai-report-title"
                >
                    <div class="flex items-center gap-2">
                        <Droplets
                            :size="18"
                            class="text-primary"
                            aria-hidden="true"
                        />
                        <h2 id="ai-report-title" class="text-sm font-semibold">
                            Analisa AI
                        </h2>
                    </div>
                    <div class="mt-3 grid gap-2">
                        <article class="rounded-xl bg-muted p-3">
                            <h3 class="text-xs font-semibold text-secondary">
                                Analisa keuangan
                            </h3>
                            <p
                                class="mt-1 text-[11px] leading-4 text-foreground"
                            >
                                {{ report.ai.finance }}
                            </p>
                        </article>
                        <article class="rounded-xl bg-primary/10 p-3">
                            <h3 class="text-xs font-semibold text-secondary">
                                Analisa kebun
                            </h3>
                            <p
                                class="mt-1 text-[11px] leading-4 text-foreground"
                            >
                                {{ report.ai.farm }}
                            </p>
                        </article>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-border bg-card p-4 shadow-sm"
                    aria-labelledby="activity-report-title"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h2
                            id="activity-report-title"
                            class="text-sm font-semibold"
                        >
                            Aktivitas terbaru
                        </h2>
                        <span class="text-[11px] text-muted-foreground"
                            >{{ report.activities.length }} aktivitas</span
                        >
                    </div>
                    <div class="mt-3 divide-y divide-border">
                        <div
                            v-for="activity in report.activities"
                            :key="activity.id"
                            class="flex gap-3 py-2.5 first:pt-0 last:pb-0"
                        >
                            <span
                                class="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold">
                                    {{ activity.typeLabel }} ·
                                    {{ activity.kebunName }}
                                </p>
                                <p
                                    class="mt-0.5 text-[11px] text-muted-foreground"
                                >
                                    {{ activity.timestamp }} ·
                                    {{ activity.officerName }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <button
                    type="button"
                    class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition-colors hover:bg-success focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    @click="downloadReport"
                >
                    <Download :size="17" aria-hidden="true" />
                    Unduh laporan
                </button>
            </template>
        </div>
    </FarmerMobileShell>
</template>
