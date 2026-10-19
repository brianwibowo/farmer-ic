<script setup lang="ts">
import type { ReportData } from '@/data/report';
defineProps<{ report: ReportData['data']['quarterly_report']; formatMoney: (value: number) => string }>();
</script>

<template>
    <section class="space-y-3">
        <div class="rounded-2xl border border-border bg-card p-4">
            <h2 class="text-sm font-semibold text-foreground">Keuangan Kuartal {{ report.period_label }} per Kebun</h2>
            <div class="mt-3 flex flex-wrap gap-4 text-[10px] text-muted-foreground"><span class="flex items-center gap-1.5"><i class="size-2.5 rounded-sm bg-[#f5a000]" /> OPEX Q2 ({{ formatMoney(report.total_opex_idr) }})</span><span class="flex items-center gap-1.5"><i class="size-2.5 rounded-sm bg-[#2fbd6a]" /> Est. Pendapatan Q2 ({{ formatMoney(report.estimated_revenue_idr) }})</span></div>
            <div class="mt-4 space-y-3">
                <article v-for="(farm, index) in report.farms" :key="farm.id" class="grid grid-cols-[minmax(0,1fr)_minmax(104px,1.2fr)_48px] items-center gap-2"><div class="min-w-0"><p class="truncate text-[10px] font-semibold text-foreground">{{ index + 1 }}. {{ farm.name }}</p><p class="truncate text-[9px] text-muted-foreground">{{ farm.commodity }} · {{ farm.status }}</p></div><div class="space-y-1"><div class="flex items-center gap-1"><div class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-[#2fbd6a]" :style="{ width: `${farm.progress_percentage}%` }" /></div><span class="text-[8px] text-[#2fbd6a]">{{ formatMoney(farm.estimated_revenue_idr) }}</span></div><div class="flex items-center gap-1"><div class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-[#f5a000]" :style="{ width: `${Math.min(100, farm.opex_idr / 10000000)}%` }" /></div><span class="text-[8px] text-[#f5a000]">{{ formatMoney(farm.opex_idr) }}</span></div></div><span class="rounded-lg border border-[#0e9287]/40 bg-[#0e9287]/10 px-1 py-2 text-center text-[9px] font-semibold text-[#0e9287]">{{ farm.roi_percentage }}% ROI</span></article>
            </div>
        </div>
        <div class="rounded-2xl border border-border bg-card p-4"><h2 class="text-sm font-semibold text-foreground">Daftar Total Kebun - Rekap {{ report.period_label }}</h2><div class="mt-3 space-y-2"><article v-for="farm in report.farm_recap" :key="farm.id" class="flex items-center gap-3 rounded-xl border border-border bg-muted p-3"><span class="grid size-10 shrink-0 place-items-center rounded-full bg-card text-xl">{{ farm.icon }}</span><div class="min-w-0 flex-1"><h3 class="truncate text-xs font-semibold text-foreground">{{ farm.name }}<span v-if="farm.province"> ({{ farm.province }})</span></h3><p class="truncate text-[9px] text-muted-foreground">{{ farm.area_hectares }} Ha · {{ farm.commodity_summary }} · {{ farm.phase }}<span v-if="farm.hst"> ({{ farm.hst }} HST)</span></p><p class="text-[9px] text-muted-foreground">OPEX Q2: {{ formatMoney(farm.opex_idr) }} · Est: {{ formatMoney(farm.estimated_revenue_idr) }}</p></div><span :class="farm.status_label === 'Siap Panen' ? 'border-[#0e9287] bg-[#0e9287]/10 text-[#0e9287]' : 'border-[#f5b74c] bg-[#f5b74c]/10 text-[#a66d00]'" class="shrink-0 rounded-lg border px-2 py-2 text-[9px] font-semibold">{{ farm.status_label }}</span></article></div></div>
    </section>
</template>
