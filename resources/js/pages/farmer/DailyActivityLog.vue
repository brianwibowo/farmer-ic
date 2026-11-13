<script setup lang="ts">
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
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
} from '@lucide/vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import FarmManagementLayout from '@/layouts/FarmManagementLayout.vue';
import { useFarmManagementNavigation } from '@/composables/useFarmManagementNavigation';
import DailyHeaderContext from '@/components/petugas/DailyHeaderContext.vue';
import { computed, ref } from 'vue';
import { maximumError } from '@/lib/activityInputLimits';

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
    name: string | null;
    position: number;
    worker_count: number | null;
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
    end_time: string | null;
    timezone: string;
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
        block_id: string;
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
    blocks: { id: string; code: string; name: string }[];
    logs: PaginatedLogs;
    filters: Record<string, string | number | null>;
    recorder: { id: string; name: string };
    today: string;
    can: { create: boolean };
    numericLimits: {
        workerCount: number;
        length: number;
        spacing: number;
        rows: number;
    };
};

const props = defineProps<Props>();
const page = usePage();
const { indexUrl } = useFarmManagementNavigation();
const headerReady = ref(false);
const existingHeader = ref(false);
const headerVersion = ref(0);
function selectBlock(activity: ActivityForm): void {
    activity.block_mapping.block_name =
        props.blocks.find(
            (block) => block.id === activity.block_mapping.block_id,
        )?.name ?? '';
}

function emptyActivity(): ActivityForm {
    return {
        type: 'block_mapping',
        block_mapping: {
            block_id: '',
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
const numericErrors = computed<Record<string, string>>(() => {
    const errors: Record<string, string> = {};
    const workerError = maximumError(
        form.worker_count,
        props.numericLimits.workerCount,
        'Jumlah pekerja',
    );
    if (workerError) errors.worker_count = workerError;
    form.activities.forEach((activity, index) => {
        const mapping = activity.block_mapping;
        const checks: [keyof typeof mapping, number, string, string][] = [
            ['length_m', props.numericLimits.length, 'Panjang', 'm'],
            ['width_m', props.numericLimits.length, 'Lebar', 'm'],
            [
                'row_spacing_cm',
                props.numericLimits.spacing,
                'Jarak antarbaris',
                'cm',
            ],
            [
                'plant_spacing_cm',
                props.numericLimits.spacing,
                'Jarak tanam',
                'cm',
            ],
            [
                'rows_per_bed',
                props.numericLimits.rows,
                'Baris tanaman per bedeng',
                '',
            ],
        ];
        for (const [key, maximum, label, unit] of checks) {
            const error = maximumError(mapping[key], maximum, label, unit);
            if (error)
                errors[`activities.${index}.block_mapping.${key}`] = error;
        }
    });
    return errors;
});

const filterForm = useForm({
    from_date: String(props.filters.from_date ?? ''),
    to_date: String(props.filters.to_date ?? ''),
});

function fieldError(name: string): string | undefined {
    return (
        numericErrors.value[name] ??
        (form.errors as Record<string, string | undefined>)[name]
    );
}

function clearFieldError(name: string): void {
    form.clearErrors(name as keyof typeof form.errors);
}

function addActivity(): void {
    form.activities.push(emptyActivity());
}

function removeActivity(index: number): void {
    if (form.activities.length === 1) return;

    form.activities.splice(index, 1);
}

function submit(): void {
    if (
        !headerReady.value ||
        form.processing ||
        Object.keys(numericErrors.value).length
    )
        return;
    form.transform((data) =>
        existingHeader.value
            ? {
                  ...data,
                  start_time: undefined,
                  end_time: undefined,
                  notes: undefined,
              }
            : data,
    ).post(`/kebun/${props.farm.id}/daily-logs`, {
        preserveScroll: true,
        onSuccess: () => {
            headerVersion.value++;
            form.reset(
                'start_time',
                'end_time',
                'worker_count',
                'notes',
                'activities',
            );
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

    <component
        :is="
            page.props.navigation?.owner
                ? FarmManagementLayout
                : FarmerMobileShell
        "
        v-bind="page.props.navigation?.owner ? {} : { activeKey: 'garden' }"
    >
        <Link
            v-if="page.props.navigation?.farms"
            :href="`${indexUrl}/${farm.id}`"
            class="mx-4 mt-4 inline-flex min-h-11 items-center gap-2 rounded-lg text-sm font-medium text-foreground underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-ring"
            ><ArrowLeft :size="16" />Kembali ke detail kebun</Link
        >
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
                    <h1
                        class="truncate text-[21px] leading-7 font-semibold text-foreground"
                    >
                        Logbook Harian
                    </h1>
                    <p class="truncate text-sm font-medium text-secondary">
                        {{ farm.name }}
                    </p>
                </div>
            </header>

            <section
                v-if="can.create"
                class="rounded-2xl border border-border bg-card p-4 shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-foreground">
                            Catat kegiatan hari ini
                        </h2>
                        <p class="mt-1 text-xs leading-5 text-muted-foreground">
                            Tambahkan pemetaan ke laporan bersama kebun pada
                            tanggal yang dipilih. Informasi harian yang sudah
                            ada tidak ditimpa.
                        </p>
                    </div>
                    <ClipboardList
                        :size="22"
                        class="shrink-0 text-primary"
                        aria-hidden="true"
                    />
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div class="grid grid-cols-2 gap-3">
                        <label
                            class="space-y-1.5 text-xs font-medium text-foreground"
                        >
                            <span class="flex items-center gap-1.5"
                                ><CalendarDays
                                    :size="15"
                                    class="text-primary"
                                />Tanggal kerja</span
                            >
                            <input
                                v-model="form.work_date"
                                type="date"
                                class="form-input"
                            />
                            <p
                                v-if="fieldError('work_date')"
                                class="form-error"
                            >
                                {{ fieldError('work_date') }}
                            </p>
                        </label>
                    </div>

                    <DailyHeaderContext
                        :key="headerVersion"
                        v-model:start="form.start_time"
                        v-model:end="form.end_time"
                        v-model:notes="form.notes"
                        :farm-id="farm.id"
                        :date="form.work_date"
                        :errors="form.errors"
                        :report-base="`/kebun/${farm.id}/daily-logs`"
                        @ready="headerReady = $event"
                        @existing="existingHeader = $event"
                    />

                    <div class="space-y-3">
                        <label
                            class="block space-y-1.5 text-xs font-medium text-foreground"
                            ><span>Jumlah pekerja untuk kegiatan pemetaan</span
                            ><input
                                v-model.number="form.worker_count"
                                type="number"
                                min="1"
                                :max="numericLimits.workerCount"
                                step="1"
                                class="form-input"
                                :aria-invalid="!!fieldError('worker_count')"
                                aria-describedby="mapping-worker-error"
                                @input="clearFieldError('worker_count')"
                            />
                            <p
                                v-if="fieldError('worker_count')"
                                id="mapping-worker-error"
                                class="form-error"
                                aria-live="polite"
                            >
                                {{ fieldError('worker_count') }}
                            </p></label
                        >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3
                                    class="text-sm font-semibold text-foreground"
                                >
                                    Kegiatan pemetaan blok
                                </h3>
                                <p class="text-xs text-muted-foreground">
                                    Pelapor: {{ recorder.name }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-border px-2.5 py-2 text-xs font-semibold text-secondary hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                @click="addActivity"
                            >
                                <Plus :size="15" /> Tambah kegiatan pemetaan
                            </button>
                        </div>

                        <article
                            v-for="(activity, index) in form.activities"
                            :key="index"
                            class="space-y-3 rounded-xl border border-border bg-muted/30 p-3"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <h4
                                    class="text-sm font-semibold text-foreground"
                                >
                                    Pemetaan {{ index + 1 }}
                                </h4>
                                <button
                                    v-if="form.activities.length > 1"
                                    type="button"
                                    class="rounded-md p-1.5 text-error hover:bg-error/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                    :aria-label="`Hapus kegiatan pemetaan ${index + 1}`"
                                    @click="removeActivity(index)"
                                >
                                    <Trash2 :size="16" />
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <label
                                    class="col-span-2 space-y-1.5 text-xs font-medium text-foreground"
                                >
                                    <span>Blok kebun</span>
                                    <AppSelect
                                        v-model="
                                            activity.block_mapping.block_id
                                        "
                                        class="form-input"
                                        required
                                        @change="selectBlock(activity)"
                                    >
                                        <AppSelectOption value="">
                                            Pilih blok aktif kebun ini
                                        </AppSelectOption>
                                        <AppSelectOption
                                            v-for="block in blocks"
                                            :key="block.id"
                                            :value="block.id"
                                        >
                                            {{ block.code }} · {{ block.name }}
                                        </AppSelectOption>
                                    </AppSelect>
                                    <span
                                        v-if="!blocks.length"
                                        class="block text-xs text-muted-foreground"
                                        >Belum ada blok aktif yang dapat
                                        dipilih. Hubungi Admin untuk menambahkan
                                        atau mengaktifkan blok.</span
                                    >
                                    <span
                                        v-if="
                                            fieldError(
                                                `activities.${index}.block_mapping.block_id`,
                                            )
                                        "
                                        role="alert"
                                        class="form-error"
                                        >{{
                                            fieldError(
                                                `activities.${index}.block_mapping.block_id`,
                                            )
                                        }}</span
                                    >
                                    <p
                                        v-if="
                                            fieldError(
                                                `activities.${index}.block_mapping.block_name`,
                                            )
                                        "
                                        class="form-error"
                                    >
                                        {{
                                            fieldError(
                                                `activities.${index}.block_mapping.block_name`,
                                            )
                                        }}
                                    </p>
                                </label>
                                <label
                                    class="space-y-1.5 text-xs font-medium text-foreground"
                                >
                                    <span>Panjang (m)</span>
                                    <input
                                        v-model="
                                            activity.block_mapping.length_m
                                        "
                                        type="number"
                                        min="0.001"
                                        :max="numericLimits.length"
                                        step="0.001"
                                        class="form-input"
                                        placeholder="35"
                                        :aria-invalid="
                                            !!fieldError(
                                                `activities.${index}.block_mapping.length_m`,
                                            )
                                        "
                                        :aria-describedby="`mapping-${index}-length-error`"
                                        @input="
                                            clearFieldError(
                                                `activities.${index}.block_mapping.length_m`,
                                            )
                                        "
                                    />
                                    <p
                                        v-if="
                                            fieldError(
                                                `activities.${index}.block_mapping.length_m`,
                                            )
                                        "
                                        class="form-error"
                                        :id="`mapping-${index}-length-error`"
                                        aria-live="polite"
                                    >
                                        {{
                                            fieldError(
                                                `activities.${index}.block_mapping.length_m`,
                                            )
                                        }}
                                    </p>
                                </label>
                                <label
                                    class="space-y-1.5 text-xs font-medium text-foreground"
                                >
                                    <span>Lebar (m)</span>
                                    <input
                                        v-model="activity.block_mapping.width_m"
                                        type="number"
                                        min="0.001"
                                        :max="numericLimits.length"
                                        step="0.001"
                                        class="form-input"
                                        placeholder="50"
                                        :aria-invalid="
                                            !!fieldError(
                                                `activities.${index}.block_mapping.width_m`,
                                            )
                                        "
                                        :aria-describedby="`mapping-${index}-width-error`"
                                        @input="
                                            clearFieldError(
                                                `activities.${index}.block_mapping.width_m`,
                                            )
                                        "
                                    />
                                    <p
                                        v-if="
                                            fieldError(
                                                `activities.${index}.block_mapping.width_m`,
                                            )
                                        "
                                        class="form-error"
                                        :id="`mapping-${index}-width-error`"
                                        aria-live="polite"
                                    >
                                        {{
                                            fieldError(
                                                `activities.${index}.block_mapping.width_m`,
                                            )
                                        }}
                                    </p>
                                </label>
                                <label
                                    class="space-y-1.5 text-xs font-medium text-foreground"
                                >
                                    <span>Jarak antarbaris (cm)</span>
                                    <input
                                        v-model="
                                            activity.block_mapping
                                                .row_spacing_cm
                                        "
                                        type="number"
                                        min="0.01"
                                        :max="numericLimits.spacing"
                                        step="0.01"
                                        class="form-input"
                                        placeholder="40"
                                        :aria-invalid="
                                            !!fieldError(
                                                `activities.${index}.block_mapping.row_spacing_cm`,
                                            )
                                        "
                                        :aria-describedby="`mapping-${index}-row-spacing-error`"
                                        @input="
                                            clearFieldError(
                                                `activities.${index}.block_mapping.row_spacing_cm`,
                                            )
                                        "
                                    />
                                    <p
                                        v-if="
                                            fieldError(
                                                `activities.${index}.block_mapping.row_spacing_cm`,
                                            )
                                        "
                                        :id="`mapping-${index}-row-spacing-error`"
                                        class="form-error"
                                        aria-live="polite"
                                    >
                                        {{
                                            fieldError(
                                                `activities.${index}.block_mapping.row_spacing_cm`,
                                            )
                                        }}
                                    </p>
                                </label>
                                <label
                                    class="space-y-1.5 text-xs font-medium text-foreground"
                                >
                                    <span>Jarak tanam (cm)</span>
                                    <input
                                        v-model="
                                            activity.block_mapping
                                                .plant_spacing_cm
                                        "
                                        type="number"
                                        min="0.01"
                                        :max="numericLimits.spacing"
                                        step="0.01"
                                        class="form-input"
                                        placeholder="20"
                                        :aria-invalid="
                                            !!fieldError(
                                                `activities.${index}.block_mapping.plant_spacing_cm`,
                                            )
                                        "
                                        :aria-describedby="`mapping-${index}-plant-spacing-error`"
                                        @input="
                                            clearFieldError(
                                                `activities.${index}.block_mapping.plant_spacing_cm`,
                                            )
                                        "
                                    />
                                    <p
                                        v-if="
                                            fieldError(
                                                `activities.${index}.block_mapping.plant_spacing_cm`,
                                            )
                                        "
                                        :id="`mapping-${index}-plant-spacing-error`"
                                        class="form-error"
                                        aria-live="polite"
                                    >
                                        {{
                                            fieldError(
                                                `activities.${index}.block_mapping.plant_spacing_cm`,
                                            )
                                        }}
                                    </p>
                                </label>
                                <label
                                    class="col-span-2 space-y-1.5 text-xs font-medium text-foreground"
                                >
                                    <span
                                        >Baris tanaman per bedeng
                                        <span
                                            class="font-normal text-muted-foreground"
                                            >(opsional)</span
                                        ></span
                                    >
                                    <input
                                        v-model="
                                            activity.block_mapping.rows_per_bed
                                        "
                                        type="number"
                                        min="1"
                                        :max="numericLimits.rows"
                                        step="1"
                                        class="form-input"
                                        placeholder="2"
                                        :aria-invalid="
                                            !!fieldError(
                                                `activities.${index}.block_mapping.rows_per_bed`,
                                            )
                                        "
                                        :aria-describedby="`mapping-${index}-rows-error`"
                                        @input="
                                            clearFieldError(
                                                `activities.${index}.block_mapping.rows_per_bed`,
                                            )
                                        "
                                    />
                                    <p
                                        v-if="
                                            fieldError(
                                                `activities.${index}.block_mapping.rows_per_bed`,
                                            )
                                        "
                                        :id="`mapping-${index}-rows-error`"
                                        class="form-error"
                                        aria-live="polite"
                                    >
                                        {{
                                            fieldError(
                                                `activities.${index}.block_mapping.rows_per_bed`,
                                            )
                                        }}
                                    </p>
                                </label>
                            </div>

                            <label
                                class="space-y-1.5 text-xs font-medium text-foreground"
                            >
                                <span
                                    >Catatan kegiatan
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >(opsional)</span
                                    ></span
                                >
                                <textarea
                                    v-model="activity.notes"
                                    rows="2"
                                    class="form-input resize-y"
                                    placeholder="Hasil atau catatan pengukuran blok."
                                />
                            </label>

                            <div class="grid gap-3">
                                <label
                                    class="space-y-1.5 text-xs font-medium text-foreground"
                                >
                                    <span>Status kendala</span>
                                    <AppSelect
                                        v-model="activity.obstacle_status"
                                        class="form-input"
                                    >
                                        <AppSelectOption value="none">
                                            Tidak ada kendala
                                        </AppSelectOption>
                                        <AppSelectOption value="reported">
                                            Ada kendala
                                        </AppSelectOption>
                                    </AppSelect>
                                </label>
                                <label
                                    v-if="
                                        activity.obstacle_status === 'reported'
                                    "
                                    class="space-y-1.5 text-xs font-medium text-foreground"
                                >
                                    <span>Uraian kendala</span>
                                    <textarea
                                        v-model="activity.obstacles"
                                        rows="2"
                                        class="form-input resize-y"
                                        placeholder="Jelaskan kendala di lapangan."
                                    />
                                </label>
                                <label
                                    class="space-y-1.5 text-xs font-medium text-foreground"
                                >
                                    <span
                                        >Solusi yang diusulkan
                                        <span
                                            class="font-normal text-muted-foreground"
                                            >(opsional)</span
                                        ></span
                                    >
                                    <textarea
                                        v-model="activity.proposed_solution"
                                        rows="2"
                                        class="form-input resize-y"
                                        placeholder="Tuliskan solusi atau penyesuaian."
                                    />
                                </label>
                                <label
                                    class="space-y-1.5 text-xs font-medium text-foreground"
                                >
                                    <span
                                        >Tindakan yang dilakukan
                                        <span
                                            class="font-normal text-muted-foreground"
                                            >(opsional)</span
                                        ></span
                                    >
                                    <textarea
                                        v-model="activity.action_taken"
                                        rows="2"
                                        class="form-input resize-y"
                                        placeholder="Tuliskan tindakan di lapangan."
                                    />
                                </label>
                            </div>
                        </article>
                    </div>

                    <div
                        v-if="Object.keys(form.errors).length"
                        class="rounded-xl border border-error/25 bg-error/10 px-3 py-2.5 text-xs text-error"
                        role="alert"
                    >
                        Periksa kembali isian yang ditandai sebelum menyimpan
                        logbook.
                    </div>

                    <button
                        type="submit"
                        class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition-colors hover:bg-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="
                            form.processing ||
                            !headerReady ||
                            Object.keys(numericErrors).length > 0
                        "
                    >
                        <Save :size="17" />
                        {{
                            form.processing ? 'Menyimpan...' : 'Simpan logbook'
                        }}
                    </button>
                </form>
            </section>

            <section
                class="rounded-2xl border border-border bg-card p-4 shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-foreground">
                            Riwayat logbook
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ logs.meta.total }} laporan tersimpan untuk kebun
                            ini.
                        </p>
                    </div>
                    <ClipboardList
                        :size="22"
                        class="shrink-0 text-primary"
                        aria-hidden="true"
                    />
                </div>

                <form
                    class="mt-4 grid grid-cols-2 gap-3"
                    @submit.prevent="applyFilters"
                >
                    <label
                        class="space-y-1.5 text-xs font-medium text-foreground"
                    >
                        <span>Dari tanggal</span>
                        <input
                            v-model="filterForm.from_date"
                            type="date"
                            class="form-input"
                        />
                    </label>
                    <label
                        class="space-y-1.5 text-xs font-medium text-foreground"
                    >
                        <span>Sampai tanggal</span>
                        <input
                            v-model="filterForm.to_date"
                            type="date"
                            class="form-input"
                        />
                    </label>
                    <div class="col-span-2 flex gap-2">
                        <button
                            type="submit"
                            class="inline-flex h-9 flex-1 items-center justify-center rounded-lg bg-secondary px-3 text-xs font-semibold text-secondary-foreground hover:bg-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            Terapkan filter
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center rounded-lg border border-border px-3 text-xs font-semibold text-foreground hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                            @click="clearFilters"
                        >
                            Reset
                        </button>
                    </div>
                </form>

                <div
                    v-if="logs.data.length === 0"
                    class="mt-5 rounded-xl border border-dashed border-border bg-muted/30 px-4 py-8 text-center"
                >
                    <ClipboardList
                        :size="30"
                        class="mx-auto text-primary"
                        aria-hidden="true"
                    />
                    <h3 class="mt-3 text-sm font-semibold text-foreground">
                        Belum ada logbook
                    </h3>
                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                        Simpan laporan kegiatan pertama untuk melihat riwayatnya
                        di sini.
                    </p>
                </div>

                <div v-else class="mt-5 space-y-3">
                    <article
                        v-for="log in logs.data"
                        :key="log.id"
                        class="rounded-xl border border-border bg-muted/20 p-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p
                                    class="text-sm font-semibold text-foreground"
                                >
                                    {{ log.work_date }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ log.start_time }}-{{
                                        log.end_time ?? 'Belum diisi'
                                    }}
                                    WIB
                                </p>
                            </div>
                            <Link
                                :href="detailUrl(log)"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-secondary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                            >
                                Detail <ChevronRight :size="15" />
                            </Link>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <span
                                v-for="activity in log.activities"
                                :key="activity.id"
                                class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-1 text-[11px] font-medium text-secondary"
                            >
                                <span
                                    class="size-1.5 rounded-full bg-primary"
                                />{{
                                    activity.block_mapping?.block_name ??
                                    activity.name ??
                                    'Aktivitas'
                                }}<template
                                    v-if="activity.worker_count !== null"
                                >
                                    ·
                                    {{ activity.worker_count }}
                                    pekerja</template
                                >
                            </span>
                        </div>
                        <p
                            v-if="log.notes"
                            class="mt-3 line-clamp-2 text-xs leading-5 text-muted-foreground"
                        >
                            {{ log.notes }}
                        </p>
                    </article>
                </div>

                <div
                    v-if="logs.meta.last_page > 1"
                    class="mt-4 flex items-center justify-between border-t border-border pt-3"
                >
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-border px-2.5 py-2 text-xs font-semibold text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="!logs.links.prev"
                        @click="goToPage(logs.links.prev)"
                    >
                        <ChevronLeft :size="15" /> Sebelumnya
                    </button>
                    <span class="text-xs text-muted-foreground"
                        >Halaman {{ logs.meta.current_page }} dari
                        {{ logs.meta.last_page }}</span
                    >
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-border px-2.5 py-2 text-xs font-semibold text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="!logs.links.next"
                        @click="goToPage(logs.links.next)"
                    >
                        Berikutnya <ChevronRight :size="15" />
                    </button>
                </div>
            </section>
        </div>
    </component>
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
