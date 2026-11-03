<script setup lang="ts">
import { Leaf, RefreshCw, Sprout } from '@lucide/vue';
import { computed } from 'vue';
import type { DashboardData } from '@/types/dashboard';
const props = defineProps<{
    cycles: DashboardData['cycles'];
    commodities: DashboardData['commodities'];
}>();
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
        <article class="dashboard-panel">
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
                class="mt-4 flex flex-wrap items-center justify-around gap-4"
            >
                <dl class="space-y-2.5 text-sm">
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
                    class="relative grid size-32 shrink-0 place-items-center rounded-full"
                    :style="ring"
                    role="img"
                    :aria-label="`${cycles.running} batch berjalan, ${cycles.harvested} sudah panen`"
                >
                    <div class="absolute inset-3 rounded-full bg-card" />
                    <p class="relative text-center">
                        <strong class="text-[32px] font-bold tabular-nums"
                            >{{ runningPercent }}%</strong
                        ><span class="mt-1 block text-xs text-muted-foreground"
                            >masih berjalan</span
                        >
                    </p>
                </div>
            </div>
            <p v-else class="py-10 text-center text-sm text-muted-foreground">
                Belum ada siklus tanam.
            </p>
            <p class="mt-4 text-xs leading-5 text-muted-foreground">
                {{ cycles.planned }} persiapan · {{ cycles.active }} aktif.
            </p>
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
                    <div class="flex justify-between gap-3 text-sm">
                        <dt>{{ stage.label }}</dt>
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
