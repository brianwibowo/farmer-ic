<script setup lang="ts">
import { CheckCircle2, ClipboardList, Sprout } from '@lucide/vue';
import { computed } from 'vue';
import type { SopCommodity, SopSummary } from '@/types/sop';

const props = defineProps<{
    total: number;
    active: number;
    commodities: SopCommodity[];
    summary?: SopSummary;
}>();

const number = new Intl.NumberFormat('id-ID');

const totalTemplates = computed(() => props.summary?.total ?? props.total);
const activeTemplates = computed(() => props.summary?.active ?? props.active);
const inactiveTemplates = computed(() =>
    Math.max(0, totalTemplates.value - activeTemplates.value),
);

const activeRatio = computed(() =>
    totalTemplates.value > 0
        ? Math.min(1, activeTemplates.value / totalTemplates.value)
        : 0,
);
const activePercent = computed(() => Math.round(activeRatio.value * 100));

const gaugeStyle = computed(() => ({
    background: `conic-gradient(from -90deg at 50% 100%, var(--primary) 0 ${activeRatio.value * 180}deg, var(--admin-chart-tertiary) ${activeRatio.value * 180}deg 180deg, transparent 180deg)`,
}));

const maxBar = computed(() =>
    Math.max(1, totalTemplates.value, activeTemplates.value, inactiveTemplates.value),
);

const previewCommodities = computed(() => props.commodities.slice(0, 2));
const remainingCommodities = computed(() =>
    Math.max(0, props.commodities.length - previewCommodities.value.length),
);
</script>

<template>
    <section
        class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4"
        aria-label="Ringkasan template SOP"
    >
        <!-- 1. Total template + comparison mini bars -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Total template</h2>
                <ClipboardList
                    class="size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] items-end justify-between gap-3">
                <p class="admin-metric-value tracking-tight">
                    {{ number.format(totalTemplates) }}
                </p>
                <div
                    class="flex h-9 w-[54px] shrink-0 items-end gap-1.5"
                    aria-hidden="true"
                >
                    <span
                        class="w-3.5 rounded-t bg-primary transition-[height]"
                        :style="{
                            height: `${Math.max(5, (totalTemplates / maxBar) * 36)}px`,
                        }"
                    />
                    <span
                        class="w-3.5 rounded-t bg-[var(--admin-chart-secondary)] transition-[height]"
                        :style="{
                            height: `${Math.max(5, (activeTemplates / maxBar) * 36)}px`,
                        }"
                    />
                    <span
                        class="w-3.5 rounded-t bg-[var(--admin-chart-tertiary)] transition-[height]"
                        :style="{
                            height: `${Math.max(5, (inactiveTemplates / maxBar) * 36)}px`,
                        }"
                    />
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                {{ number.format(activeTemplates) }} aktif ·
                {{ number.format(inactiveTemplates) }} nonaktif
            </p>
        </article>

        <!-- 2. Template aktif + semi-circle completion arc gauge -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Template aktif</h2>
                <CheckCircle2
                    class="size-4 shrink-0 text-success"
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] items-end justify-between gap-3">
                <p class="admin-metric-value tracking-tight">
                    {{ number.format(activeTemplates) }}
                </p>
                <div
                    class="relative h-[34px] w-[62px] shrink-0 rounded-t-full"
                    :style="gaugeStyle"
                    aria-hidden="true"
                >
                    <span
                        class="absolute inset-x-[5px] top-[5px] bottom-0 rounded-t-full bg-card"
                    />
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                <strong class="font-semibold text-primary"
                    >{{ activePercent }}%</strong
                >
                dari total template berstatus aktif
            </p>
        </article>

        <!-- 3. Komoditas terdaftar + preview tags -->
        <article
            class="admin-panel flex flex-col justify-between gap-3 rounded-xl bg-card"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Komoditas terdaftar</h2>
                <Sprout
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
            </div>

            <div class="flex min-h-[2.75rem] flex-wrap items-end justify-between gap-3">
                <p class="admin-metric-value tracking-tight">
                    {{ number.format(commodities.length) }}
                </p>
                <div
                    v-if="commodities.length"
                    class="flex min-w-0 flex-wrap items-center justify-end gap-1.5"
                >
                    <span
                        v-for="commodity in previewCommodities"
                        :key="commodity.id"
                        class="rounded-full bg-accent px-2.5 py-1 text-xs font-medium text-accent-foreground"
                    >
                        {{ commodity.name }}
                    </span>
                    <span
                        v-if="remainingCommodities > 0"
                        class="rounded-full bg-muted px-2 py-1 text-xs font-semibold tabular-nums text-muted-foreground"
                    >
                        +{{ remainingCommodities }}
                    </span>
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                {{
                    commodities.length
                        ? 'Komoditas tersedia untuk template SOP'
                        : 'Belum ada komoditas terdaftar'
                }}
            </p>
        </article>
    </section>
</template>
