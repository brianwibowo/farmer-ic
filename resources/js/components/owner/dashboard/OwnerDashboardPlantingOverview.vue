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
    total.value > 0
        ? Math.round((props.cycles.running / total.value) * 100)
        : 0,
);
const ring = computed(() => ({
    background:
        total.value > 0
            ? `conic-gradient(var(--primary) 0 ${runningPercent.value}%, var(--accent) ${runningPercent.value}% 100%)`
            : 'var(--muted)',
}));
const commodityTotal = computed(() =>
    props.commodities.reduce((sum, crop) => sum + crop.areaValue, 0),
);
</script>

<template>
    <section class="min-w-0 space-y-4" aria-label="Ringkasan penanaman">
        <article
            class="rounded-xl border border-border bg-card p-4"
            aria-labelledby="owner-cycle-overview"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 id="owner-cycle-overview" class="text-base font-semibold">
                    Siklus tanam
                </h2>
                <RefreshCw
                    class="size-4 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>
            <div
                v-if="total"
                class="mt-4 flex flex-wrap items-center justify-between gap-4"
            >
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center gap-2">
                        <span
                            class="size-2.5 rounded-full bg-primary"
                            aria-hidden="true"
                        />
                        <dt>Berjalan</dt>
                        <dd class="ml-auto pl-3 font-bold tabular-nums">
                            {{ cycles.running }}
                        </dd>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="size-2.5 rounded-full bg-accent"
                            aria-hidden="true"
                        />
                        <dt>Sudah panen</dt>
                        <dd class="ml-auto pl-3 font-bold tabular-nums">
                            {{ cycles.harvested }}
                        </dd>
                    </div>
                </dl>
                <div
                    class="relative grid size-32 shrink-0 place-items-center rounded-full"
                    :style="ring"
                    role="img"
                    :aria-label="`${cycles.running} batch berjalan dan ${cycles.harvested} batch sudah panen. ${runningPercent}% masih berjalan.`"
                >
                    <div class="absolute inset-3 rounded-full bg-card" />
                    <p class="relative text-center">
                        <strong
                            class="text-[28px] leading-9 font-bold tabular-nums"
                            >{{ runningPercent }}%</strong
                        ><span class="block text-xs text-muted-foreground"
                            >masih berjalan</span
                        >
                    </p>
                </div>
            </div>
            <p
                v-else
                role="status"
                class="mt-4 text-sm leading-6 text-muted-foreground"
            >
                Belum ada siklus tanam.
            </p>
            <p class="mt-4 text-xs leading-5 text-muted-foreground">
                {{ cycles.planned }} persiapan · {{ cycles.active }} aktif.
            </p>
        </article>
        <article
            class="rounded-xl border border-border bg-card p-4"
            aria-labelledby="owner-stage-overview"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 id="owner-stage-overview" class="text-base font-semibold">
                    Tahap penanaman
                </h2>
                <Sprout
                    class="size-4 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>
            <dl v-if="cycles.stages.length" class="mt-4 space-y-4">
                <div v-for="stage in cycles.stages" :key="stage.code">
                    <div
                        class="flex items-start justify-between gap-3 text-sm leading-5"
                    >
                        <dt>{{ stage.label }}</dt>
                        <dd class="shrink-0 font-bold tabular-nums">
                            {{ stage.count }}
                        </dd>
                    </div>
                    <div
                        class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                        aria-hidden="true"
                    >
                        <span
                            class="block h-full rounded-full bg-primary"
                            :style="{
                                width: `${cycles.running > 0 ? Math.min(100, (stage.count / cycles.running) * 100) : 0}%`,
                            }"
                        />
                    </div>
                </div>
            </dl>
            <p
                v-else
                role="status"
                class="mt-4 text-sm leading-6 text-muted-foreground"
            >
                Tahap penanaman belum tercatat.
            </p>
            <p class="mt-4 text-xs leading-5 text-muted-foreground">
                {{
                    cycles.withoutTimeline
                        ? `${cycles.withoutTimeline} batch belum memiliki tahap berjalan yang tercatat.`
                        : 'Berdasarkan tahap yang sedang berlangsung pada batch.'
                }}
            </p>
        </article>
        <article
            class="rounded-xl border border-border bg-card p-4"
            aria-labelledby="owner-crop-overview"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 id="owner-crop-overview" class="text-base font-semibold">
                    Komoditas tertanam
                </h2>
                <Leaf class="size-4 text-muted-foreground" aria-hidden="true" />
            </div>
            <dl v-if="commodities.length" class="mt-4 space-y-4">
                <div v-for="crop in commodities" :key="crop.id">
                    <div
                        class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 text-sm leading-5"
                    >
                        <dt class="min-w-0 break-words">{{ crop.name }}</dt>
                        <dd class="font-semibold tabular-nums">
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
                                width: `${commodityTotal > 0 ? Math.min(100, (crop.areaValue / commodityTotal) * 100) : 0}%`,
                            }"
                        />
                    </div>
                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                        {{ crop.cycleCount }} siklus
                    </p>
                </div>
            </dl>
            <p
                v-else
                role="status"
                class="mt-4 text-sm leading-6 text-muted-foreground"
            >
                Komoditas belum tercatat.
            </p>
            <p class="mt-4 text-xs leading-5 text-muted-foreground">
                Luas tanam pada siklus aktif atau sudah panen.
            </p>
        </article>
    </section>
</template>
