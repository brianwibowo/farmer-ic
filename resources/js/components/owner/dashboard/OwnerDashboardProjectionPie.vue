<script setup lang="ts">
import { computed, useId } from 'vue';

type Projection = {
    total_ton: number;
    has_data: boolean;
    commodities: Array<{ id: string; name: string; ton: number }>;
};

const props = defineProps<{ projection: Projection }>();
const titleId = useId();
const tonFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 });
// The compact projection chart deliberately has a distinct, high-contrast
// categorical palette from the green land/commodity dashboards.
const colors = [
    'var(--owner-projection-chart-red)',
    'var(--owner-projection-chart-blue)',
    'var(--owner-projection-chart-orange)',
];

function point(radius: number, angle: number) {
    const radians = ((angle - 90) * Math.PI) / 180;

    return {
        x: 50 + radius * Math.cos(radians),
        y: 50 + radius * Math.sin(radians),
    };
}

function sector(start: number, end: number): string {
    // A single commodity is a full circle; SVG arcs cannot use identical ends.
    if (end - start >= 359.999)
        return 'M 50 50 L 50 6 A 44 44 0 1 1 49.999 6 Z';

    const outerStart = point(44, start);
    const outerEnd = point(44, end);
    const large = end - start > 180 ? 1 : 0;

    return `M 50 50 L ${outerStart.x} ${outerStart.y} A 44 44 0 ${large} 1 ${outerEnd.x} ${outerEnd.y} Z`;
}

const slices = computed(() => {
    const crops = props.projection.commodities.filter((crop) => crop.ton > 0);
    const total = crops.reduce((sum, crop) => sum + crop.ton, 0);
    if (!props.projection.has_data || total <= 0) return [];

    let angle = 0;

    return crops.map((crop, index) => {
        const end =
            index === crops.length - 1 ? 360 : angle + (crop.ton / total) * 360;
        const slice = {
            ...crop,
            color: colors[index % colors.length],
            path: sector(angle, end),
        };
        angle = end;

        return slice;
    });
});

const description = computed(() => {
    if (!slices.value.length)
        return 'Belum ada proyeksi panen aktif yang dapat dihitung.';

    return `Total proyeksi ${tonFormat.format(props.projection.total_ton)} ton: ${slices.value.map((slice) => `${slice.name} ${tonFormat.format(slice.ton)} ton`).join(', ')}.`;
});
</script>

<template>
    <div
        class="flex min-w-[132px] items-center gap-2"
        :aria-labelledby="titleId"
    >
        <svg
            v-if="slices.length"
            class="size-14 shrink-0"
            viewBox="0 0 100 100"
            role="img"
            :aria-label="description"
        >
            <path
                v-for="slice in slices"
                :key="slice.id"
                :d="slice.path"
                :fill="slice.color"
            >
                <title>
                    {{ slice.name }}: {{ tonFormat.format(slice.ton) }} ton
                </title>
            </path>
        </svg>
        <div
            v-else
            class="flex size-14 shrink-0 items-center justify-center rounded-full bg-muted text-xs text-muted-foreground"
            aria-hidden="true"
        >
            —
        </div>
        <div class="min-w-0">
            <p :id="titleId" class="font-medium tabular-nums">
                {{
                    projection.has_data
                        ? `${tonFormat.format(projection.total_ton)} ton`
                        : 'Belum tersedia'
                }}
            </p>
            <p class="mt-0.5 text-xs leading-4 text-muted-foreground">
                Proyeksi panen aktif
            </p>
        </div>
    </div>
</template>
