<script setup lang="ts">
import { Chart, registerables } from 'chart.js';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { executiveNumber } from '@/services/ownerExecutiveSummary';

Chart.register(...registerables);
const props = withDefaults(
    defineProps<{
        title: string;
        labels: string[];
        series: {
            label: string;
            values: (number | null)[];
            colorTokens?: string[];
            borderColorTokens?: string[];
            borderWidth?: number;
            hidden?: boolean;
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
const plotInsets = ref({ left: 0, right: 0 });

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
                afterLayout(chart) {
                    plotInsets.value = {
                        left: chart.chartArea.left,
                        right: chart.width - chart.chartArea.right,
                    };
                },
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
            },
        ],
        data: {
            labels: props.labels,
            datasets: props.series.map((series, index) => ({
                label: series.label,
                hidden: series.hidden ?? false,
                data: series.values,
                backgroundColor:
                    series.colorTokens?.map((token) =>
                        style.getPropertyValue(token).trim(),
                    ) ?? colors.value[index % colors.value.length],
                borderColor:
                    (series.borderColorTokens ?? series.colorTokens)?.map(
                        (token) => style.getPropertyValue(token).trim(),
                    ) ?? colors.value[index % colors.value.length],
                borderWidth: series.borderWidth ?? 0,
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
                        title: (items) =>
                            props.labels[items[0]?.dataIndex] ?? '',
                        label: (context) => {
                            if (context.raw === null)
                                return `${context.dataset.label}: Belum tersedia`;
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
                        callback:
                            props.categoryGroups.length &&
                            props.kind !== 'horizontal'
                                ? function (value) {
                                      const label =
                                          props.labels[Number(value)] ?? '';
                                      const { ctx } = this.chart;
                                      const maxWidth = Math.max(
                                          24,
                                          (this.chart.width - 64) /
                                              Math.max(props.labels.length, 1) -
                                              16,
                                      );
                                      ctx.save();
                                      ctx.font = `${Chart.defaults.font.size}px ${Chart.defaults.font.family}`;
                                      const lines = [''];
                                      for (const word of label.split(/\s+/u)) {
                                          const last = lines.length - 1;
                                          const candidate =
                                              `${lines[last]} ${word}`.trim();
                                          if (
                                              ctx.measureText(candidate)
                                                  .width <= maxWidth
                                          ) {
                                              lines[last] = candidate;
                                              continue;
                                          }
                                          if (lines[last]) lines.push('');
                                          for (const character of word) {
                                              const index = lines.length - 1;
                                              if (
                                                  lines[index] &&
                                                  ctx.measureText(
                                                      lines[index] + character,
                                                  ).width > maxWidth
                                              )
                                                  lines.push(character);
                                              else lines[index] += character;
                                          }
                                      }
                                      ctx.restore();
                                      return lines;
                                  }
                                : undefined,
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
        <div
            class="min-w-0 overflow-x-auto rounded-sm pb-3 focus-visible:outline-2 focus-visible:outline-ring"
            :tabindex="minWidth ? 0 : undefined"
            :role="minWidth ? 'region' : undefined"
            :aria-label="
                minWidth
                    ? `${title}. Geser untuk melihat semua kelompok.`
                    : undefined
            "
        >
            <div
                class="min-w-0"
                :style="minWidth ? { minWidth: `${minWidth}px` } : undefined"
            >
                <div
                    class="relative min-w-0"
                    :class="compact ? 'h-40 sm:h-44' : 'h-56 sm:h-64'"
                >
                    <canvas ref="canvas" role="img" :aria-label="title"
                        ><span>{{ title }}</span></canvas
                    >
                </div>
                <div
                    v-if="categoryGroups.length"
                    class="grid pt-2 text-center text-xs leading-5 font-semibold text-foreground"
                    :style="{
                        gridTemplateColumns: `repeat(${labels.length}, minmax(0, 1fr))`,
                        paddingLeft: `${plotInsets.left}px`,
                        paddingRight: `${plotInsets.right}px`,
                    }"
                >
                    <div
                        v-for="(group, index) in categoryGroups"
                        :key="index"
                        class="min-w-0 px-2"
                        :style="{ gridColumn: `span ${group.count}` }"
                    >
                        <div
                            class="border-t border-border px-2 pt-2 [overflow-wrap:anywhere]"
                        >
                            {{ group.label }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <p
            v-if="
                !series.some((series) =>
                    series.values.some(
                        (value) => value !== null && value !== 0,
                    ),
                )
            "
            class="py-2 text-center text-sm text-muted-foreground"
        >
            Belum ada nilai yang dapat ditampilkan.
        </p>
    </div>
</template>
