<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Clock3,
    ClipboardList,
    Plus,
    Save,
    Trash2,
    Users,
} from '@lucide/vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';

type Farm = {
    id: string;
    name: string;
    city: string | null;
    district: string | null;
    province: string | null;
};

type BlockMapping = {
    form_version: number;
    block_name: string;
    length_m: string;
    width_m: string;
    row_spacing_cm: string | null;
    plant_spacing_cm: string | null;
    rows_per_bed: number | null;
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
    block_mapping: BlockMapping | null;
};

type DailyLog = {
    id: string;
    farm_id: string;
    work_date: string;
    start_time: string;
    end_time: string;
    timezone: string;
    worker_count: number;
    recorded_by: string;
    recorder_name: string | null;
    notes: string | null;
    activities: Activity[];
    created_at: string;
    updated_at: string | null;
};

type PaginatedLogs = {
    data: DailyLog[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
};

type ActivityForm = {
    type: 'block_mapping';
    block_mapping: {
        block_name: string;
        length_m: string;
        width_m: string;
        row_spacing_cm: string;
        plant_spacing_cm: string;
        rows_per_bed: string;
    };
    obstacle_status: 'none' | 'reported';
    obstacles: string;
    proposed_solution: string;
    action_taken: string;
    notes: string;
};

type Props = {
    farm: Farm;
    logs: PaginatedLogs;
    filters: Record<string, string | number | null>;
    recorder: { id: string; name: string };
    today: string;
    can: { create: boolean };
};

const props = defineProps<Props>();

function emptyActivity(): ActivityForm {
    return {
        type: 'block_mapping',
        block_mapping: {
            block_name: '',
            length_m: '',
            width_m: '',
            row_spacing_cm: '',
            plant_spacing_cm: '',
            rows_per_bed: '',
        },
        obstacle_status: 'none',
        obstacles: '',
        proposed_solution: '',
        action_taken: '',
        notes: '',
    };
}

const form = useForm({
    work_date: props.today,
    start_time: '08:00',
    end_time: '16:00',
    worker_count: 1,
    notes: '',
    activities: [emptyActivity()],
});

const filterForm = useForm({
    from_date: String(props.filters.from_date ?? ''),
    to_date: String(props.filters.to_date ?? ''),
});

function fieldError(name: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[name];
}

function addActivity(): void {
    form.activities.push(emptyActivity());
}

function removeActivity(index: number): void {
    if (form.activities.length === 1) return;

    form.activities.splice(index, 1);
}

function submit(): void {
    form.post(`/kebun/${props.farm.id}/daily-logs`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('start_time', 'end_time', 'worker_count', 'notes', 'activities');
            form.work_date = props.today;
            form.activities = [emptyActivity()];
        },
    });
}

function applyFilters(): void {
    filterForm.get(`/kebun/${props.farm.id}/daily-logs`, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearFilters(): void {
    filterForm.from_date = '';
    filterForm.to_date = '';
    applyFilters();
}

function goToPage(url: string | null): void {
    if (url) router.visit(url, { preserveScroll: true, preserveState: true });
}

function detailUrl(log: DailyLog): string {
    return `/kebun/${props.farm.id}/daily-logs/${log.id}`;
}
</script>

<template>
    <Head :title="`Logbook Harian - ${farm.name}`" />

    <FarmerMobileShell active-key="garden">
        <div class="space-y-5 px-4 pt-4 pb-6">
            <header class="flex items-start gap-3">
                <Link
                    href="/kebun"
                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-card text-secondary transition-colors hover:border-primary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    aria-label="Kembali ke daftar kebun"
                >
                    <ArrowLeft :size="18" />
                </Link>
                <div class="min-w-0">
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">Aktivitas kebun</p>
                    <h1 class="truncate text-[21px] leading-7 font-semibold text-foreground">Logbook Harian</h1>
                    <p class="truncate text-sm font-medium text-secondary">{{ farm.name }}</p>
                </div>
            </header>

            <section v-if="can.create" class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-foreground">Catat kegiatan hari ini</h2>
                        <p class="mt-1 text-xs leading-5 text-muted-foreground">Isi satu laporan untuk beberapa kegiatan pemetaan blok pada hari yang sama.</p>
                    </div>
                    <ClipboardList :size="22" class="shrink-0 text-primary" aria-hidden="true" />
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div class="grid grid-cols-2 gap-3">
                        <label class="space-y-1.5 text-xs font-medium text-foreground">
                            <span class="flex items-center gap-1.5"><CalendarDays :size="15" class="text-primary" />Tanggal kerja</span>
                            <input v-model="form.work_date" type="date" class="form-input" />
                            <p v-if="fieldError('work_date')" class="form-error">{{ fieldError('work_date') }}</p>
                        </label>
                        <label class="space-y-1.5 text-xs font-medium text-foreground">
                            <span class="flex items-center gap-1.5"><Users :size="15" class="text-primary" />Jumlah pekerja</span>
                            <input v-model.number="form.worker_count" type="number" min="1" max="32767" class="form-input" />
                            <p v-if="fieldError('worker_count')" class="form-error">{{ fieldError('worker_count') }}</p>
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="space-y-1.5 text-xs font-medium text-foreground">
                            <span class="flex items-center gap-1.5"><Clock3 :size="15" class="text-primary" />Mulai</span>
                            <input v-model="form.start_time" type="time" class="form-input" />
                            <p v-if="fieldError('start_time')" class="form-error">{{ fieldError('start_time') }}</p>
                        </label>
                        <label class="space-y-1.5 text-xs font-medium text-foreground">
                            <span class="flex items-center gap-1.5"><Clock3 :size="15" class="text-primary" />Selesai</span>
                            <input v-model="form.end_time" type="time" class="form-input" />
                            <p v-if="fieldError('end_time')" class="form-error">{{ fieldError('end_time') }}</p>
                        </label>
                    </div>

                    <label class="space-y-1.5 text-xs font-medium text-foreground">
                        <span>Catatan laporan</span>
                        <textarea v-model="form.notes" rows="2" class="form-input resize-y" placeholder="Contoh: Pemetaan awal sebelum pembuatan bedengan." />
                        <p v-if="fieldError('notes')" class="form-error">{{ fieldError('notes') }}</p>
                    </label>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-foreground">Kegiatan pemetaan blok</h3>
                                <p class="text-xs text-muted-foreground">Pelapor: {{ recorder.name }}</p>
                            </div>
                            <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-border px-2.5 py-2 text-xs font-semibold text-secondary hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" @click="addActivity">
                                <Plus :size="15" /> Tambah blok
                            </button>
                        </div>

                        <article v-for="(activity, index) in form.activities" :key="index" class="space-y-3 rounded-xl border border-border bg-muted/30 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <h4 class="text-sm font-semibold text-foreground">Blok {{ index + 1 }}</h4>
                                <button v-if="form.activities.length > 1" type="button" class="rounded-md p-1.5 text-error hover:bg-error/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" :aria-label="`Hapus blok ${index + 1}`" @click="removeActivity(index)">
                                    <Trash2 :size="16" />
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <label class="col-span-2 space-y-1.5 text-xs font-medium text-foreground">
                                    <span>Nama blok</span>
                                    <input v-model="activity.block_mapping.block_name" type="text" class="form-input" placeholder="Blok 1" />
                                    <p v-if="fieldError(`activities.${index}.block_mapping.block_name`)" class="form-error">{{ fieldError(`activities.${index}.block_mapping.block_name`) }}</p>
                                </label>
                                <label class="space-y-1.5 text-xs font-medium text-foreground">
                                    <span>Panjang (m)</span>
                                    <input v-model="activity.block_mapping.length_m" type="number" min="0.001" step="0.001" class="form-input" placeholder="35" />
                                    <p v-if="fieldError(`activities.${index}.block_mapping.length_m`)" class="form-error">{{ fieldError(`activities.${index}.block_mapping.length_m`) }}</p>
                                </label>
                                <label class="space-y-1.5 text-xs font-medium text-foreground">
                                    <span>Lebar (m)</span>
                                    <input v-model="activity.block_mapping.width_m" type="number" min="0.001" step="0.001" class="form-input" placeholder="50" />
                                    <p v-if="fieldError(`activities.${index}.block_mapping.width_m`)" class="form-error">{{ fieldError(`activities.${index}.block_mapping.width_m`) }}</p>
                                </label>
                                <label class="space-y-1.5 text-xs font-medium text-foreground">
                                    <span>Jarak antarbaris (cm)</span>
                                    <input v-model="activity.block_mapping.row_spacing_cm" type="number" min="0.01" step="0.01" class="form-input" placeholder="40" />
                                </label>
                                <label class="space-y-1.5 text-xs font-medium text-foreground">
                                    <span>Jarak tanam (cm)</span>
                                    <input v-model="activity.block_mapping.plant_spacing_cm" type="number" min="0.01" step="0.01" class="form-input" placeholder="20" />
                                </label>
                                <label class="col-span-2 space-y-1.5 text-xs font-medium text-foreground">
                                    <span>Baris tanaman per bedeng <span class="font-normal text-muted-foreground">(opsional)</span></span>
                                    <input v-model="activity.block_mapping.rows_per_bed" type="number" min="1" step="1" class="form-input" placeholder="2" />
                                </label>
                            </div>

                            <label class="space-y-1.5 text-xs font-medium text-foreground">
                                <span>Catatan kegiatan <span class="font-normal text-muted-foreground">(opsional)</span></span>
                                <textarea v-model="activity.notes" rows="2" class="form-input resize-y" placeholder="Hasil atau catatan pengukuran blok." />
                            </label>

                            <div class="grid gap-3">
                                <label class="space-y-1.5 text-xs font-medium text-foreground">
                                    <span>Status kendala</span>
                                    <select v-model="activity.obstacle_status" class="form-input">
                                        <option value="none">Tidak ada kendala</option>
                                        <option value="reported">Ada kendala</option>
                                    </select>
                                </label>
                                <label v-if="activity.obstacle_status === 'reported'" class="space-y-1.5 text-xs font-medium text-foreground">
                                    <span>Uraian kendala</span>
                                    <textarea v-model="activity.obstacles" rows="2" class="form-input resize-y" placeholder="Jelaskan kendala di lapangan." />
                                </label>
                                <label class="space-y-1.5 text-xs font-medium text-foreground">
                                    <span>Solusi yang diusulkan <span class="font-normal text-muted-foreground">(opsional)</span></span>
                                    <textarea v-model="activity.proposed_solution" rows="2" class="form-input resize-y" placeholder="Tuliskan solusi atau penyesuaian." />
                                </label>
                                <label class="space-y-1.5 text-xs font-medium text-foreground">
                                    <span>Tindakan yang dilakukan <span class="font-normal text-muted-foreground">(opsional)</span></span>
                                    <textarea v-model="activity.action_taken" rows="2" class="form-input resize-y" placeholder="Tuliskan tindakan di lapangan." />
                                </label>
                            </div>
                        </article>
                    </div>

                    <div v-if="Object.keys(form.errors).length" class="rounded-xl border border-error/25 bg-error/10 px-3 py-2.5 text-xs text-error" role="alert">
                        Periksa kembali isian yang ditandai sebelum menyimpan logbook.
                    </div>

                    <button type="submit" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition-colors hover:bg-secondary disabled:cursor-not-allowed disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" :disabled="form.processing">
                        <Save :size="17" />
                        {{ form.processing ? 'Menyimpan...' : 'Simpan logbook' }}
                    </button>
                </form>
            </section>

            <section class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-foreground">Riwayat logbook</h2>
                        <p class="mt-1 text-xs text-muted-foreground">{{ logs.meta.total }} laporan tersimpan untuk kebun ini.</p>
                    </div>
                    <ClipboardList :size="22" class="shrink-0 text-primary" aria-hidden="true" />
                </div>

                <form class="mt-4 grid grid-cols-2 gap-3" @submit.prevent="applyFilters">
                    <label class="space-y-1.5 text-xs font-medium text-foreground">
                        <span>Dari tanggal</span>
                        <input v-model="filterForm.from_date" type="date" class="form-input" />
                    </label>
                    <label class="space-y-1.5 text-xs font-medium text-foreground">
                        <span>Sampai tanggal</span>
                        <input v-model="filterForm.to_date" type="date" class="form-input" />
                    </label>
                    <div class="col-span-2 flex gap-2">
                        <button type="submit" class="inline-flex h-9 flex-1 items-center justify-center rounded-lg bg-secondary px-3 text-xs font-semibold text-secondary-foreground hover:bg-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">Terapkan filter</button>
                        <button type="button" class="inline-flex h-9 items-center justify-center rounded-lg border border-border px-3 text-xs font-semibold text-foreground hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" @click="clearFilters">Reset</button>
                    </div>
                </form>

                <div v-if="logs.data.length === 0" class="mt-5 rounded-xl border border-dashed border-border bg-muted/30 px-4 py-8 text-center">
                    <ClipboardList :size="30" class="mx-auto text-primary" aria-hidden="true" />
                    <h3 class="mt-3 text-sm font-semibold text-foreground">Belum ada logbook</h3>
                    <p class="mt-1 text-xs leading-5 text-muted-foreground">Simpan laporan kegiatan pertama untuk melihat riwayatnya di sini.</p>
                </div>

                <div v-else class="mt-5 space-y-3">
                    <article v-for="log in logs.data" :key="log.id" class="rounded-xl border border-border bg-muted/20 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-foreground">{{ log.work_date }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ log.start_time }}–{{ log.end_time }} WIB · {{ log.worker_count }} pekerja</p>
                            </div>
                            <Link :href="detailUrl(log)" class="inline-flex items-center gap-1 text-xs font-semibold text-secondary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                                Detail <ChevronRight :size="15" />
                            </Link>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <span v-for="activity in log.activities" :key="activity.id" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-1 text-[11px] font-medium text-secondary">
                                <span class="size-1.5 rounded-full bg-primary" />{{ activity.block_mapping?.block_name ?? 'Pemetaan blok' }}
                            </span>
                        </div>
                        <p v-if="log.notes" class="mt-3 line-clamp-2 text-xs leading-5 text-muted-foreground">{{ log.notes }}</p>
                    </article>
                </div>

                <div v-if="logs.meta.last_page > 1" class="mt-4 flex items-center justify-between border-t border-border pt-3">
                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-border px-2.5 py-2 text-xs font-semibold text-foreground disabled:cursor-not-allowed disabled:opacity-40" :disabled="!logs.links.prev" @click="goToPage(logs.links.prev)">
                        <ChevronLeft :size="15" /> Sebelumnya
                    </button>
                    <span class="text-xs text-muted-foreground">Halaman {{ logs.meta.current_page }} dari {{ logs.meta.last_page }}</span>
                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-border px-2.5 py-2 text-xs font-semibold text-foreground disabled:cursor-not-allowed disabled:opacity-40" :disabled="!logs.links.next" @click="goToPage(logs.links.next)">
                        Berikutnya <ChevronRight :size="15" />
                    </button>
                </div>
            </section>
        </div>
    </FarmerMobileShell>
</template>

<style scoped>
.form-input {
    width: 100%;
    min-height: 40px;
    border: 1px solid hsl(var(--border));
    border-radius: 0.625rem;
    background: hsl(var(--card));
    padding: 0.55rem 0.7rem;
    font-size: 0.8125rem;
    color: hsl(var(--foreground));
    outline: none;
}

.form-input:focus {
    border-color: hsl(var(--primary));
    box-shadow: 0 0 0 2px hsl(var(--primary) / 0.2);
}

.form-error {
    color: hsl(var(--error));
    font-size: 0.6875rem;
    line-height: 1rem;
}
</style>
