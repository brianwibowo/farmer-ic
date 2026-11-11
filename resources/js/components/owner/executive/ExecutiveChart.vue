<script setup lang="ts">
import { Chart, registerables } from 'chart.js';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { executiveNumber } from '@/services/ownerExecutiveDemoService';

Chart.register(...registerables);
const props = withDefaults(
    defineProps<{
        title: string;
        labels: string[];
        series: { label: string; values: number[] }[];
        kind?: 'horizontal' | 'grouped';
        stacked?: boolean;
        unit?: string;
        details?: string[];
        interactive?: boolean;
        compact?: boolean;
    }>(),
    {
        kind: 'grouped',
        stacked: false,
        unit: '',
        details: () => [],
        interactive: false,
        compact: false,
    },
);
const emit = defineEmits<{ select: [index: number] }>();
const canvas = ref<HTMLCanvasElement>();
let chart: Chart | undefined;
let observer: MutationObserver | undefined;
let disposed = false;
const colors = ref<string[]>([]);

async function draw() {
    await nextTick();
    if (!canvas.value || disposed) return;
    chart?.destroy();
    const style = getComputedStyle(canvas.value);
    const foreground = style.getPropertyValue('--foreground').trim();
    const muted = style.getPropertyValue('--muted-foreground').trim();
    const border = style.getPropertyValue('--border').trim();
    colors.value = [2, 1, 5, 3, 4].map((index) =>
        style.getPropertyValue(`--chart-${index}`).trim(),
    );
    chart = new Chart(canvas.value, {
        type: 'bar',
        data: {
            labels: props.labels,
            datasets: props.series.map((series, index) => ({
                label: series.label,
                data: series.values,
                backgroundColor: colors.value[index % colors.value.length],
                borderWidth: 0,
                borderRadius: 4,
                maxBarThickness: 30,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: matchMedia('(prefers-reduced-motion: reduce)').matches
                ? false
                : { duration: 250 },
            indexAxis: props.kind === 'horizontal' ? 'y' : 'x',
            onClick: (_event, elements) => {
                if (props.interactive && elements[0])
                    emit('select', elements[0].index);
            },
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: foreground,
                        usePointStyle: true,
                        boxWidth: 8,
                        padding: 18,
                    },
                },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const value = Number(context.raw);
                            return `${context.dataset.label}: ${executiveNumber(value)} ${props.unit}`;
                        },
                        afterLabel: (context) =>
                            props.details[context.dataIndex] ?? '',
                    },
                },
            },
            scales: {
                x: {
                    stacked: props.stacked,
                    beginAtZero: true,
                    grid: {
                        color: border,
                        display: props.kind === 'horizontal',
                    },
                    ticks: { color: muted, maxRotation: 0, autoSkip: true },
                },
                y: {
                    stacked: props.stacked,
                    beginAtZero: true,
                    grid: {
                        color: border,
                        display: props.kind !== 'horizontal',
                    },
                    ticks: { color: muted },
                },
            },
        },
    });
}
onMounted(() => {
    void draw();
    observer = new MutationObserver(() => void draw());
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class', 'style'],
    });
});
watch(
    () => [
        props.labels,
        props.series,
        props.kind,
        props.unit,
        props.details,
        props.stacked,
        props.compact,
    ],
    () => void draw(),
    { deep: true },
);
onBeforeUnmount(() => {
    disposed = true;
    observer?.disconnect();
    chart?.destroy();
});
</script>

<template>
    <div class="min-w-0">
        <div
            class="relative min-w-0"
            :class="compact ? 'h-40 sm:h-44' : 'h-56 sm:h-64'"
        >
            <canvas ref="canvas" role="img" :aria-label="title"
                ><span>{{ title }}</span></canvas
            >
        </div>
        <p
            v-if="
                !series.some((series) =>
                    series.values.some((value) => value !== 0),
                )
            "
            class="py-2 text-center text-sm text-muted-foreground"
        >
            Belum ada nilai yang dapat ditampilkan.
        </p>
    </div>
</template>
