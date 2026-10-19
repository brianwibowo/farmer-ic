<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import ReportFilters from '@/components/farmer/ReportFilters.vue';
import ReportMonthlyView from '@/components/farmer/ReportMonthlyView.vue';
import ReportQuarterlyView from '@/components/farmer/ReportQuarterlyView.vue';
import ReportWeeklyView from '@/components/farmer/ReportWeeklyView.vue';
import { clearDemoStorage } from '@/composables/useDemoStorage';
import type { ReportCommodity, ReportData, ReportPeriod } from '@/data/report';
import { getReportData } from '@/services/reportService';

const period = ref<ReportPeriod>('minggu');
const selectedWeek = ref(3);
const selectedMonth = ref('Januari 2026');
const selectedQuarter = ref('Q1 (Jan - Mar)');
const selectedCommodity = ref<ReportCommodity>('Semua');
const report = ref<ReportData | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');
const statusMessage = ref('');
const data = computed(() => report.value?.data);
const monthlyReports = computed(() => data.value?.monthly_commodity_reports ?? []);
const quarterly = computed(() => data.value?.quarterly_report);

const formatMoney = (value: number): string => {
    if (value >= 1_000_000_000) return `Rp ${(value / 1_000_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} M`;
    if (value >= 1_000_000) return `Rp ${(value / 1_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} jt`;
    return `Rp ${(value / 1_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} rb`;
};

async function loadReport(): Promise<void> {
    isLoading.value = true;
    errorMessage.value = '';
    try { report.value = await getReportData(period.value, selectedCommodity.value); }
    catch { errorMessage.value = 'Data laporan belum dapat dimuat. Coba lagi.'; }
    finally { isLoading.value = false; }
}
async function changePeriod(value: ReportPeriod): Promise<void> { period.value = value; await loadReport(); }
async function changeCommodity(value: ReportCommodity): Promise<void> { selectedCommodity.value = value; await loadReport(); }
async function resetDemoData(): Promise<void> { clearDemoStorage(); statusMessage.value = 'Data demo laporan telah direset.'; await loadReport(); }
onMounted(() => void loadReport());
</script>

<template>
    <Head title="Laporan" />
    <FarmerMobileShell active-key="report">
        <div class="space-y-4 px-4 pt-5 pb-6">
            <header class="flex items-start justify-between gap-3"><div><p class="text-xs font-medium text-muted-foreground">Ringkasan operasional</p><h1 class="mt-1 text-2xl font-semibold tracking-tight text-foreground">Laporan</h1></div><button type="button" class="text-xs font-medium text-success underline-offset-2 hover:underline" @click="void resetDemoData()">Reset demo</button></header>
            <ReportFilters v-model:period="period" v-model:selected-week="selectedWeek" v-model:selected-month="selectedMonth" v-model:selected-quarter="selectedQuarter" v-model:selected-commodity="selectedCommodity" @update:period="changePeriod" @update:selected-commodity="changeCommodity" />
            <div v-if="statusMessage" class="rounded-lg bg-muted px-3 py-2 text-sm text-success" role="status">{{ statusMessage }}</div>
            <div v-if="isLoading" class="grid grid-cols-2 gap-3" aria-live="polite"><div v-for="item in 4" :key="item" class="h-28 animate-pulse rounded-xl bg-muted" /><div class="col-span-2 h-64 animate-pulse rounded-xl bg-muted" /></div>
            <p v-else-if="errorMessage" class="rounded-lg bg-error/10 px-3 py-2 text-sm text-error">{{ errorMessage }}</p>
            <div v-else-if="!data" class="rounded-xl bg-muted px-5 py-10 text-center text-sm text-muted-foreground">Belum ada data laporan untuk periode ini.</div>
            <ReportMonthlyView v-else-if="period === 'bulan'" :reports="monthlyReports" :month="selectedMonth" />
            <ReportQuarterlyView v-else-if="period === 'kuartal' && quarterly" :report="quarterly" :format-money="formatMoney" />
            <ReportWeeklyView v-else-if="data" :data="data" :month="selectedMonth" :format-money="formatMoney" />
        </div>
    </FarmerMobileShell>
</template>
