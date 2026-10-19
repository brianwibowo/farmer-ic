<script setup lang="ts">
import { Leaf, Sprout, Wheat } from '@lucide/vue';
import type { ReportData } from '@/data/report';

defineProps<{ reports: ReportData['data']['monthly_commodity_reports']; month: string }>();
const color = (name: string) => name.includes('Jagung') ? '#f5b74c' : name.includes('Kedelai') ? '#63a20a' : '#0e9287';
const stageColor = (index: number) => ['#168dcc', '#2fbd6a', '#f5a000', '#138f79'][index] ?? '#138f79';
</script>

<template>
    <section class="space-y-3" aria-labelledby="monthly-report-title">
        <div class="flex items-center justify-between gap-2"><h2 id="monthly-report-title" class="truncate text-sm font-semibold text-foreground">Progress Fase Tumbuh · {{ month }}</h2><span class="text-[10px] text-muted-foreground">Per komoditas</span></div>
        <article v-for="monthly in reports" :key="monthly.id" class="rounded-2xl border-2 bg-card p-3" :style="{ borderColor: color(monthly.commodity_name) }">
            <header class="flex items-center justify-between gap-2 rounded-lg px-2 py-2" :style="{ backgroundColor: `${color(monthly.commodity_name)}20` }">
                <h3 class="flex min-w-0 items-center gap-1.5 truncate text-xs font-semibold" :style="{ color: color(monthly.commodity_name) }"><Wheat v-if="monthly.commodity_name.includes('Jagung')" :size="15" /><Sprout v-else-if="monthly.commodity_name.includes('Kedelai')" :size="15" /><Leaf v-else :size="15" /><span class="truncate">{{ monthly.order }}. {{ monthly.commodity_name }} ({{ monthly.farm_count }} Kebun · {{ monthly.total_area_hectares }} Ha)</span></h3>
                <span class="shrink-0 text-[10px] text-muted-foreground">{{ monthly.period_label }}</span>
            </header>
            <div class="mt-3 space-y-3">
                <div v-for="(stage, index) in monthly.stages" :key="stage.label" class="grid min-w-0 grid-cols-[92px_minmax(0,1fr)_76px] items-center gap-2">
                    <span class="line-clamp-2 text-[9px] leading-3 text-foreground">{{ stage.label }}</span>
                    <div class="min-w-0 h-2 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full" :style="{ width: `${stage.progress_percentage}%`, backgroundColor: stageColor(index) }" /></div>
                    <span class="line-clamp-2 text-right text-[8px] leading-3 font-medium" :style="{ color: stageColor(index) }">{{ stage.value_label }}</span>
                </div>
            </div>
        </article>
    </section>
</template>
