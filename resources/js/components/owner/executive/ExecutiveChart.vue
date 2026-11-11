<script setup lang="ts">
import { Chart, registerables } from 'chart.js';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { executiveNumber } from '@/services/ownerExecutiveDemoService';

Chart.register(...registerables);
const props = withDefaults(
    defineProps<{
        title: string;
        labels: string[];
        series: {
            label: string;
            values: number[];
            colorTokens?: string[];
        }[];
        kind?: 'horizontal' | 'grouped';
        stacked?: boolean;
        unit?: string;
        details?: string[];
        interactive?: boolean;
        compact?: boolean;
        minWidth?: number;
        formatValue?: (value: number) => string;
        categoryGroups?: { label: string; count: number }[];
        showLegend?: boolean;
    }>(),
    {
        kind: 'grouped',
        stacked: false,
        unit: '',
        details: () => [],
        interactive: false,
        compact: false,
        minWidth: 0,
        categoryGroups: () => [],
        showLegend: true,
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
        plugins: [
            {
                id: 'executiveCategoryGroups',
                beforeDatasetsDraw(chart) {
                    if (props.categoryGroups.length < 2) return;
                    const axis = chart.scales.x;
                    const { ctx, chartArea } = chart;
                    const step = axis.width / Math.max(props.labels.length, 1);
                    let start = 0;
                    ctx.save();
                    props.categoryGroups.forEach((group, index) => {
                        const left = axis.getPixelForValue(start) - step / 2;
                        const right =
                            axis.getPixelForValue(start + group.count - 1) +
                            step / 2;
                        if (index % 2 === 0) {
                            ctx.globalAlpha = 0.45;
                            ctx.fillStyle = style
                                .getPropertyValue('--muted')
                                .trim();
                            ctx.fillRect(
                                left,
                                chartArea.top,
                                right - left,
                                chartArea.bottom - chartArea.top,
                            );
                        }
                        if (index < props.categoryGroups.length - 1) {
                            ctx.globalAlpha = 0.45;
                            ctx.strokeStyle = muted;
                            ctx.lineWidth = 1.5;
                            ctx.setLineDash([4, 4]);
                            ctx.beginPath();
                            ctx.moveTo(right, chartArea.top);
                            ctx.lineTo(right, chartArea.bottom);
                            ctx.stroke();
                        }
                        start += group.count;
                    });
                    ctx.restore();
                },
                afterDraw(chart) {
                    if (!props.categoryGroups.length) return;
                    const axis = chart.scales.x;
                    const { ctx } = chart;
                    const step = axis.width / Math.max(props.labels.length, 1);
                    let start = 0;
                    ctx.save();
                    ctx.font = `600 12px ${style.fontFamily}`;
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'top';
                    for (const group of props.categoryGroups) {
                        const left = axis.getPixelForValue(start) - step / 2;
                        const right =
                            axis.getPixelForValue(start + group.count - 1) +
                            step / 2;
                        const lines = group.label
                            .split(' ')
                            .reduce<string[]>((lines, word) => {
                                const last = lines.length - 1;
                                if (
                                    last >= 0 &&
                                    ctx.measureText(`${lines[last]} ${word}`)
                                        .width <
                                        right - left - 12
                                )
                                    lines[last] += ` ${word}`;
                                else lines.push(word);
                                return lines;
                            }, []);
                        ctx.strokeStyle = muted;
                        ctx.lineWidth = 1.5;
                        ctx.beginPath();
                        ctx.moveTo(left + 4, axis.bottom + 2);
                        ctx.lineTo(right - 4, axis.bottom + 2);
                        ctx.stroke();
                        ctx.fillStyle = foreground;
                        lines.forEach((line, index) =>
                            ctx.fillText(
                                line,
                                (left + right) / 2,
                                axis.bottom + 10 + index * 15,
                            ),
                        );
                        start += group.count;
                    }
                    ctx.restore();
                },
            },
        ],
        data: {
            labels: props.labels,
            datasets: props.series.map((series, index) => ({
                label: series.label,
                data: series.values,
                backgroundColor:
                    series.colorTokens?.map((token) =>
                        style.getPropertyValue(token).trim(),
                    ) ?? colors.value[index % colors.value.length],
                borderColor:
                    series.colorTokens?.map((token) =>
                        style.getPropertyValue(token).trim(),
                    ) ?? colors.value[index % colors.value.length],
                borderWidth: 0,
                borderSkipped: false,
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
            layout: {
                padding: {
                    bottom: props.categoryGroups.length
                        ? props.categoryGroups.length > 1 &&
                          props.categoryGroups.some(
                              (group) => group.count === 1,
                          )
                            ? 64
                            : 44
                        : 0,
                },
            },
            onClick: (_event, elements) => {
                if (props.interactive && elements[0])
                    emit('select', elements[0].index);
            },
            onHover: (_event, elements) => {
                if (canvas.value)
                    canvas.value.style.cursor =
                        props.interactive && elements.length
                            ? 'pointer'
                            : 'default';
            },
            plugins: {
                legend: {
                    display: props.showLegend && !props.minWidth,
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
                            return `${context.dataset.label}: ${props.formatValue ? props.formatValue(value) : `${executiveNumber(value)} ${props.unit}`}`;
                        },
                        afterBody: (items) =>
                            props.details[items[0]?.dataIndex] ?? '',
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
                    ticks: {
                        color: muted,
                        maxRotation: 0,
                        autoSkip: !props.minWidth,
                    },
                },
                y: {
                    stacked: props.stacked,
                    beginAtZero: true,
                    grid: {
                        color: border,
                        display: props.kind !== 'horizontal',
                    },
                    ticks: {
                        color: muted,
                        callback: (value) =>
                            typeof value === 'number'
                                ? executiveNumber(value)
                                : value,
                    },
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
        props.minWidth,
        props.categoryGroups,
        props.formatValue,
        props.showLegend,
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
        <ul
            v-if="showLegend && minWidth"
            class="mb-3 flex flex-wrap justify-center gap-x-4 gap-y-2 text-xs"
            aria-label="Legenda grafik"
        >
            <li
                v-for="(item, index) in series"
                :key="item.label"
                class="inline-flex items-center gap-1.5"
            >
                <span
                    class="size-3 rounded-full"
                    :style="{ background: colors[index % colors.length] }"
                    aria-hidden="true"
                />
                {{ item.label }}
            </li>
        </ul>
        <div class="min-w-0 overflow-x-auto">
            <div
                class="relative min-w-0"
                :class="compact ? 'h-40 sm:h-44' : 'h-56 sm:h-64'"
                :style="minWidth ? { minWidth: `${minWidth}px` } : undefined"
            >
                <canvas ref="canvas" role="img" :aria-label="title"
                    ><span>{{ title }}</span></canvas
                >
            </div>
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
