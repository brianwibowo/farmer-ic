<script setup lang="ts">
import type { ReportCommodity, ReportPeriod } from '@/data/report';

const props = defineProps<{
    period: ReportPeriod;
    selectedWeek: number;
    selectedMonth: string;
    selectedQuarter: string;
    selectedCommodity: ReportCommodity;
}>();

const emit = defineEmits<{
    'update:period': [value: ReportPeriod];
    'update:selectedWeek': [value: number];
    'update:selectedMonth': [value: string];
    'update:selectedQuarter': [value: string];
    'update:selectedCommodity': [value: ReportCommodity];
}>();

const periods: Array<{ value: ReportPeriod; label: string }> = [
    { value: 'minggu', label: 'Minggu' },
    { value: 'bulan', label: 'Bulan' },
    { value: 'kuartal', label: 'Kuartal' },
];
const commodities: ReportCommodity[] = ['Semua', 'Jagung', 'Kedelai', 'Rumput Pakan'];
const commodityCounts: Record<ReportCommodity, number> = { Semua: 10, Jagung: 3, Kedelai: 3, 'Rumput Pakan': 4 };
const monthOptions = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'];
const quarterOptions = ['Q1 (Jan - Mar)', 'Q2 (Apr - Jun)', 'Q3 (Jul - Sep)', 'Q4 (Okt - Des)'];
</script>

<template>
    <section class="space-y-2" aria-label="Filter laporan">
        <div class="grid grid-cols-3 gap-2" role="tablist">
            <button v-for="item in periods" :key="item.value" type="button" role="tab" :aria-selected="props.period === item.value" :class="['rounded-xl border px-2 py-2 text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring', props.period === item.value ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-muted text-muted-foreground hover:bg-card']" @click="emit('update:period', item.value)">{{ item.label }}</button>
        </div>
        <div v-if="props.period === 'minggu'" class="grid grid-cols-2 gap-2">
            <label class="sr-only" for="report-week">Minggu</label>
            <select id="report-week" :value="props.selectedWeek" class="h-9 rounded-lg border border-border bg-card px-3 text-xs text-foreground outline-none focus:ring-2 focus:ring-primary/30" @change="emit('update:selectedWeek', Number(($event.target as HTMLSelectElement).value))">
                <option v-for="week in 4" :key="week" :value="week">Minggu {{ week }} ({{ (week - 1) * 7 + 1 }}–{{ week * 7 }} Januari)</option>
            </select>
            <label class="sr-only" for="report-month">Bulan</label>
            <select id="report-month" :value="props.selectedMonth" class="h-9 rounded-lg border border-border bg-card px-3 text-xs text-foreground outline-none focus:ring-2 focus:ring-primary/30" @change="emit('update:selectedMonth', ($event.target as HTMLSelectElement).value)">
                <option>Januari 2026</option><option>Februari 2026</option><option>Maret 2026</option><option>April 2026</option>
            </select>
        </div>
        <div v-else-if="props.period === 'bulan'" class="grid grid-cols-6 gap-1">
            <button v-for="month in monthOptions" :key="month" type="button" :class="['min-w-0 whitespace-nowrap rounded-xl border px-1 py-2 text-[9px] font-semibold transition-colors', props.selectedMonth.startsWith(month) ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-muted text-muted-foreground hover:bg-card']" @click="emit('update:selectedMonth', `${month} 2026`)">{{ month.slice(0, 3) }}</button>
        </div>
        <div v-else class="grid grid-cols-4 gap-1">
            <button v-for="quarter in quarterOptions" :key="quarter" type="button" :class="['min-w-0 whitespace-nowrap rounded-xl border px-1 py-2 text-[9px] font-semibold transition-colors', props.selectedQuarter === quarter ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-muted text-muted-foreground hover:bg-card']" @click="emit('update:selectedQuarter', quarter)">{{ quarter }}</button>
        </div>
        <div class="flex gap-2 overflow-x-auto pb-1" role="radiogroup" aria-label="Filter komoditas">
            <button v-for="commodity in commodities" :key="commodity" type="button" role="radio" :aria-checked="props.selectedCommodity === commodity" :class="['shrink-0 rounded-lg border px-3 py-2 text-xs font-semibold transition-colors', props.selectedCommodity === commodity ? 'border-secondary bg-secondary text-secondary-foreground' : commodity === 'Jagung' ? 'border-warning bg-warning/10 text-warning' : commodity === 'Kedelai' ? 'border-success bg-success/10 text-success' : commodity === 'Rumput Pakan' ? 'border-sky-500 bg-sky-500/10 text-sky-700 dark:text-sky-300' : 'border-border bg-card text-muted-foreground']" @click="emit('update:selectedCommodity', commodity)">{{ commodity === 'Rumput Pakan' ? 'Rumput' : commodity }} ({{ commodityCounts[commodity] }})</button>
        </div>
    </section>
</template>
