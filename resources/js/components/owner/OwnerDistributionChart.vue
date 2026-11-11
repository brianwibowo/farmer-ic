<script setup lang="ts">
import { Check, Leaf, RotateCcw } from '@lucide/vue';
import { computed, ref, useId, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        items: Array<{
            id: string;
            name: string;
            value: number;
            detail?: string;
        }>;
        title?: string;
        description?: string;
        unit?: string;
        totalLabel?: string;
        categoryLabel?: string;
        embedded?: boolean;
        compact?: boolean;
        showFooter?: boolean;
        showLegend?: boolean;
    }>(),
    {
        title: 'Distribusi',
        description: '',
        unit: 'Ha',
        totalLabel: 'Total',
        categoryLabel: 'kategori',
        embedded: false,
        compact: false,
        showFooter: true,
        showLegend: true,
    },
);
const selectedId = defineModel<string | null>('selected', { default: null });
const headingId = useId();
const previewId = ref<string | null>(null);
const areaFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 });
const percentFormat = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 1,
});
const colors = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-5)',
    'var(--chart-4)',
];
const crops = computed(() =>
    props.items
        .map((crop, index) => ({
            ...crop,
            color: colors[index % colors.length],
        }))
        .filter((crop) => Number.isFinite(crop.value) && crop.value > 0),
);
const total = computed(() =>
    crops.value.reduce((sum, crop) => sum + crop.value, 0),
);

function percent(value: number): string {
    return value < 0.1 ? '<0,1%' : `${percentFormat.format(value)}%`;
}

function point(radius: number, angle: number) {
    const radians = ((angle - 90) * Math.PI) / 180;
    return {
        x: 140 + radius * Math.cos(radians),
        y: 140 + radius * Math.sin(radians),
    };
}

function sector(start: number, end: number): string {
    const outerStart = point(124, start);
    const outerEnd = point(124, end);
    const innerEnd = point(88, end);
    const innerStart = point(88, start);
    const large = end - start > 180 ? 1 : 0;
    return `M ${outerStart.x} ${outerStart.y} A 124 124 0 ${large} 1 ${outerEnd.x} ${outerEnd.y} L ${innerEnd.x} ${innerEnd.y} A 88 88 0 ${large} 0 ${innerStart.x} ${innerStart.y} Z`;
}

const slices = computed(() => {
    if (!Number.isFinite(total.value) || total.value <= 0) return [];
    let angle = 0;
    return crops.value.map((crop, index) => {
        const share = (crop.value / total.value) * 100;
        const end =
            index === crops.value.length - 1 ? 360 : angle + share * 3.6;
        const midpoint = (angle + end) / 2;
        const offset = point(3, midpoint);
        const slice = {
            ...crop,
            valueLabel: areaFormat.format(crop.value),
            percentage: percent(share),
            share,
            color: crop.color,
            path: sector(angle, end),
            label: point(106, midpoint),
            offset: `translate(${offset.x - 140} ${offset.y - 140})`,
        };
        angle = end;
        return slice;
    });
});
const activeId = computed(() => previewId.value ?? selectedId.value);
const activeSlice = computed(() =>
    slices.value.find((crop) => crop.id === activeId.value),
);
const selectedSlice = computed(() =>
    slices.value.find((crop) => crop.id === selectedId.value),
);
const largest = computed(() =>
    slices.value.reduce<(typeof slices.value)[number] | undefined>(
        (current, crop) =>
            !current || crop.value > current.value ? crop : current,
        undefined,
    ),
);
const chartDescription = computed(
    () =>
        `Total ${props.totalLabel.toLocaleLowerCase('id-ID')} ${areaFormat.format(total.value)} ${props.unit}. ${slices.value.map((crop) => `${crop.name}, ${crop.valueLabel} ${props.unit}, ${crop.percentage}`).join('; ')}.`,
);
const selectionAnnouncement = computed(() =>
    selectedSlice.value
        ? `${selectedSlice.value.name} dipilih: ${selectedSlice.value.valueLabel} ${props.unit}, ${selectedSlice.value.percentage} dari total.`
        : `Menampilkan ${props.totalLabel.toLocaleLowerCase('id-ID')} semua ${props.categoryLabel}.`,
);

watch(slices, (items) => {
    // A shared legend can select a crop with zero value in one of its diagrams.
    const selectable = props.showLegend ? items : props.items;
    if (!selectable.some((crop) => crop.id === selectedId.value))
        selectedId.value = null;
    if (!items.some((crop) => crop.id === previewId.value))
        previewId.value = null;
});

function toggleSelection(id: string): void {
    selectedId.value = selectedId.value === id ? null : id;
    previewId.value = null;
}

function preview(id: string, event: PointerEvent): void {
    if (event.pointerType === 'mouse') previewId.value = id;
}

function clearSelection(): void {
    selectedId.value = null;
    previewId.value = null;
}
</script>

<template>
    <section
        class="owner-commodity-card flex min-w-0 flex-col"
        :class="[
            embedded
                ? 'owner-distribution-embedded'
                : 'h-full rounded-xl border border-border bg-card p-4 lg:p-5',
            compact ? 'owner-distribution-compact' : '',
            !showLegend ? 'owner-distribution-plot-only' : '',
        ]"
        :aria-labelledby="headingId"
    >
        <header
            :class="
                embedded ? 'sr-only' : 'flex items-start justify-between gap-3'
            "
        >
            <div>
                <h2 :id="headingId" class="text-base font-semibold 2xl:text-lg">
                    {{ title }}
                </h2>
                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                    {{ description }}
                </p>
            </div>
            <span
                v-if="slices.length"
                class="shrink-0 rounded-full bg-muted px-2.5 py-1 text-xs leading-5 font-medium text-muted-foreground"
                >{{ slices.length }} {{ categoryLabel }}</span
            >
            <Leaf
                v-else
                class="mt-1 size-4 text-muted-foreground"
                aria-hidden="true"
            />
        </header>
        <template v-if="slices.length">
            <p class="sr-only">{{ chartDescription }}</p>
            <div class="owner-commodity-body">
                <div class="owner-commodity-plot">
                    <svg
                        viewBox="0 0 280 280"
                        class="size-full overflow-visible"
                        aria-hidden="true"
                    >
                        <circle
                            cx="140"
                            cy="140"
                            r="106"
                            fill="none"
                            stroke="var(--muted)"
                            stroke-width="36"
                        />
                        <g
                            v-for="crop in slices"
                            :key="crop.id"
                            :transform="
                                selectedId === crop.id ? crop.offset : undefined
                            "
                            :class="
                                activeId && activeId !== crop.id
                                    ? 'opacity-30'
                                    : 'opacity-100'
                            "
                            class="cursor-pointer motion-safe:transition-opacity"
                            @click="toggleSelection(crop.id)"
                            @pointerenter="preview(crop.id, $event)"
                            @pointerleave="previewId = null"
                        >
                            <title>
                                {{ crop.name }}: {{ crop.valueLabel }}
                                {{ unit }} · {{ crop.percentage
                                }}{{ crop.detail ? ` · ${crop.detail}` : '' }}
                            </title>
                            <circle
                                v-if="slices.length === 1"
                                cx="140"
                                cy="140"
                                r="106"
                                fill="none"
                                :stroke="crop.color"
                                stroke-width="36"
                            />
                            <path
                                v-else
                                :d="crop.path"
                                :fill="crop.color"
                                stroke="var(--card)"
                                stroke-width="3"
                                stroke-linejoin="round"
                            />
                            <g
                                v-if="crop.share >= 8"
                                class="pointer-events-none"
                            >
                                <rect
                                    :x="crop.label.x - 23"
                                    :y="crop.label.y - 11"
                                    width="46"
                                    height="22"
                                    rx="11"
                                    fill="var(--card)"
                                    fill-opacity="0.95"
                                />
                                <text
                                    :x="crop.label.x"
                                    :y="crop.label.y"
                                    text-anchor="middle"
                                    dominant-baseline="central"
                                    fill="var(--foreground)"
                                    font-size="13"
                                    font-weight="600"
                                >
                                    {{ crop.percentage }}
                                </text>
                            </g>
                        </g>
                    </svg>
                    <div
                        class="owner-commodity-center pointer-events-none absolute text-center"
                        aria-hidden="true"
                    >
                        <p
                            class="text-xs leading-5 font-medium break-words text-muted-foreground"
                        >
                            {{ activeSlice?.name ?? totalLabel }}
                        </p>
                        <p
                            class="mt-1 text-[30px] leading-9 font-bold tracking-tight tabular-nums"
                        >
                            {{
                                activeSlice?.valueLabel ??
                                areaFormat.format(total)
                            }}<span
                                class="block text-xs leading-4 font-medium tracking-normal text-muted-foreground"
                                >{{ unit }}</span
                            >
                        </p>
                        <p class="mt-1 text-xs leading-5 text-muted-foreground">
                            {{
                                activeSlice
                                    ? `${activeSlice.percentage} dari total`
                                    : `${slices.length} ${categoryLabel}`
                            }}
                        </p>
                    </div>
                </div>
                <ul
                    v-if="showLegend"
                    class="min-w-0 space-y-1.5"
                    :aria-label="`Pilih ${categoryLabel}`"
                >
                    <li v-for="crop in slices" :key="crop.id">
                        <button
                            type="button"
                            class="owner-commodity-choice flex min-h-16 w-full items-center gap-3 rounded-xl border p-2.5 text-left hover:border-primary/40 hover:bg-muted/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-safe:transition-colors"
                            :class="
                                activeId === crop.id
                                    ? 'border-primary/50 bg-primary/10'
                                    : 'border-border/60 bg-muted/10'
                            "
                            :aria-label="`${crop.name}, ${crop.valueLabel} ${props.unit}, ${crop.percentage}. ${crop.detail ?? ''}`"
                            :aria-pressed="selectedId === crop.id"
                            @click="toggleSelection(crop.id)"
                            @pointerenter="preview(crop.id, $event)"
                            @pointerleave="previewId = null"
                            @focus="previewId = crop.id"
                            @blur="previewId = null"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-xl border text-sm font-semibold text-foreground"
                                :style="{
                                    background: `color-mix(in srgb, ${crop.color} 14%, var(--card))`,
                                    borderColor: `color-mix(in srgb, ${crop.color} 35%, var(--border))`,
                                }"
                                aria-hidden="true"
                            >
                                <Check
                                    v-if="selectedId === crop.id"
                                    class="size-4"
                                />
                                <span v-else>{{
                                    crop.name
                                        .charAt(0)
                                        .toLocaleUpperCase('id-ID')
                                }}</span>
                            </span>
                            <slot name="item" :item="crop"
                                ><span class="min-w-0 flex-1 space-y-1.5">
                                    <span
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <span
                                            class="owner-commodity-name min-w-0 text-sm leading-5 font-semibold break-words"
                                            >{{ crop.name }}</span
                                        >
                                        <span
                                            class="shrink-0 rounded-md bg-card px-2 py-0.5 text-xs leading-5 font-semibold tabular-nums ring-1 ring-border/50"
                                            >{{ crop.percentage }}</span
                                        >
                                    </span>
                                    <span class="flex items-center gap-3">
                                        <span
                                            class="shrink-0 text-xs leading-4 text-muted-foreground tabular-nums"
                                            ><span
                                                class="font-medium text-foreground"
                                                >{{ crop.valueLabel }}</span
                                            >
                                            {{ unit }}</span
                                        >
                                        <span
                                            class="h-1 min-w-4 flex-1 overflow-hidden rounded-full bg-muted"
                                            aria-hidden="true"
                                        >
                                            <span
                                                class="block h-full rounded-full"
                                                :style="{
                                                    width: `${crop.share}%`,
                                                    background: crop.color,
                                                }"
                                            />
                                        </span>
                                    </span> </span
                            ></slot>
                        </button>
                    </li>
                </ul>
            </div>
            <div
                v-if="showFooter"
                class="mt-4 flex min-h-11 flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-lg bg-muted/50 px-3 py-1.5 text-xs leading-5"
            >
                <p v-if="largest" class="text-muted-foreground">
                    Terbesar:
                    <span class="font-semibold text-foreground">{{
                        largest.name
                    }}</span>
                    · {{ largest.percentage }}
                </p>
                <button
                    v-if="selectedId"
                    type="button"
                    class="inline-flex min-h-11 items-center gap-1.5 rounded-md font-medium text-secondary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
                    @click="clearSelection"
                >
                    <RotateCcw class="size-3.5" aria-hidden="true" />Tampilkan
                    total
                </button>
            </div>
            <p
                v-if="showFooter"
                class="mt-3 text-xs leading-5 text-muted-foreground"
            >
                Pilih {{ categoryLabel }} untuk menyorot bagiannya pada diagram.
            </p>
            <p
                class="sr-only"
                role="status"
                aria-live="polite"
                aria-atomic="true"
            >
                {{ selectionAnnouncement }}
            </p>
        </template>
        <p
            v-else
            role="status"
            class="flex min-h-48 flex-1 items-center justify-center py-6 text-center text-sm leading-6 text-muted-foreground"
        >
            Belum ada data {{ totalLabel.toLocaleLowerCase('id-ID') }} yang
            tercatat.
        </p>
    </section>
</template>

<style scoped>
.owner-commodity-card {
    container-type: inline-size;
}
.owner-commodity-body {
    display: grid;
    flex: 1;
    align-items: center;
    gap: 16px;
    margin-top: 16px;
}
.owner-commodity-plot {
    position: relative;
    width: min(100%, 248px);
    aspect-ratio: 1;
    margin-inline: auto;
}
.owner-commodity-center {
    inset: 23% 19%;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
@container (min-width: 480px) {
    .owner-commodity-body {
        grid-template-columns: minmax(190px, 1fr) minmax(0, 1.25fr);
        gap: 20px;
    }
}
@container (min-width: 700px) {
    .owner-commodity-plot {
        width: min(100%, 288px);
    }
    .owner-commodity-name {
        font-size: 1rem;
        line-height: 1.5rem;
    }
}
.owner-distribution-embedded .owner-commodity-body {
    margin-top: 0;
    flex: initial;
}
.owner-distribution-compact .owner-commodity-plot {
    width: min(100%, 208px);
}
.owner-distribution-compact .owner-commodity-choice {
    min-height: 60px;
}
@container (min-width: 480px) {
    .owner-distribution-compact .owner-commodity-body {
        grid-template-columns: minmax(170px, 208px) minmax(0, 1fr);
        gap: 24px;
    }
}
.owner-distribution-plot-only .owner-commodity-body {
    grid-template-columns: minmax(0, 1fr);
}
.owner-distribution-plot-only .owner-commodity-center > p:nth-child(2) {
    font-size: 1.5rem;
    line-height: 2rem;
}
</style>
