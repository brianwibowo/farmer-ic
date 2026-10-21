<script setup lang="ts">
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
    type ActiveElement,
    type ChartData,
    type ChartOptions,
    type TooltipItem,
} from 'chart.js';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Line } from 'vue-chartjs';
import type { RevenuePoint } from '@/data/farmerFinance';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    Legend,
);

const props = defineProps<{
    points: RevenuePoint[];
}>();

const emit = defineEmits<{
    pointSelected: [point: RevenuePoint];
}>();

const isDark = ref(false);
let themeObserver: MutationObserver | undefined;

function syncTheme(): void {
    isDark.value = document.documentElement.classList.contains('dark');
}

onMounted(() => {
    syncTheme();
    themeObserver = new MutationObserver(syncTheme);
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
});

onBeforeUnmount(() => themeObserver?.disconnect());

const formatRupiah = (value: number): string =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);

const selectPoint = (elements: ActiveElement[]): void => {
    const index = elements[0]?.index;
    const point = index === undefined ? undefined : props.points[index];

    if (point) {
        emit('pointSelected', point);
    }
};

const chartData = computed<ChartData<'line'>>(() => ({
    labels: props.points.map((point) => point.label),
    datasets: [
        {
            label: 'Pendapatan',
            data: props.points.map((point) => point.revenue),
            borderColor: isDark.value ? '#8fe697' : '#1c5e21',
            backgroundColor: isDark.value
                ? 'rgba(104, 187, 108, 0.24)'
                : 'rgba(104, 187, 108, 0.18)',
            pointBackgroundColor: isDark.value ? '#8fe697' : '#68bb6c',
            pointBorderColor: isDark.value ? '#121c14' : '#ffffff',
            pointBorderWidth: props.points.length > 6 ? 1.5 : 2,
            pointRadius: props.points.length > 6 ? 3.5 : 5,
            pointHoverRadius: props.points.length > 6 ? 6 : 8,
            pointHoverBackgroundColor: isDark.value ? '#68bb6c' : '#1c5e21',
            fill: true,
            tension: 0.35,
        },
        {
            label: 'Pengeluaran',
            data: props.points.map((point) => point.expense),
            borderColor: isDark.value ? '#ff8585' : '#bc0202',
            backgroundColor: isDark.value
                ? 'rgba(220, 38, 38, 0.16)'
                : 'rgba(188, 2, 2, 0.08)',
            pointBackgroundColor: isDark.value ? '#ff8585' : '#bc0202',
            pointBorderColor: isDark.value ? '#121c14' : '#ffffff',
            pointBorderWidth: props.points.length > 6 ? 1.5 : 2,
            pointRadius: props.points.length > 6 ? 3.5 : 5,
            pointHoverRadius: props.points.length > 6 ? 6 : 8,
            pointHoverBackgroundColor: isDark.value ? '#dc2626' : '#8f0202',
            fill: true,
            tension: 0.35,
        },
    ],
}));

const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index',
        intersect: false,
    },
    onHover: (_event, elements) => selectPoint(elements),
    onClick: (_event, elements) => selectPoint(elements),
    plugins: {
        legend: {
            display: true,
            position: 'bottom',
            labels: {
                usePointStyle: true,
                pointStyle: 'circle',
                color: isDark.value ? '#f9faf9' : '#334238',
                boxWidth: 8,
                padding: 16,
            },
        },
        tooltip: {
            mode: 'index',
            intersect: false,
            backgroundColor: isDark.value ? '#121c14' : '#ffffff',
            titleColor: isDark.value ? '#f9faf9' : '#070807',
            bodyColor: isDark.value ? '#f9faf9' : '#070807',
            borderColor: isDark.value ? '#2d4538' : '#d9d9d9',
            borderWidth: 1,
            callbacks: {
                label: (context: TooltipItem<'line'>) =>
                    ` ${context.dataset.label}: ${formatRupiah(context.parsed.y ?? 0)}`,
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            border: { display: false },
            ticks: { color: isDark.value ? '#c6d6c8' : '#5e6b61' },
        },
        y: {
            beginAtZero: true,
            border: { display: false },
            grid: { color: isDark.value ? '#2d4538' : '#e6eee7' },
            ticks: {
                color: isDark.value ? '#c6d6c8' : '#5e6b61',
                callback: (value: string | number) =>
                    `${Number(value) / 1_000_000} jt`,
            },
        },
    },
}));
</script>

<template>
    <div
        class="h-64 w-full"
        role="img"
        aria-label="Grafik pendapatan dan pengeluaran per bulan"
    >
        <Line
            :data="chartData"
            :options="chartOptions"
            aria-label="Grafik pendapatan dan pengeluaran per bulan. Pilih titik untuk melihat detail periode tersebut."
        />
    </div>
</template>
