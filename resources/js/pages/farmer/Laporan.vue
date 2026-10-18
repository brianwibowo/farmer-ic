<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Leaf, Sprout, Wheat } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import { clearDemoStorage } from '@/composables/useDemoStorage';
import {
    type ReportCommodity,
    type ReportData,
    type ReportPeriod,
} from '@/data/report';
import { getReportData } from '@/services/reportService';

const period = ref<ReportPeriod>('minggu');
const selectedWeek = ref(3);
const selectedMonth = ref('Januari 2026');
const selectedQuarter = ref('Q1 (Jan - Mar)');
const monthOptions = computed(() => {
    const month = selectedMonth.value.split(' ')[0];
    return ['Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'].includes(month)
        ? ['Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
        : ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'];
});
const quarterOptions = ['Q1 (Jan - Mar)', 'Q2 (Apr - Jun)', 'Q3 (Jul - Sep)', 'Q4 (Okt - Des)'];
const selectedCommodity = ref<ReportCommodity>('Semua');
const report = ref<ReportData | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');
const statusMessage = ref('');

const periods: Array<{ value: ReportPeriod; label: string }> = [
    { value: 'minggu', label: 'Minggu' },
    { value: 'bulan', label: 'Bulan' },
    { value: 'kuartal', label: 'Kuartal' },
];
const commodities: ReportCommodity[] = [
    'Semua',
    'Jagung',
    'Kedelai',
    'Rumput Pakan',
];
const commodityCounts: Record<ReportCommodity, number> = {
    Semua: 10,
    Jagung: 3,
    Kedelai: 3,
    'Rumput Pakan': 4,
};

const data = computed(() => report.value?.data);
const monthlyReports = computed(() => data.value?.monthly_commodity_reports ?? []);
const quarterly = computed(() => data.value?.quarterly_report);
const chartColors = {
    kedelai: '#63a20a',
    jagung: '#f5b74c',
    rumput: '#0e9287',
};
const metricColors = {
    area: '#63a20a',
    opex: '#d38123',
    rev: '#3469dc',
    rci: '#7f47dd',
};

function metricStyle(color: string): Record<string, string> {
    void color;
    return { borderColor: 'var(--border)', backgroundColor: 'var(--card)' };
}

function metricBadgeStyle(color: string): Record<string, string> {
    return { borderColor: `${color}99`, backgroundColor: `${color}18`, color };
}

function commodityColor(commodity: string): string {
    if (commodity === 'Jagung') return chartColors.jagung;
    if (commodity === 'Kedelai') return chartColors.kedelai;
    return chartColors.rumput;
}

function targetCardStyle(commodity: string): Record<string, string> {
    const color = commodityColor(commodity);
    return {
        borderColor: color,
        backgroundColor: `${color}18`,
    };
}

function commodityTone(commodity: string): string {
    if (commodity === 'Jagung') {
        return 'text-amber-500';
    }
    if (commodity === 'Kedelai') {
        return 'text-lime-600 dark:text-lime-300';
    }
    return 'text-sky-500 dark:text-sky-300';
}

function stageColor(index: number): string {
    return ['#168dcc', '#2fbd6a', '#f5a000', '#138f79'][index] ?? '#138f79';
}

const formatCompactRupiah = (value: number): string => {
    const absolute = Math.abs(value);
    const sign = value < 0 ? '-' : '';
    if (absolute >= 1_000_000_000) {
        return `${sign}Rp ${(absolute / 1_000_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} M`;
    }
    if (absolute >= 1_000_000) {
        return `${sign}Rp ${(absolute / 1_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} jt`;
    }
    if (absolute >= 1_000) {
        return `${sign}Rp ${(absolute / 1_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} rb`;
    }
    return `${sign}Rp ${absolute.toLocaleString('id-ID')}`;
};

const donutBackground = computed(() => {
    const points = data.value?.land_and_cash_proportion.donut_chart_data ?? [];
    if (!points.length) return 'var(--muted)';

    let offset = 0;
    const colors = [chartColors.jagung, chartColors.kedelai, chartColors.rumput];
    const stops = points.map((point, index) => {
        const start = offset;
        offset += point.area_percentage;
        const gap = Math.min(0.8, point.area_percentage / 4);
        return `#ffffff ${start}% ${start + gap}%, ${colors[index % colors.length]} ${start + gap}% ${offset - gap}%, #ffffff ${offset - gap}% ${offset}%`;
    });

    return `conic-gradient(${stops.join(', ')})`;
});

async function loadReport(): Promise<void> {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        report.value = await getReportData(period.value, selectedCommodity.value);
    } catch {
        errorMessage.value = 'Data laporan belum dapat dimuat. Coba lagi.';
    } finally {
        isLoading.value = false;
    }
}

async function selectPeriod(nextPeriod: ReportPeriod): Promise<void> {
    period.value = nextPeriod;
    await loadReport();
}

async function selectCommodity(commodity: ReportCommodity): Promise<void> {
    selectedCommodity.value = commodity;
    await loadReport();
}

async function resetDemoData(): Promise<void> {
    clearDemoStorage();
    statusMessage.value = 'Data demo laporan telah direset.';
    await loadReport();
}

onMounted(() => void loadReport());
</script>

<template>
    <Head title="Laporan" />

    <FarmerMobileShell active-key="report">
        <div class="space-y-4 px-4 pt-5 pb-6">
            <header class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-medium text-muted-foreground">
                        Ringkasan operasional
                    </p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-foreground">
                        Laporan
                    </h1>
                </div>
                <button
                    type="button"
                    class="shrink-0 text-xs font-medium text-success underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    @click="void resetDemoData()"
                >
                    Reset demo
                </button>
            </header>

            <section class="space-y-2" aria-label="Filter laporan">
                <div class="grid grid-cols-3 gap-2" role="tablist">
                    <button
                        v-for="item in periods"
                        :key="item.value"
                        type="button"
                        role="tab"
                        :aria-selected="period === item.value"
                        :class="[
                            'rounded-xl border px-2 py-2 text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                            period === item.value
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-muted text-muted-foreground hover:bg-card',
                        ]"
                        @click="void selectPeriod(item.value)"
                    >
                        {{ item.label }}
                    </button>
                </div>

                <div v-if="period === 'minggu'" class="grid grid-cols-2 gap-2">
                    <label class="sr-only" for="report-week">Minggu</label>
                    <select
                        id="report-week"
                        v-model="selectedWeek"
                        class="h-9 rounded-lg border border-border bg-card px-3 text-xs text-foreground outline-none focus:ring-2 focus:ring-primary/30"
                    >
                        <option v-for="week in 4" :key="week" :value="week">
                            Minggu {{ week }} ({{ (week - 1) * 7 + 1 }}–{{ week * 7 }} Januari)
                        </option>
                    </select>
                    <label class="sr-only" for="report-month">Bulan</label>
                    <select
                        id="report-month"
                        v-model="selectedMonth"
                        class="h-9 rounded-lg border border-border bg-card px-3 text-xs text-foreground outline-none focus:ring-2 focus:ring-primary/30"
                    >
                        <option>Januari 2026</option>
                        <option>Februari 2026</option>
                        <option>Maret 2026</option>
                        <option>April 2026</option>
                    </select>
                </div>

                <div v-else-if="period === 'bulan'" class="grid grid-cols-6 gap-1">
                    <button
                        v-for="month in monthOptions"
                        :key="month"
                        type="button"
                        :class="[
                            'min-w-0 whitespace-nowrap rounded-xl border px-1 py-2 text-[9px] font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                            selectedMonth.startsWith(month)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-muted text-muted-foreground hover:bg-card',
                        ]"
                        @click="selectedMonth = `${month} 2026`"
                    >
                        {{ month.slice(0, 3) }}
                    </button>
                </div>

                <div v-else class="grid grid-cols-4 gap-1">
                    <button
                        v-for="quarter in quarterOptions"
                        :key="quarter"
                        type="button"
                        :class="[
                            'min-w-0 whitespace-nowrap rounded-xl border px-1 py-2 text-[9px] font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                            selectedQuarter === quarter
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-muted text-muted-foreground hover:bg-card',
                        ]"
                        @click="selectedQuarter = quarter"
                    >
                        {{ quarter }}
                    </button>
                </div>

                <div class="flex gap-2 overflow-x-auto pb-1" role="radiogroup" aria-label="Filter komoditas">
                    <button
                        v-for="commodity in commodities"
                        :key="commodity"
                        type="button"
                        role="radio"
                        :aria-checked="selectedCommodity === commodity"
                        :class="[
                            'shrink-0 rounded-lg border px-3 py-2 text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                            selectedCommodity === commodity
                                ? 'border-secondary bg-secondary text-secondary-foreground'
                                : commodity === 'Jagung'
                                  ? 'border-warning bg-warning/10 text-warning hover:bg-warning/20'
                                  : commodity === 'Kedelai'
                                    ? 'border-success bg-success/10 text-success hover:bg-success/20'
                                    : commodity === 'Rumput Pakan'
                                      ? 'border-sky-500 bg-sky-500/10 text-sky-700 hover:bg-sky-500/20 dark:text-sky-300'
                                      : 'border-border bg-card text-muted-foreground hover:border-primary hover:text-primary',
                        ]"
                        @click="void selectCommodity(commodity)"
                    >
                        {{ commodity === 'Rumput Pakan' ? 'Rumput' : commodity }} ({{ commodityCounts[commodity] }})
                    </button>
                </div>
            </section>

            <div v-if="statusMessage" class="rounded-lg bg-muted px-3 py-2 text-sm text-success" role="status">
                {{ statusMessage }}
            </div>

            <div v-if="isLoading" class="grid grid-cols-2 gap-3" aria-live="polite" aria-label="Memuat laporan">
                <div v-for="item in 4" :key="item" class="h-28 animate-pulse rounded-xl bg-muted" />
                <div class="col-span-2 h-64 animate-pulse rounded-xl bg-muted" />
            </div>

            <p v-else-if="errorMessage" class="rounded-lg bg-error/10 px-3 py-2 text-sm text-error">
                {{ errorMessage }}
            </p>

            <div v-else-if="!data" class="rounded-xl bg-muted px-5 py-10 text-center text-sm text-muted-foreground">
                Belum ada data laporan untuk periode ini.
            </div>

            <template v-else-if="period === 'bulan'">
                <section class="space-y-3" aria-labelledby="monthly-report-title">
                    <div class="flex items-center justify-between gap-2">
                        <h2 id="monthly-report-title" class="truncate text-sm font-semibold text-foreground">
                            Progress Fase Tumbuh · {{ selectedMonth }}
                        </h2>
                        <span class="shrink-0 text-[10px] text-muted-foreground">Per komoditas</span>
                    </div>
                    <article
                        v-for="monthly in monthlyReports"
                        :key="monthly.id"
                        class="rounded-2xl border-2 bg-card p-3"
                        :style="{ borderColor: commodityColor(monthly.commodity_name.replace('Tanaman ', '')) }"
                    >
                        <header
                            class="flex items-center justify-between gap-2 rounded-lg px-2 py-2"
                            :style="{
                                backgroundColor: `${commodityColor(monthly.commodity_name.replace('Tanaman ', ''))}20`,
                            }"
                        >
                            <h3 class="flex min-w-0 items-center gap-1.5 truncate text-xs font-semibold" :style="{ color: commodityColor(monthly.commodity_name.replace('Tanaman ', '')) }">
                                <Wheat v-if="monthly.commodity_name.includes('Jagung')" :size="15" :stroke-width="2" aria-hidden="true" />
                                <Sprout v-else-if="monthly.commodity_name.includes('Kedelai')" :size="15" :stroke-width="2" aria-hidden="true" />
                                <Leaf v-else :size="15" :stroke-width="2" aria-hidden="true" />
                                <span class="truncate">{{ monthly.order }}. {{ monthly.commodity_name }} ({{ monthly.farm_count }} Kebun · {{ monthly.total_area_hectares }} Ha)</span>
                            </h3>
                            <span class="shrink-0 text-[10px] text-muted-foreground">{{ monthly.period_label }}</span>
                        </header>
                        <div class="mt-3 space-y-3">
                            <div v-for="(stage, stageIndex) in monthly.stages" :key="stage.label" class="grid min-w-0 grid-cols-[92px_minmax(0,1fr)_76px] items-center gap-2">
                                <span class="line-clamp-2 min-w-0 text-[9px] leading-3 text-foreground">{{ stage.label }}</span>
                                <div class="min-w-0 h-2 overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full"
                                        :style="{
                                            width: `${stage.progress_percentage}%`,
                                            backgroundColor: stageColor(stageIndex),
                                        }"
                                    />
                                </div>
                                <span
                                    class="line-clamp-2 min-w-0 text-right text-[8px] leading-3 font-medium"
                                    :style="{ color: stageColor(stageIndex) }"
                                >
                                    {{ stage.value_label }}
                                </span>
                            </div>
                        </div>
                    </article>
                </section>
            </template>

            <template v-else-if="period === 'kuartal' && quarterly">
                <section class="space-y-3" aria-labelledby="quarter-finance-title">
                    <div class="rounded-2xl border border-border bg-card p-4">
                        <h2 id="quarter-finance-title" class="text-sm font-semibold text-foreground">
                            Keuangan Kuartal {{ quarterly.period_label }} per Kebun
                        </h2>
                        <div class="mt-3 flex flex-wrap gap-4 text-[10px] text-muted-foreground">
                            <span class="flex items-center gap-1.5"><i class="size-2.5 rounded-sm bg-[#f5a000]" /> OPEX Q2 ({{ formatCompactRupiah(quarterly.total_opex_idr) }})</span>
                            <span class="flex items-center gap-1.5"><i class="size-2.5 rounded-sm bg-[#2fbd6a]" /> Est. Pendapatan Q2 ({{ formatCompactRupiah(quarterly.estimated_revenue_idr) }})</span>
                        </div>
                        <div class="mt-4 space-y-3">
                            <article v-for="(farm, index) in quarterly.farms" :key="farm.id" class="grid grid-cols-[minmax(0,1fr)_minmax(104px,1.2fr)_48px] items-center gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-[10px] font-semibold text-foreground">{{ index + 1 }}. {{ farm.name }}</p>
                                    <p class="truncate text-[9px] text-muted-foreground">{{ farm.commodity }} · {{ farm.status }}</p>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1"><div class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-[#2fbd6a]" :style="{ width: `${farm.progress_percentage}%` }" /></div><span class="text-[8px] text-[#2fbd6a]">{{ formatCompactRupiah(farm.estimated_revenue_idr) }}</span></div>
                                    <div class="flex items-center gap-1"><div class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-[#f5a000]" :style="{ width: `${Math.min(100, farm.opex_idr / 10000000)}%` }" /></div><span class="text-[8px] text-[#f5a000]">{{ formatCompactRupiah(farm.opex_idr) }}</span></div>
                                </div>
                                <span class="rounded-lg border border-[#0e9287]/40 bg-[#0e9287]/10 px-1 py-2 text-center text-[9px] font-semibold text-[#0e9287]">{{ farm.roi_percentage }}% ROI</span>
                            </article>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-4">
                        <h2 class="text-sm font-semibold text-foreground">Daftar Total Kebun - Rekap {{ quarterly.period_label }}</h2>
                        <div class="mt-3 space-y-2">
                            <article v-for="farm in quarterly.farm_recap" :key="farm.id" class="flex items-center gap-3 rounded-xl border border-border bg-muted p-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-card text-xl" aria-hidden="true">{{ farm.icon }}</span>
                                <div class="min-w-0 flex-1">
                                    <h3 class="truncate text-xs font-semibold text-foreground">{{ farm.name }}<span v-if="farm.province"> ({{ farm.province }})</span></h3>
                                    <p class="truncate text-[9px] text-muted-foreground">{{ farm.area_hectares }} Ha · {{ farm.commodity_summary }} · {{ farm.phase }}<span v-if="farm.hst"> ({{ farm.hst }} HST)</span></p>
                                    <p class="text-[9px] text-muted-foreground">OPEX Q2: {{ formatCompactRupiah(farm.opex_idr) }} · Est: {{ formatCompactRupiah(farm.estimated_revenue_idr) }}</p>
                                </div>
                                <span :class="farm.status_label === 'Siap Panen' ? 'border-[#0e9287] bg-[#0e9287]/10 text-[#0e9287]' : 'border-[#f5b74c] bg-[#f5b74c]/10 text-[#a66d00]'" class="shrink-0 rounded-lg border px-2 py-2 text-[9px] font-semibold">{{ farm.status_label }}</span>
                            </article>
                        </div>
                    </div>
                </section>
            </template>

            <template v-else>
                <section class="grid grid-cols-2 gap-3" aria-label="Ringkasan laporan">
                    <article class="relative rounded-xl border p-3 shadow-sm" :style="metricStyle(metricColors.area)">
                        <span class="absolute top-3 right-3 grid size-8 place-items-center rounded-full border text-[10px] font-semibold" :style="metricBadgeStyle(metricColors.area)">Ha</span>
                        <p class="text-xs text-muted-foreground">Total Luas</p>
                        <p class="mt-2 text-xl font-semibold text-foreground">
                            {{ data.summary_cards.total_area.total_hectares }} Ha
                        </p>
                        <p class="mt-1 text-[11px] text-success">
                            {{ data.summary_cards.total_area.total_commodities }} komoditas aktif
                        </p>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full" :style="{ width: `${data.summary_cards.total_area.commodities_progress_percentage}%`, backgroundColor: metricColors.area }" />
                        </div>
                    </article>
                    <article class="relative rounded-xl border p-3 shadow-sm" :style="metricStyle(metricColors.opex)">
                        <span class="absolute top-3 right-3 grid size-8 place-items-center rounded-full border text-[10px] font-semibold" :style="metricBadgeStyle(metricColors.opex)">OPX</span>
                        <p class="text-xs text-muted-foreground">OPEX Minggu ke-{{ data.filter_metadata.week }}</p>
                        <p class="mt-2 text-xl font-semibold text-foreground">
                            {{ formatCompactRupiah(data.summary_cards.weekly_opex.current_week_opex_idr) }}
                        </p>
                        <p class="mt-1 text-[11px] text-warning">
                            Bulan ini {{ formatCompactRupiah(data.summary_cards.weekly_opex.current_month_opex_idr) }}
                        </p>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full" :style="{ width: `${data.summary_cards.weekly_opex.monthly_budget_progress_percentage}%`, backgroundColor: metricColors.opex }" />
                        </div>
                    </article>
                    <article class="relative rounded-xl border p-3 shadow-sm" :style="metricStyle(metricColors.rev)">
                        <span class="absolute top-3 right-3 grid size-8 place-items-center rounded-full border text-[10px] font-semibold" :style="metricBadgeStyle(metricColors.rev)">REV</span>
                        <p class="text-xs text-muted-foreground">Est. Panen Minggu Ini</p>
                        <p class="mt-2 text-xl font-semibold text-foreground">
                            {{ formatCompactRupiah(data.summary_cards.estimated_harvest.total_estimated_revenue_idr) }}
                        </p>
                        <p class="mt-1 truncate text-[11px] text-primary">
                            {{ data.summary_cards.estimated_harvest.harvesting_commodities.join(', ') }}
                        </p>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full" :style="{ width: `${data.summary_cards.estimated_harvest.harvest_progress_percentage}%`, backgroundColor: metricColors.rev }" />
                        </div>
                    </article>
                    <article class="relative rounded-xl border p-3 shadow-sm" :style="metricStyle(metricColors.rci)">
                        <span class="absolute top-3 right-3 grid size-8 place-items-center rounded-full border text-[10px] font-semibold" :style="metricBadgeStyle(metricColors.rci)">RCI</span>
                        <p class="text-xs text-muted-foreground">Efisiensi Mingguan</p>
                        <p class="mt-2 text-xl font-semibold text-foreground">
                            {{ data.summary_cards.budget_efficiency.efficiency_percentage }}%
                        </p>
                        <p class="mt-1 text-[11px] text-success">
                            {{ data.summary_cards.budget_efficiency.ceiling_compliance_status }}
                        </p>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full" :style="{ width: `${data.summary_cards.budget_efficiency.ceiling_usage_percentage}%`, backgroundColor: metricColors.rci }" />
                        </div>
                    </article>
                </section>

                <section class="rounded-2xl border border-border bg-card p-4" aria-labelledby="proportion-title">
                    <h2 id="proportion-title" class="text-sm font-semibold text-foreground">
                        Proporsi Lahan & Kas Mingguan (Minggu {{ data.filter_metadata.week }} - {{ selectedMonth }})
                    </h2>
                    <div class="mt-4 flex items-center gap-4">
                        <div class="relative grid size-36 shrink-0 place-items-center rounded-full" :style="{ background: donutBackground }">
                            <div class="grid size-20 place-items-center rounded-full bg-card text-center">
                                <strong class="text-sm text-foreground">{{ data.summary_cards.total_area.total_hectares }} Ha</strong>
                                <span class="text-[10px] text-muted-foreground">Minggu {{ data.filter_metadata.week }}</span>
                            </div>
                        </div>
                        <ul class="min-w-0 space-y-3">
                            <li v-for="item in data.land_and_cash_proportion.donut_chart_data" :key="item.commodity_name" class="text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="size-2.5 shrink-0 rounded-full border border-white" :style="{ backgroundColor: commodityColor(item.commodity_name) }" />
                                    <span class="truncate font-medium text-foreground">{{ item.commodity_name }}</span>
                                    <span class="ml-auto text-muted-foreground">{{ item.area_percentage }}%</span>
                                </div>
                                <p class="mt-1 pl-4 text-[10px] leading-4 text-muted-foreground">
                                    {{ item.area_hectares }} Ha · {{ item.area_percentage }}%
                                </p>
                                <p class="pl-4 text-[9px] leading-3" :class="commodityTone(item.commodity_name)">
                                    Minggu aktif: {{ formatCompactRupiah(item.weekly_opex_idr) }} OPEX | Est. {{ formatCompactRupiah(item.estimated_revenue_idr) }}
                                </p>
                            </li>
                        </ul>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-4" aria-labelledby="targets-title">
                    <div>
                        <h2 id="targets-title" class="truncate text-sm font-semibold text-foreground">
                            Target Operasional Minggu ke-{{ data.filter_metadata.week }} · {{ selectedMonth }}
                        </h2>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2">
                    <article v-for="target in data.operational_targets" :key="target.id" class="min-w-0 rounded-xl border p-2 text-center" :style="targetCardStyle(target.commodity_name)">
                        <span class="mx-auto grid size-8 place-items-center rounded-lg bg-surface text-base" :class="commodityTone(target.commodity_name)" aria-hidden="true">{{ target.icon }}</span>
                        <h3 class="mt-1 truncate text-xs font-semibold text-foreground">{{ target.commodity_name }}</h3>
                        <p class="mt-1 truncate text-[9px] text-muted-foreground">{{ target.farm_name }} · {{ target.area_hectares }} Ha</p>
                        <span class="mt-2 inline-flex rounded-full bg-card px-1.5 py-0.5 text-[9px] font-semibold text-secondary">{{ target.fase.code }}</span>
                        <p class="mt-2 line-clamp-2 min-h-7 text-[9px] leading-3 text-foreground">{{ target.status_description }}</p>
                        <p class="mt-1 line-clamp-2 min-h-6 text-[9px] leading-3 text-muted-foreground">{{ target.operational_task.task_name }}</p>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-card">
                            <div class="h-full rounded-full bg-primary" :style="{ width: `${target.operational_task.progress_percentage}%` }" />
                        </div>
                        <p class="mt-1 text-[9px] font-medium text-muted-foreground">{{ target.operational_task.progress_percentage }}%</p>
                    </article>
                    </div>
                </section>
            </template>
        </div>
    </FarmerMobileShell>
</template>
