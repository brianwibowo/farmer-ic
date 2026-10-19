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
} from "chart.js";
import { computed } from "vue";
import { Line } from "vue-chartjs";
import type { RevenuePoint } from "@/data/farmerFinance";

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
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(value);

const selectPoint = (elements: ActiveElement[]): void => {
    const index = elements[0]?.index;
    const point = index === undefined ? undefined : props.points[index];

    if (point) {
        emit("pointSelected", point);
    }
};

const chartData = computed<ChartData<"line">>(() => ({
    labels: props.points.map((point) => point.label),
    datasets: [
        {
            label: "Pendapatan",
            data: props.points.map((point) => point.revenue),
            borderColor: "#1c5e21",
            backgroundColor: "rgba(104, 187, 108, 0.18)",
            pointBackgroundColor: "#68bb6c",
            pointBorderColor: "#ffffff",
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 8,
            pointHoverBackgroundColor: "#1c5e21",
            fill: true,
            tension: 0.35,
        },
        {
            label: "Pengeluaran",
            data: props.points.map((point) => point.expense),
            borderColor: "#bc0202",
            backgroundColor: "rgba(188, 2, 2, 0.08)",
            pointBackgroundColor: "#bc0202",
            pointBorderColor: "#ffffff",
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 8,
            pointHoverBackgroundColor: "#8f0202",
            fill: true,
            tension: 0.35,
        },
    ],
}));

const chartOptions: ChartOptions<"line"> = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: "index",
        intersect: false,
    },
    onHover: (_event, elements) => selectPoint(elements),
    onClick: (_event, elements) => selectPoint(elements),
    plugins: {
        legend: {
            display: true,
            position: "bottom",
            labels: {
                usePointStyle: true,
                pointStyle: "circle",
                color: "#334238",
                boxWidth: 8,
                padding: 16,
            },
        },
        tooltip: {
            mode: "index",
            intersect: false,
            callbacks: {
                label: (context: TooltipItem<"line">) =>
                    ` ${context.dataset.label}: ${formatRupiah(context.parsed.y ?? 0)}`,
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            border: { display: false },
            ticks: { color: "#5e6b61" },
        },
        y: {
            beginAtZero: true,
            border: { display: false },
            grid: { color: "#e6eee7" },
            ticks: {
                color: "#5e6b61",
                callback: (value: string | number) =>
                    `${Number(value) / 1_000_000} jt`,
            },
        },
    },
};
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
