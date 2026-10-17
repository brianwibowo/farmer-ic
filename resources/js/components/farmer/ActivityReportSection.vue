<script setup lang="ts">
import {
    AlertTriangle,
    CheckCircle2,
    ClipboardList,
    ShieldAlert,
} from '@lucide/vue';
import { computed } from 'vue';
import type { ActivityReportData } from '@/data/activityLogs';

type DetailRow = {
    label: string;
    value: string;
    emphasis?: boolean;
};

const props = defineProps<{
    report: ActivityReportData;
}>();

function formatNumber(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value);
}

const detailRows = computed<DetailRow[]>(() => {
    const report = props.report;

    switch (report.kind) {
        case 'penanaman-benih':
            return [
                { label: 'Nama merk / varietas', value: report.brandOrVariety },
                {
                    label: 'Jumlah benih',
                    value: `${formatNumber(report.quantityKg)} kg`,
                    emphasis: true,
                },
                { label: 'Pola tanam', value: report.plantingPattern },
                { label: 'Luas area', value: report.areaSize, emphasis: true },
            ];
        case 'pemupukan':
            return [
                { label: 'Tahap pemupukan', value: report.fertilizationStage },
                { label: 'Jenis pupuk', value: report.fertilizerType },
                { label: 'Nama merk', value: report.brandName },
                { label: 'Status subsidi', value: report.subsidyStatus },
                { label: 'Metode aplikasi', value: report.applicationMethod },
                {
                    label: 'Jumlah penggunaan',
                    value: report.usageAmount,
                    emphasis: true,
                },
            ];
        case 'pengairan':
            return [
                { label: 'Sumber air', value: report.waterSource },
                { label: 'Metode pengairan', value: report.irrigationMethod },
                { label: 'Mesin pompa', value: report.machineType },
                {
                    label: 'Durasi pengairan',
                    value: `${formatNumber(report.durationHours)} jam`,
                    emphasis: true,
                },
            ];
        case 'pestisida':
            return [
                { label: 'Jenis pestisida', value: report.pesticideCategory },
                { label: 'Tujuan aplikasi', value: report.purpose },
                { label: 'Nama merk', value: report.brandName },
                { label: 'Bahan aktif', value: report.activeIngredient },
                { label: 'Dosis per tangki', value: report.dosagePerTank },
                {
                    label: 'Ukuran tangki',
                    value: `${formatNumber(report.tankSizeLiters)} liter`,
                },
                {
                    label: 'Jumlah tangki',
                    value: `${formatNumber(report.tankCount)} tangki`,
                    emphasis: true,
                },
            ];
        default:
            return [];
    }
});

const growthRows = computed<DetailRow[]>(() => {
    if (props.report.kind !== 'pengecekan-pertumbuhan') return [];
    return [
        { label: 'Fase pertumbuhan', value: props.report.growthPhase },
        {
            label: 'Rerata tinggi tanaman',
            value: `${formatNumber(props.report.averageHeightCm)} cm`,
            emphasis: true,
        },
    ];
});

const incidentRows = computed<DetailRow[]>(() => {
    if (props.report.kind !== 'pelaporan-insiden') return [];
    return [
        { label: 'Kategori insiden', value: props.report.incidentCategory },
        {
            label: 'Luas area terdampak',
            value: `${formatNumber(props.report.affectedAreaHa)} Ha`,
            emphasis: true,
        },
    ];
});

const harvestRows = computed<DetailRow[]>(() => {
    if (props.report.kind !== 'panen') return [];
    return [
        { label: 'Luas area panen', value: props.report.harvestedArea },
        {
            label: 'Berat bruto / kotor',
            value: props.report.grossWeight,
            emphasis: true,
        },
    ];
});

const harvestQuality = computed(() => {
    if (props.report.kind !== 'panen') return [];
    return [
        { label: 'Kadar air', value: props.report.quality.moistureContent },
        { label: 'Warna', value: props.report.quality.color },
        { label: 'Kematangan', value: props.report.quality.ripeness },
        { label: 'Kebersihan', value: props.report.quality.cleanliness },
    ];
});

function badgeClass(value: string): string {
    if (value === 'Aman' || value === 'Subsidi' || value === 'Preventif') {
        return 'border-success/30 bg-success/10 text-success';
    }
    if (value === 'Darurat' || value === 'Berat' || value === 'Korektif') {
        return 'border-error/30 bg-error/10 text-error';
    }
    if (value === 'Ringan' || value === 'Bisa Ditangani') {
        return 'border-warning/40 bg-warning/15 text-foreground';
    }
    return 'border-border bg-muted text-foreground';
}

function isBadgeRow(row: DetailRow): boolean {
    return ['Status subsidi', 'Tujuan aplikasi'].includes(row.label);
}
</script>

<template>
    <section
        aria-labelledby="activity-report-heading"
        class="rounded-2xl border border-border bg-card p-4"
    >
        <div
            class="flex items-center justify-between gap-3 border-b border-border pb-3"
        >
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex size-8 items-center justify-center rounded-lg bg-surface text-secondary"
                >
                    <ClipboardList
                        :size="17"
                        :stroke-width="1.8"
                        aria-hidden="true"
                    />
                </span>
                <div>
                    <h2
                        id="activity-report-heading"
                        class="text-base leading-5 font-semibold text-foreground"
                    >
                        Laporan aktivitas
                    </h2>
                    <p class="mt-0.5 text-[11px] text-muted-foreground">
                        Data yang dicatat di lapangan
                    </p>
                </div>
            </div>
            <span class="text-[11px] font-medium text-muted-foreground"
                >Lengkap</span
            >
        </div>

        <dl v-if="detailRows.length" class="mt-4 divide-y divide-border/80">
            <div
                v-for="row in detailRows"
                :key="row.label"
                class="flex items-start justify-between gap-4 py-2.5 first:pt-0 last:pb-0"
            >
                <dt class="min-w-0 text-sm text-muted-foreground">
                    {{ row.label }}
                </dt>
                <dd
                    v-if="isBadgeRow(row)"
                    :class="[
                        'shrink-0 rounded-md border px-2 py-1 text-xs font-semibold',
                        badgeClass(row.value),
                    ]"
                >
                    {{ row.value }}
                </dd>
                <dd
                    v-else
                    :class="[
                        'max-w-[60%] text-right text-sm',
                        row.emphasis
                            ? 'font-semibold text-secondary'
                            : 'font-medium text-foreground',
                    ]"
                >
                    {{ row.value }}
                </dd>
            </div>
        </dl>

        <div
            v-else-if="props.report.kind === 'pengecekan-pertumbuhan'"
            class="mt-4 space-y-4"
        >
            <dl class="divide-y divide-border/80">
                <div
                    v-for="row in growthRows"
                    :key="row.label"
                    class="flex items-start justify-between gap-4 py-2.5 first:pt-0 last:pb-0"
                >
                    <dt class="text-sm text-muted-foreground">
                        {{ row.label }}
                    </dt>
                    <dd
                        :class="[
                            'max-w-[60%] text-right text-sm',
                            row.emphasis
                                ? 'font-semibold text-secondary'
                                : 'font-medium text-foreground',
                        ]"
                    >
                        {{ row.value }}
                    </dd>
                </div>
            </dl>
            <div class="rounded-xl bg-muted/60 p-3">
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Indikasi gangguan
                </p>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <div class="rounded-lg border border-border bg-card p-2.5">
                        <p class="text-[11px] text-muted-foreground">
                            Penyakit
                        </p>
                        <span
                            :class="[
                                'mt-1 inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold',
                                badgeClass(props.report.diseaseIndication),
                            ]"
                        >
                            <CheckCircle2
                                v-if="props.report.diseaseIndication === 'Aman'"
                                :size="13"
                                aria-hidden="true"
                            />
                            <AlertTriangle
                                v-else
                                :size="13"
                                aria-hidden="true"
                            />
                            {{ props.report.diseaseIndication }}
                        </span>
                    </div>
                    <div class="rounded-lg border border-border bg-card p-2.5">
                        <p class="text-[11px] text-muted-foreground">Hama</p>
                        <span
                            :class="[
                                'mt-1 inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold',
                                badgeClass(props.report.pestIndication),
                            ]"
                        >
                            <CheckCircle2
                                v-if="props.report.pestIndication === 'Aman'"
                                :size="13"
                                aria-hidden="true"
                            />
                            <AlertTriangle
                                v-else
                                :size="13"
                                aria-hidden="true"
                            />
                            {{ props.report.pestIndication }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else-if="props.report.kind === 'pelaporan-insiden'"
            class="mt-4 space-y-4"
        >
            <dl class="divide-y divide-border/80">
                <div
                    v-for="row in incidentRows"
                    :key="row.label"
                    class="flex items-start justify-between gap-4 py-2.5 first:pt-0 last:pb-0"
                >
                    <dt class="text-sm text-muted-foreground">
                        {{ row.label }}
                    </dt>
                    <dd
                        :class="[
                            'max-w-[60%] text-right text-sm',
                            row.emphasis
                                ? 'font-semibold text-secondary'
                                : 'font-medium text-foreground',
                        ]"
                    >
                        {{ row.value }}
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <dt class="text-sm text-muted-foreground">
                        Tingkat keparahan
                    </dt>
                    <dd
                        :class="[
                            'inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold',
                            badgeClass(props.report.severity),
                        ]"
                    >
                        <ShieldAlert :size="13" aria-hidden="true" />
                        {{ props.report.severity }}
                    </dd>
                </div>
            </dl>
            <div class="rounded-xl border border-error/20 bg-error/5 p-3">
                <p class="text-xs font-semibold text-error">Usulan solusi</p>
                <p class="mt-1.5 text-sm leading-5 text-foreground">
                    {{ props.report.proposedSolution }}
                </p>
            </div>
        </div>

        <div v-else-if="props.report.kind === 'panen'" class="mt-4 space-y-4">
            <dl class="divide-y divide-border/80">
                <div
                    v-for="row in harvestRows"
                    :key="row.label"
                    class="flex items-start justify-between gap-4 py-2.5 first:pt-0 last:pb-0"
                >
                    <dt class="text-sm text-muted-foreground">
                        {{ row.label }}
                    </dt>
                    <dd
                        :class="[
                            'max-w-[60%] text-right text-sm',
                            row.emphasis
                                ? 'font-semibold text-secondary'
                                : 'font-medium text-foreground',
                        ]"
                    >
                        {{ row.value }}
                    </dd>
                </div>
            </dl>
            <div class="rounded-xl bg-muted/60 p-3">
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Deskripsi kualitas
                </p>
                <dl class="mt-2 grid grid-cols-2 gap-2">
                    <div
                        v-for="row in harvestQuality"
                        :key="row.label"
                        class="rounded-lg border border-border bg-card p-2.5"
                    >
                        <dt class="text-[11px] text-muted-foreground">
                            {{ row.label }}
                        </dt>
                        <dd
                            class="mt-1 text-xs leading-4 font-semibold text-foreground"
                        >
                            {{ row.value }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>
</template>
