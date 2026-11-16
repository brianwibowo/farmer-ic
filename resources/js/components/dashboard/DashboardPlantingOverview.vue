<script setup lang="ts">
import { Leaf, RefreshCw, Sprout } from '@lucide/vue';
import { computed } from 'vue';
import MetricInfoTooltip from '@/components/MetricInfoTooltip.vue';
import type { DashboardData } from '@/types/dashboard';
const props = defineProps<{
    cycles: DashboardData['cycles'];
    commodities: DashboardData['commodities'];
}>();
const stageDescriptions: Record<string, string> = {
    soil_preparation:
        'Membersihkan dan mengolah lahan agar siap untuk penanaman.',
    basic_fertilizer:
        'Memberikan pupuk awal sebelum atau saat tanam untuk mendukung pertumbuhan awal tanaman.',
    planting:
        'Menanam benih atau bibit pada lahan sesuai komoditas dan rencana tanam.',
    maintenance:
        'Merawat tanaman setelah tanam, misalnya melalui pengairan, penyiangan, pemupukan susulan, dan pemantauan gangguan.',
    harvest: 'Mengambil hasil tanaman saat mencapai waktu atau kriteria panen.',
};
const total = computed(() => props.cycles.running + props.cycles.harvested);
const runningPercent = computed(() =>
    total.value ? Math.round((props.cycles.running / total.value) * 100) : 0,
);
const commodityTotal = computed(() =>
    props.commodities.reduce((sum, crop) => sum + crop.areaValue, 0),
);
const ring = computed(() => ({
    background: total.value
        ? `conic-gradient(var(--primary) 0 ${runningPercent.value}%, var(--admin-chart-secondary) ${runningPercent.value}% 100%)`
        : 'var(--muted)',
}));
</script>

<template>
    <section
        class="dashboard-planting-overview grid gap-[18px] lg:grid-cols-3"
        aria-label="Ringkasan penanaman"
    >
        <article class="dashboard-panel flex h-full flex-col">
            <div class="flex items-center justify-between gap-2">
                <h2 class="admin-section-title">Siklus tanam</h2>
                <RefreshCw
                    :size="17"
                    class="text-muted-foreground"
                    aria-hidden="true"
                />
            </div>
            <div
                v-if="total"
                class="mt-5 flex flex-1 flex-col items-center justify-center gap-5 sm:flex-row sm:justify-center sm:gap-7 xl:justify-between xl:gap-4"
            >
                <dl
                    class="w-full max-w-48 space-y-4 text-sm sm:w-auto sm:min-w-36"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="size-2 rounded-full bg-primary"
                            aria-hidden="true"
                        />
                        <dt>Berjalan</dt>
                        <dd class="ml-auto font-bold tabular-nums">
                            {{ cycles.running }}
                        </dd>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="dashboard-series-1 size-2 rounded-full"
                            aria-hidden="true"
                        />
                        <dt>Sudah panen</dt>
                        <dd class="ml-auto font-bold tabular-nums">
                            {{ cycles.harvested }}
                        </dd>
                    </div>
                </dl>
                <div
                    class="relative grid size-36 shrink-0 place-items-center rounded-full xl:size-40"
                    :style="ring"
                    role="img"
                    :aria-label="`${cycles.running} batch berjalan, ${cycles.harvested} sudah panen`"
                >
                    <div
                        class="absolute inset-3.5 rounded-full bg-card xl:inset-4"
                    />
                    <p class="relative text-center">
                        <strong
                            class="text-[34px] font-bold tabular-nums xl:text-[38px]"
                            >{{ runningPercent }}%</strong
                        ><span class="mt-1 block text-xs text-muted-foreground"
                            >masih berjalan</span
                        >
                    </p>
                </div>
            </div>
            <p
                v-else
                class="flex flex-1 items-center justify-center py-10 text-center text-sm text-muted-foreground"
            >
                Belum ada siklus tanam.
            </p>
            <dl
                class="mt-auto grid grid-cols-2 gap-3 border-t border-border/70 pt-4"
            >
                <div>
                    <dt class="text-xs text-muted-foreground">Persiapan</dt>
                    <dd
                        class="mt-1 text-lg font-bold text-foreground tabular-nums"
                    >
                        {{ cycles.planned }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Aktif</dt>
                    <dd
                        class="mt-1 text-lg font-bold text-foreground tabular-nums"
                    >
                        {{ cycles.active }}
                    </dd>
                </div>
            </dl>
        </article>
        <article class="dashboard-panel">
            <div class="flex items-center justify-between gap-2">
                <h2 class="admin-section-title">Tahap penanaman</h2>
                <Sprout
                    :size="18"
                    class="text-muted-foreground"
                    aria-hidden="true"
                />
            </div>
            <dl class="mt-4 space-y-3">
                <div v-for="stage in cycles.stages" :key="stage.code">
                    <div
                        class="flex items-center justify-between gap-3 text-sm"
                    >
                        <dt class="flex min-w-0 items-center gap-1">
                            <span>{{ stage.label }}</span>
                            <MetricInfoTooltip
                                :label="stage.label"
                                :description="
                                    stageDescriptions[stage.code] ??
                                    'Tahap dalam siklus penanaman.'
                                "
                                touch-target
                            />
                        </dt>
                        <dd class="font-bold tabular-nums">
                            {{ stage.count }}
                        </dd>
                    </div>
                    <div
                        class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted"
                        aria-hidden="true"
                    >
                        <span
                            class="block h-full rounded-full bg-primary"
                            :style="{
                                width: `${cycles.running ? (stage.count / cycles.running) * 100 : 0}%`,
                            }"
                        />
                    </div>
                </div>
            </dl>
            <p class="mt-4 text-xs leading-5 text-muted-foreground">
                {{
                    cycles.withoutTimeline
                        ? `${cycles.withoutTimeline} batch belum memiliki tahap berjalan yang tercatat.`
                        : 'Berdasarkan tahap yang sedang berlangsung pada batch.'
                }}
            </p>
            <p class="mt-1 text-xs leading-5 text-muted-foreground">
                Pembersihan lahan dicatat pada tingkat blok.
            </p>
        </article>
        <article class="dashboard-panel">
            <div class="flex items-center justify-between gap-2">
                <h2 class="admin-section-title">Komoditas tertanam</h2>
                <Leaf
                    :size="18"
                    class="text-muted-foreground"
                    aria-hidden="true"
                />
            </div>
            <dl v-if="commodities.length" class="mt-4 space-y-5">
                <div v-for="crop in commodities" :key="crop.id">
                    <div
                        class="flex items-baseline justify-between gap-3 text-sm"
                    >
                        <dt>{{ crop.name }}</dt>
                        <dd class="shrink-0 text-sm font-bold text-foreground">
                            {{ crop.plantedArea }} ha
                        </dd>
                    </div>
                    <div
                        class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                        aria-hidden="true"
                    >
                        <span
                            class="block h-full rounded-full bg-primary"
                            :style="{
                                width: `${commodityTotal ? (crop.areaValue / commodityTotal) * 100 : 0}%`,
                            }"
                        />
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ crop.cycleCount }} siklus
                    </p>
                </div>
            </dl>
            <p v-else class="py-10 text-center text-sm text-muted-foreground">
                Komoditas belum tercatat.
            </p>
            <p class="mt-4 text-xs leading-5 text-muted-foreground">
                Luas tanam pada siklus aktif atau sudah panen.
            </p>
        </article>
    </section>
</template>
