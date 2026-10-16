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
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import type { RevenuePoint } from '@/mocks/farmerFinance';

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
            label: 'Revenue',
            data: props.points.map((point) => point.amount),
            borderColor: '#1c5e21',
            backgroundColor: 'rgba(104, 187, 108, 0.18)',
            pointBackgroundColor: '#68bb6c',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 8,
            pointHoverBackgroundColor: '#1c5e21',
            fill: true,
            tension: 0.35,
        },
    ],
}));

const chartOptions: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index',
        intersect: false,
    },
    onHover: (_event, elements) => selectPoint(elements),
    onClick: (_event, elements) => selectPoint(elements),
    plugins: {
        legend: { display: false },
        tooltip: {
            mode: 'index',
            intersect: false,
            callbacks: {
                label: (context: TooltipItem<'line'>) =>
                    ` ${formatRupiah(context.parsed.y ?? 0)}`,
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            border: { display: false },
            ticks: { color: '#5e6b61' },
        },
        y: {
            beginAtZero: true,
            border: { display: false },
            grid: { color: '#e6eee7' },
            ticks: {
                color: '#5e6b61',
                callback: (value: string | number) =>
                    `${Number(value) / 1_000_000} jt`,
            },
        },
    },
};
</script>

<template>
    <div
        class="h-56 w-full"
        role="img"
        aria-label="Grafik pendapatan per bulan"
    >
        <Line
            :data="chartData"
            :options="chartOptions"
            aria-label="Grafik pendapatan per bulan. Pilih titik untuk melihat revenue pada periode tersebut."
        />
    </div>
</template>
