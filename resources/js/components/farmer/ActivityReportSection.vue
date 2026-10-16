<script setup lang="ts">
import {
    AlertTriangle,
    CheckCircle2,
    ClipboardList,
    ShieldAlert,
} from '@lucide/vue';
import type {
    ActivityReportData,
    DisturbanceLevel,
    IncidentSeverity,
} from '@/data/activityLogs';

defineProps<{
    report: ActivityReportData;
}>();

function formatNumber(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value);
}

function disturbanceBadgeClass(level: DisturbanceLevel): string {
    if (level === 'Aman') {
        return 'bg-success/15 text-success border-success/30';
    }
    if (level === 'Berat') {
        return 'bg-error/15 text-error border-error/30';
    }
    return 'bg-warning/20 text-foreground border-warning/40';
}

function severityBadgeClass(severity: IncidentSeverity): string {
    if (severity === 'Darurat') {
        return 'bg-error/15 text-error border-error/30';
    }
    return 'bg-warning/20 text-foreground border-warning/40';
}
</script>

<template>
    <section
        aria-labelledby="activity-report-heading"
        class="rounded-xl border border-border bg-card p-4"
    >
        <div class="flex items-center gap-2 border-b border-border pb-3">
            <ClipboardList
                :size="18"
                :stroke-width="1.8"
                class="shrink-0 text-primary"
                aria-hidden="true"
            />
            <h2
                id="activity-report-heading"
                class="text-[16px] leading-5 font-semibold text-foreground"
            >
                Laporan Aktivitas
            </h2>
        </div>

        <!-- 1. Penanaman Benih -->
        <dl
            v-if="report.kind === 'penanaman-benih'"
            class="mt-3.5 space-y-3 text-[14px]"
        >
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Nama merk / varietas</dt>
                <dd class="text-right font-medium text-foreground">
                    {{ report.brandOrVariety }}
                </dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Jumlah benih</dt>
                <dd class="text-right font-medium text-foreground">
                    {{ formatNumber(report.quantityKg) }} kg
                </dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Pola tanam</dt>
                <dd class="text-right font-medium text-foreground">
                    {{ report.plantingPattern }}
                </dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Luas area</dt>
                <dd class="text-right font-medium text-foreground">
                    {{ report.areaSize }}
                </dd>
            </div>
        </dl>

        <!-- 2. Pemupukan -->
        <dl
            v-else-if="report.kind === 'pemupukan'"
            class="mt-3.5 space-y-3 text-[14px]"
        >
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Tahap pemupukan</dt>
                <dd class="text-right font-medium text-foreground">
                    {{ report.fertilizationStage }}
                </dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Jenis pupuk</dt>
                <dd class="text-right font-medium text-foreground">
                    {{ report.fertilizerType }}
                </dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Nama merk</dt>
                <dd class="text-right font-medium text-foreground">
                    {{ report.brandName }}
                </dd>
            </div>
            <div class="flex items-center justify-between gap-4">
                <dt class="text-muted-foreground">Status subsidi pupuk</dt>
                <dd>
                    <span
                        :class="[
                            'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold',
                            report.subsidyStatus === 'Subsidi'
                                ? 'border-success/30 bg-success/15 text-success'
                                : 'border-border bg-muted text-foreground',
                        ]"
                    >
                        {{ report.subsidyStatus }}
                    </span>
                </dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Metode aplikasi</dt>
                <dd class="text-right font-medium text-foreground">
                    {{ report.applicationMethod }}
                </dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Jumlah penggunaan</dt>
                <dd
                    class="text-right font-semibold text-secondary dark:text-primary"
                >
                    {{ report.usageAmount }}
                </dd>
            </div>
        </dl>

        <!-- 3. Pengairan -->
        <dl
            v-else-if="report.kind === 'pengairan'"
            class="mt-3.5 space-y-3 text-[14px]"
        >
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Sumber air</dt>
                <dd class="text-right font-medium text-foreground">
                    {{ report.waterSource }}
                </dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Metode pengairan</dt>
                <dd class="text-right font-medium text-foreground">
                    {{ report.irrigationMethod }}
                </dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Mesin pompa</dt>
                <dd class="text-right font-medium text-foreground">
                    {{ report.machineType }}
                </dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Durasi pengairan</dt>
                <dd
                    class="text-right font-semibold text-secondary dark:text-primary"
                >
                    {{ formatNumber(report.durationHours) }} Jam
                </dd>
            </div>
        </dl>

        <!-- 4. Pengecekan Fase Pertumbuhan -->
        <div
            v-else-if="report.kind === 'pengecekan-pertumbuhan'"
            class="mt-3.5 space-y-3.5 text-[14px]"
        >
            <dl class="space-y-3">
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="text-muted-foreground">Fase pertumbuhan</dt>
                    <dd class="text-right font-medium text-foreground">
                        {{ report.growthPhase }}
                    </dd>
                </div>
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="text-muted-foreground">
                        Rerata tinggi (~5 tanaman)
                    </dt>
                    <dd class="text-right font-semibold text-foreground">
                        {{ formatNumber(report.averageHeightCm) }} cm
                    </dd>
                </div>
            </dl>

            <div class="rounded-lg bg-muted/60 p-3">
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Indikasi Gangguan
                </h3>
                <dl class="mt-2.5 grid grid-cols-2 gap-3">
                    <div
                        class="flex items-center justify-between rounded-lg border border-border bg-card px-3 py-2"
                    >
                        <dt class="text-xs text-muted-foreground">Penyakit</dt>
                        <dd>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-semibold',
                                    disturbanceBadgeClass(
                                        report.diseaseIndication,
                                    ),
                                ]"
                            >
                                <CheckCircle2
                                    v-if="report.diseaseIndication === 'Aman'"
                                    :size="12"
                                    aria-hidden="true"
                                />
                                <AlertTriangle
                                    v-else
                                    :size="12"
                                    aria-hidden="true"
                                />
                                {{ report.diseaseIndication }}
                            </span>
                        </dd>
                    </div>

                    <div
                        class="flex items-center justify-between rounded-lg border border-border bg-card px-3 py-2"
                    >
                        <dt class="text-xs text-muted-foreground">Hama</dt>
                        <dd>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-semibold',
                                    disturbanceBadgeClass(
                                        report.pestIndication,
                                    ),
                                ]"
                            >
                                <CheckCircle2
                                    v-if="report.pestIndication === 'Aman'"
                                    :size="12"
                                    aria-hidden="true"
                                />
                                <AlertTriangle
                                    v-else
                                    :size="12"
                                    aria-hidden="true"
                                />
                                {{ report.pestIndication }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- 5. Pestisida -->
        <dl
            v-else-if="report.kind === 'pestisida'"
            class="mt-3.5 space-y-3 text-[14px]"
        >
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Jenis pestisida</dt>
                <dd class="text-right font-medium text-foreground">
                    {{ report.pesticideCategory }}
                </dd>
            </div>
            <div class="flex items-center justify-between gap-4">
                <dt class="text-muted-foreground">Tujuan aplikasi</dt>
                <dd>
                    <span
                        :class="[
                            'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold',
                            report.purpose === 'Preventif'
                                ? 'border-success/30 bg-success/15 text-success'
                                : 'border-warning/40 bg-warning/20 text-foreground',
                        ]"
                    >
                        {{ report.purpose }}
                    </span>
                </dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Nama merk</dt>
                <dd class="text-right font-medium text-foreground">
                    {{ report.brandName }}
                </dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Bahan aktif</dt>
                <dd class="text-right font-medium text-foreground">
                    {{ report.activeIngredient }}
                </dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Dosis per tangki</dt>
                <dd class="text-right font-medium text-foreground">
                    {{ report.dosagePerTank }}
                </dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Ukuran tangki</dt>
                <dd class="text-right font-medium text-foreground">
                    {{ formatNumber(report.tankSizeLiters) }} Liter
                </dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-muted-foreground">Jumlah tangki</dt>
                <dd
                    class="text-right font-semibold text-secondary dark:text-primary"
                >
                    {{ formatNumber(report.tankCount) }} Tangki
                </dd>
            </div>
        </dl>

        <!-- 6. Pelaporan Insiden -->
        <div
            v-else-if="report.kind === 'pelaporan-insiden'"
            class="mt-3.5 space-y-3.5 text-[14px]"
        >
            <dl class="space-y-3">
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="text-muted-foreground">Kategori insiden</dt>
                    <dd class="text-right font-medium text-foreground">
                        {{ report.incidentCategory }}
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-muted-foreground">Tingkat keparahan</dt>
                    <dd>
                        <span
                            :class="[
                                'inline-flex items-center gap-1 rounded-md border px-2.5 py-0.5 text-xs font-semibold',
                                severityBadgeClass(report.severity),
                            ]"
                        >
                            <ShieldAlert :size="13" aria-hidden="true" />
                            {{ report.severity }}
                        </span>
                    </dd>
                </div>
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="text-muted-foreground">Luas area terdampak</dt>
                    <dd class="text-right font-semibold text-foreground">
                        {{ formatNumber(report.affectedAreaHa) }} Ha
                    </dd>
                </div>
            </dl>

            <div class="rounded-lg border border-border bg-muted/50 p-3">
                <h3 class="text-xs font-semibold text-muted-foreground">
                    Usulan Solusi
                </h3>
                <p class="mt-1 text-[13px] leading-5 text-foreground">
                    {{ report.proposedSolution }}
                </p>
            </div>
        </div>

        <!-- 7. Panen -->
        <div
            v-else-if="report.kind === 'panen'"
            class="mt-3.5 space-y-3.5 text-[14px]"
        >
            <dl class="space-y-3">
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="text-muted-foreground">Luas area panen</dt>
                    <dd class="text-right font-medium text-foreground">
                        {{ report.harvestedArea }}
                    </dd>
                </div>
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="text-muted-foreground">Berat bruto / kotor</dt>
                    <dd
                        class="text-right font-semibold text-secondary dark:text-primary"
                    >
                        {{ report.grossWeight }}
                    </dd>
                </div>
            </dl>

            <div class="rounded-lg bg-muted/60 p-3">
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Deskripsi Kualitas
                </h3>
                <dl class="mt-2.5 grid grid-cols-2 gap-2.5">
                    <div class="rounded-lg border border-border bg-card p-2.5">
                        <dt class="text-xs text-muted-foreground">Kadar air</dt>
                        <dd class="mt-0.5 font-semibold text-foreground">
                            {{ report.quality.moistureContent }}
                        </dd>
                    </div>
                    <div class="rounded-lg border border-border bg-card p-2.5">
                        <dt class="text-xs text-muted-foreground">Warna</dt>
                        <dd class="mt-0.5 font-semibold text-foreground">
                            {{ report.quality.color }}
                        </dd>
                    </div>
                    <div class="rounded-lg border border-border bg-card p-2.5">
                        <dt class="text-xs text-muted-foreground">
                            Kematangan
                        </dt>
                        <dd class="mt-0.5 font-semibold text-foreground">
                            {{ report.quality.ripeness }}
                        </dd>
                    </div>
                    <div class="rounded-lg border border-border bg-card p-2.5">
                        <dt class="text-xs text-muted-foreground">
                            Kebersihan
                        </dt>
                        <dd class="mt-0.5 font-semibold text-foreground">
                            {{ report.quality.cleanliness }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>
</template>
