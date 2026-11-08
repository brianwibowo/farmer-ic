<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, CheckCircle2, ClipboardList, Ruler, Users } from '@lucide/vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';

type Farm = {
    id: string;
    name: string;
    city: string | null;
    district: string | null;
    province: string | null;
};

type Activity = {
    id: string;
    type: string | null;
    position: number;
    notes: string | null;
    obstacle_status: 'none' | 'reported' | 'not_recorded';
    obstacles: string | null;
    proposed_solution: string | null;
    action_taken: string | null;
    block_mapping: {
        form_version: number;
        block_name: string;
        length_m: string;
        width_m: string;
        row_spacing_cm: string | null;
        plant_spacing_cm: string | null;
        rows_per_bed: number | null;
    } | null;
};

type DailyLog = {
    id: string;
    farm_id: string;
    work_date: string;
    start_time: string;
    end_time: string;
    timezone: string;
    worker_count: number;
    recorder_name: string | null;
    notes: string | null;
    activities: Activity[];
};

const props = defineProps<{ farm: Farm; log: DailyLog }>();
</script>

<template>
    <Head :title="`Detail Logbook - ${farm.name}`" />

    <FarmerMobileShell active-key="garden">
        <div class="space-y-5 px-4 pt-4 pb-6">
            <header class="flex items-start gap-3">
                <Link
                    :href="`/kebun/${farm.id}/daily-logs`"
                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-card text-secondary transition-colors hover:border-primary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    aria-label="Kembali ke logbook"
                >
                    <ArrowLeft :size="18" />
                </Link>
                <div class="min-w-0">
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">Riwayat kebun</p>
                    <h1 class="truncate text-[21px] leading-7 font-semibold text-foreground">Detail Logbook</h1>
                    <p class="truncate text-sm font-medium text-secondary">{{ farm.name }}</p>
                </div>
            </header>

            <section class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs text-muted-foreground">Tanggal kegiatan</p>
                        <h2 class="mt-1 text-xl font-semibold text-foreground">{{ log.work_date }}</h2>
                    </div>
                    <ClipboardList :size="24" class="text-primary" aria-hidden="true" />
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-xl bg-muted/40 p-3">
                        <p class="text-muted-foreground">Waktu</p>
                        <p class="mt-1 font-semibold text-foreground">{{ log.start_time }}–{{ log.end_time }} WIB</p>
                    </div>
                    <div class="rounded-xl bg-muted/40 p-3">
                        <p class="text-muted-foreground">Petugas</p>
                        <p class="mt-1 font-semibold text-foreground">{{ log.recorder_name ?? 'Tidak tercatat' }}</p>
                    </div>
                    <div class="col-span-2 flex items-center gap-2 rounded-xl bg-muted/40 p-3">
                        <Users :size="16" class="text-primary" />
                        <span class="text-muted-foreground">Jumlah pekerja:</span>
                        <strong class="text-foreground">{{ log.worker_count }}</strong>
                    </div>
                </div>

                <p v-if="log.notes" class="mt-4 rounded-xl border border-border bg-muted/20 p-3 text-sm leading-6 text-muted-foreground">{{ log.notes }}</p>
            </section>

            <section class="space-y-3">
                <div class="flex items-center gap-2">
                    <Ruler :size="19" class="text-primary" />
                    <h2 class="text-base font-semibold text-foreground">Rincian pemetaan blok</h2>
                </div>

                <article v-for="activity in log.activities" :key="activity.id" class="space-y-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs text-muted-foreground">Kegiatan {{ activity.position }}</p>
                            <h3 class="mt-1 text-base font-semibold text-foreground">{{ activity.block_mapping?.block_name ?? 'Pemetaan blok' }}</h3>
                        </div>
                        <span v-if="activity.obstacle_status === 'reported'" class="inline-flex items-center gap-1 rounded-full bg-warning/25 px-2 py-1 text-[11px] font-medium text-warning-foreground"><AlertTriangle :size="13" /> Ada kendala</span>
                        <span v-else class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2 py-1 text-[11px] font-medium text-success"><CheckCircle2 :size="13" /> Lancar</span>
                    </div>

                    <div v-if="activity.block_mapping" class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-xl bg-muted/40 p-3"><p class="text-muted-foreground">Ukuran blok</p><p class="mt-1 font-semibold text-foreground">{{ activity.block_mapping.length_m }} m × {{ activity.block_mapping.width_m }} m</p></div>
                        <div class="rounded-xl bg-muted/40 p-3"><p class="text-muted-foreground">Baris per bedeng</p><p class="mt-1 font-semibold text-foreground">{{ activity.block_mapping.rows_per_bed ?? '—' }}</p></div>
                        <div class="rounded-xl bg-muted/40 p-3"><p class="text-muted-foreground">Jarak antarbaris</p><p class="mt-1 font-semibold text-foreground">{{ activity.block_mapping.row_spacing_cm ?? '—' }} cm</p></div>
                        <div class="rounded-xl bg-muted/40 p-3"><p class="text-muted-foreground">Jarak tanam</p><p class="mt-1 font-semibold text-foreground">{{ activity.block_mapping.plant_spacing_cm ?? '—' }} cm</p></div>
                    </div>

                    <div v-if="activity.notes" class="text-sm leading-6 text-muted-foreground"><p class="font-semibold text-foreground">Catatan</p><p>{{ activity.notes }}</p></div>
                    <div v-if="activity.obstacles" class="text-sm leading-6 text-muted-foreground"><p class="font-semibold text-foreground">Kendala</p><p>{{ activity.obstacles }}</p></div>
                    <div v-if="activity.proposed_solution" class="text-sm leading-6 text-muted-foreground"><p class="font-semibold text-foreground">Solusi</p><p>{{ activity.proposed_solution }}</p></div>
                    <div v-if="activity.action_taken" class="text-sm leading-6 text-muted-foreground"><p class="font-semibold text-foreground">Tindakan</p><p>{{ activity.action_taken }}</p></div>
                </article>
            </section>
        </div>
    </FarmerMobileShell>
</template>
