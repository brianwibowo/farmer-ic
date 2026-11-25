<script setup lang="ts">
import PageBackButton from '@/components/PageBackButton.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    ArrowRight,
    CalendarDays,
    ChevronDown,
    ClipboardList,
    FileText,
    LoaderCircle,
    MapPin,
    Save,
    ShieldAlert,
    SquarePen,
} from '@lucide/vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import ActivityDetailFields, {
    type ActivityField,
} from '@/components/petugas/ActivityDetailFields.vue';
import ActivityPhotoPicker from '@/components/petugas/ActivityPhotoPicker.vue';
import { usePhotoCompression } from '@/composables/usePhotoCompression';
import ActivityWorkerCount from '@/components/petugas/ActivityWorkerCount.vue';
import { Button } from '@/components/ui/button';
import { useErrorDisclosure } from '@/composables/useErrorDisclosure';
import { useVoluntaryLocation } from '@/composables/useVoluntaryLocation';
import {
    extractPetugasBackParam,
    petugasBackLabel,
    resolvePetugasBackHref,
    withPetugasBack,
} from '@/lib/petugasBackLink';
import {
    activityDetailMaximumErrors,
    maximumError,
} from '@/lib/activityInputLimits';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    farm: { id: string; name: string };
    type: { id: string; code: string; name: string };
    today: string;
    fields: ActivityField[];
    numericLimits: { workerCount: number; quantityKg: number };
    blocks: {
        id: string;
        name: string;
        area_limit_m2: number;
        blocked_reason: string | null;
    }[];
    cycles: {
        id: string;
        subblock_label: string;
        block: { id: string; name: string };
        reserved_area_ha: string;
        area_limit_m2: number;
        blocked_reason: string | null;
    }[];
    matchingTasks: {
        id: string;
        planting_cycle_id: string;
        name: string;
        scheduled_date: string | null;
    }[];
    incidentTicket?: {
        id: string;
        incident_category: string | null;
        severity: 'light' | 'medium' | 'emergency';
        severity_label: string;
        description: string | null;
        admin_note: string | null;
        scope: 'cycle' | 'block' | 'farm';
        cycle_id: string | null;
        block_id: string | null;
        location_label: string;
    } | null;
}>();
const lockedTicketCycle = computed(() =>
    props.incidentTicket?.scope === 'cycle'
        ? props.cycles.find((c) => c.id === props.incidentTicket?.cycle_id)
        : undefined,
);
const form = useForm({
    cycle_id:
        props.incidentTicket?.scope === 'cycle'
            ? (props.incidentTicket.cycle_id ?? '')
            : '',
    block_id:
        props.incidentTicket?.scope === 'block'
            ? (props.incidentTicket.block_id ?? '')
            : (lockedTicketCycle.value?.block.id ?? ''),
    work_date: props.today,
    worker_count: 1,
    notes: '',
    details: Object.fromEntries(
        props.fields.map((f) => [
            f.key,
            f.key === 'area_unit'
                ? 'm²'
                : f.key === 'quantity_unit' || f.key === 'weight_unit'
                  ? 'kg'
                  : f.key === 'incident_status'
                    ? 'Perlu penanganan'
                    : '',
        ]),
    ) as Record<string, string>,
    photos: [] as File[],
});
const clientError = ref('');
const isIncident = computed(() => props.type.code === 'incident');
const showIncidentWorkerCount = computed(
    () =>
        isIncident.value &&
        form.details.incident_status === 'Sudah ditangani langsung',
);
const incidentWorkerCount = ref<string | number>('');
const blockOnly = computed(() =>
    ['land_cleaning', 'soil_preparation', 'base_fertilization'].includes(
        props.type.code,
    ),
);
const optionalLocation = computed(() =>
    ['incident', 'other'].includes(props.type.code),
);
const { availableCycles, areaLimitM2, blockedReason, submittedBlockId } =
    useVoluntaryLocation(
        form,
        () => optionalLocation.value,
        () => props.blocks,
        () => props.cycles,
    );
const numericErrors = computed<Record<string, string>>(() => {
    const result = activityDetailMaximumErrors(
        props.fields,
        form.details,
        areaLimitM2.value,
        props.numericLimits.quantityKg,
    );
    const workerError = !isIncident.value
        ? maximumError(
              form.worker_count,
              props.numericLimits.workerCount,
              'Jumlah pekerja',
          )
        : showIncidentWorkerCount.value
          ? maximumError(
                incidentWorkerCount.value,
                props.numericLimits.workerCount,
                'Jumlah pekerja',
            )
          : undefined;
    if (workerError) result.worker_count = workerError;
    return result;
});
const errors = computed<Record<string, string>>(() => ({
    ...form.errors,
    ...numericErrors.value,
}));
const locationTicketError = computed(
    () =>
        errors.value.incident_ticket_id ||
        errors.value.cycle_id ||
        errors.value.block_id,
);
const photoErrors = computed(() =>
    Object.entries(form.errors).filter(
        ([key]) => key === 'photos' || key.startsWith('photos.'),
    ),
);
const { open: notesOpen, onToggle: syncNotesOpen } = useErrorDisclosure(
    () => !!errors.value.notes,
);
const noArea = computed(
    () =>
        areaLimitM2.value === 0 &&
        props.fields.some(
            (field) =>
                (field.key === 'area' && !field.optional) ||
                field.key === 'bed_count',
        ),
);
watch(
    () => ({ ...form.details }),
    (current, previous) => {
        for (const key of Object.keys(current)) {
            if (current[key] === previous[key]) continue;
            form.clearErrors(`details.${key}` as keyof typeof form.errors);
            if (key === 'area_unit')
                form.clearErrors('details.area' as keyof typeof form.errors);
            if (['bed_count', 'length_m', 'width_m'].includes(key))
                form.clearErrors('details.area' as keyof typeof form.errors);
            if (key === 'quantity_unit' || key === 'bag_size')
                form.clearErrors(
                    'details.quantity' as keyof typeof form.errors,
                );
        }
    },
);
watch(
    () => form.worker_count,
    () => form.clearErrors('worker_count'),
);
watch(incidentWorkerCount, () => form.clearErrors('worker_count'));
watch(
    () => form.details.incident_status,
    (status) => {
        if (status !== 'Sudah ditangani langsung') {
            incidentWorkerCount.value = '';
            form.clearErrors('worker_count');
        }
    },
);
watch(
    () => form.work_date,
    () => form.clearErrors('work_date'),
);
watch(
    () => form.notes,
    () => form.clearErrors('notes'),
);
watch(areaLimitM2, () =>
    form.clearErrors('details.area' as keyof typeof form.errors),
);
const matches = computed(() =>
    props.matchingTasks.filter((t) => t.planting_cycle_id === form.cycle_id),
);
function clearPhotoErrors(): void {
    clientError.value = '';
    for (const key of Object.keys(form.errors)) {
        if (key === 'photos' || key.startsWith('photos.'))
            form.clearErrors(key as keyof typeof form.errors);
    }
}
const { compressing, compressionStatus, fallbackNotice, preparePhotos } =
    usePhotoCompression(() => form.photos);
async function photos(event: Event): Promise<void> {
    if (compressing.value || form.processing) return;
    const input = event.target as HTMLInputElement;
    const files = [...(input.files ?? [])];
    clientError.value = '';
    if (
        files.length + form.photos.length > 5 ||
        files.some(
            (f) =>
                f.size > 15728640 ||
                !['image/jpeg', 'image/png', 'image/webp'].includes(f.type),
        )
    )
        clientError.value =
            'Pilih maksimal 5 foto JPG/PNG/WebP, masing-masing maksimal 15 MB.';
    else {
        try {
            form.photos.push(...(await preparePhotos(files)));
            clearPhotoErrors();
        } catch (error) {
            clientError.value =
                error instanceof Error
                    ? error.message
                    : 'Foto gagal diproses. Pilih ulang foto.';
        }
    }
    input.value = '';
}
function removePhoto(index: number): void {
    if (compressing.value || form.processing) return;
    form.photos.splice(index, 1);
    clearPhotoErrors();
}
function scheduledDateLabel(value: string | null): string {
    if (!value) return 'Belum dijadwalkan';
    const input = /^\d{4}-\d{2}-\d{2}$/.test(value)
        ? `${value}T12:00:00+07:00`
        : value;
    const date = new Date(input);
    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('id-ID', {
              dateStyle: 'medium',
              timeZone: 'Asia/Jakarta',
          }).format(date);
}
function submit(): void {
    if (
        form.processing ||
        compressing.value ||
        blockedReason.value ||
        noArea.value ||
        Object.keys(numericErrors.value).length
    )
        return;
    clientError.value = '';
    if (!form.photos.length) {
        clientError.value = 'Tambahkan minimal satu foto dokumentasi.';
        return;
    }
    form.transform((data) => {
        const { worker_count, ...rest } = data;
        const details = Object.fromEntries(
            Object.entries(data.details).filter(([key]) => {
                const f = props.fields.find((f) => f.key === key);
                return !f?.when || data.details[f.when[0]] === f.when[1];
            }),
        );
        return {
            ...rest,
            ...(!isIncident.value
                ? { worker_count }
                : showIncidentWorkerCount.value &&
                    String(incidentWorkerCount.value ?? '').trim() !== ''
                  ? { worker_count: Number(incidentWorkerCount.value) }
                  : {}),
            cycle_id: props.incidentTicket
                ? props.incidentTicket.scope === 'cycle'
                    ? (props.incidentTicket.cycle_id ?? '')
                    : ''
                : data.cycle_id,
            block_id: props.incidentTicket
                ? props.incidentTicket.scope === 'block'
                    ? (props.incidentTicket.block_id ?? '')
                    : ''
                : submittedBlockId(data.cycle_id, data.block_id),
            incident_ticket_id: props.incidentTicket?.id ?? null,
            details,
        };
    }).post(`/petugas/aktivitas/mandiri/${props.type.code}`, {
        forceFormData: true,
        preserveScroll: true,
    });
}
const page = usePage();
const backHref = computed(() =>
    props.incidentTicket
        ? withPetugasBack(
              `/petugas/incidents/${props.incidentTicket.id}`,
              extractPetugasBackParam(page.url),
          )
        : resolvePetugasBackHref(page.url, '/petugas/laporan'),
);
const backLabel = computed(() =>
    props.incidentTicket
        ? 'Kembali ke tiket insiden'
        : petugasBackLabel(backHref.value, 'Kembali ke laporan'),
);
</script>
<template>
    <Head :title="`Catat ${type.name}`" />
    <div class="min-w-0 space-y-[18px] text-foreground">
        <header>
            <div class="flex min-w-0 items-start gap-2">
                <PageBackButton :href="backHref" :label="backLabel" />
                <h1 class="text-xl leading-7 font-semibold">
                    {{
                        incidentTicket
                            ? `Tindak lanjuti insiden · ${type.name}`
                            : 'Catat aktivitas'
                    }}
                </h1>
            </div>
            <p class="mt-1 text-sm leading-5 text-muted-foreground">
                {{
                    incidentTicket
                        ? 'Menyimpan aktivitas ini akan masuk ke logbook harian dan otomatis menyelesaikan tiket insiden.'
                        : 'Laporkan pekerjaan mandiri di kebun.'
                }}
            </p>
        </header>
        <section
            v-if="incidentTicket"
            class="petugas-card space-y-2 border border-warning/35 bg-warning/10 p-4"
            aria-label="Ringkasan tiket insiden yang ditindaklanjuti"
        >
            <div class="flex items-center justify-between gap-2">
                <p
                    class="flex items-center gap-2 text-xs font-semibold text-warning"
                >
                    <ShieldAlert class="size-4 shrink-0" aria-hidden="true" />
                    Tindak lanjut tiket insiden
                </p>
                <span
                    class="rounded-full bg-card px-2.5 py-0.5 text-[11px] font-semibold text-foreground"
                    >{{ incidentTicket.severity_label }}</span
                >
            </div>
            <p class="text-sm font-semibold text-foreground">
                {{ incidentTicket.incident_category || 'Insiden lahan' }}
            </p>
            <p
                v-if="incidentTicket.description"
                class="text-xs leading-5 break-words text-muted-foreground"
            >
                {{ incidentTicket.description }}
            </p>
            <p
                v-if="incidentTicket.admin_note"
                class="rounded-xl bg-card/80 p-2.5 text-xs leading-5 text-foreground"
            >
                <span class="font-semibold">Arahan Admin:</span>
                {{ incidentTicket.admin_note }}
            </p>
        </section>
        <section
            class="petugas-card overflow-hidden"
            aria-label="Aktivitas yang dilaporkan"
        >
            <div class="flex items-center gap-3 bg-primary/5 p-4">
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <ClipboardList class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-primary">
                        {{
                            incidentTicket
                                ? 'Aktivitas tindak lanjut insiden'
                                : 'Aktivitas mandiri'
                        }}
                    </p>
                    <p class="mt-0.5 text-base leading-6 font-bold">
                        {{ type.name }}
                    </p>
                    <p
                        class="mt-1 flex items-start gap-1 text-xs leading-5 text-muted-foreground"
                    >
                        <MapPin
                            class="mt-0.5 size-3.5 shrink-0"
                            aria-hidden="true"
                        />
                        <span>{{ farm.name }}</span>
                    </p>
                </div>
            </div>
        </section>
        <form class="space-y-4" @submit.prevent="submit">
            <fieldset
                :disabled="form.processing"
                class="space-y-4 disabled:opacity-70"
            >
                <section class="petugas-card space-y-4 p-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                        >
                            <CalendarDays class="size-5" aria-hidden="true" />
                        </span>
                        <h2 class="text-base font-semibold">
                            Waktu pelaksanaan
                        </h2>
                    </div>
                    <label class="block space-y-2 text-sm">
                        <span class="font-medium">Tanggal kerja *</span>
                        <input
                            v-model="form.work_date"
                            type="date"
                            required
                            :max="today"
                            class="petugas-activity-field block [color-scheme:light] dark:[color-scheme:dark]"
                            :aria-invalid="!!errors.work_date"
                            :aria-describedby="
                                errors.work_date
                                    ? 'voluntary-date-error'
                                    : undefined
                            "
                        />
                        <span
                            v-if="errors.work_date"
                            id="voluntary-date-error"
                            class="block text-xs text-error"
                            >{{ errors.work_date }}</span
                        >
                    </label>
                </section>
                <section class="petugas-card space-y-4 p-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                        >
                            <MapPin class="size-5" aria-hidden="true" />
                        </span>
                        <h2 class="text-base font-semibold">
                            Lokasi pekerjaan
                        </h2>
                    </div>
                    <template v-if="incidentTicket">
                        <div
                            class="space-y-1.5 rounded-xl border border-border bg-muted/40 p-3"
                        >
                            <p
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                Lokasi dikunci sesuai tiket insiden
                            </p>
                            <p class="text-sm font-semibold text-foreground">
                                {{ incidentTicket.location_label }}
                            </p>
                        </div>
                        <p
                            v-if="locationTicketError"
                            role="alert"
                            class="text-xs text-error"
                        >
                            {{ locationTicketError }}
                        </p>
                    </template>
                    <template v-else>
                        <div
                            v-if="blockOnly || optionalLocation"
                            class="space-y-2"
                        >
                            <label
                                for="voluntary-block"
                                class="text-sm font-medium"
                            >
                                Blok {{ blockOnly ? '*' : '(opsional)' }}
                            </label>
                            <AppSelect
                                id="voluntary-block"
                                v-model="form.block_id"
                                :required="blockOnly"
                                :disabled="form.processing"
                                aria-label="Blok"
                                placeholder="Pilih blok"
                                class="petugas-activity-field"
                                :aria-invalid="!!errors.block_id"
                                :aria-describedby="
                                    errors.block_id
                                        ? 'voluntary-block-error'
                                        : undefined
                                "
                            >
                                <AppSelectOption
                                    v-if="optionalLocation"
                                    value=""
                                    >Tidak dipilih</AppSelectOption
                                >
                                <AppSelectOption
                                    v-for="block in blocks"
                                    :key="block.id"
                                    :value="block.id"
                                    >{{ block.name }}</AppSelectOption
                                >
                            </AppSelect>
                            <p
                                v-if="errors.block_id"
                                id="voluntary-block-error"
                                class="text-xs text-error"
                                aria-live="polite"
                            >
                                {{ errors.block_id }}
                            </p>
                        </div>
                        <div v-if="!blockOnly" class="space-y-2">
                            <label
                                for="voluntary-cycle"
                                class="text-sm font-medium"
                                >{{
                                    optionalLocation
                                        ? 'Subblok (opsional)'
                                        : 'Blok / subblok *'
                                }}</label
                            >
                            <AppSelect
                                id="voluntary-cycle"
                                v-model="form.cycle_id"
                                :required="!optionalLocation"
                                :disabled="
                                    form.processing ||
                                    (optionalLocation &&
                                        (!form.block_id ||
                                            !availableCycles.length))
                                "
                                :aria-label="
                                    optionalLocation
                                        ? 'Subblok'
                                        : 'Blok / subblok'
                                "
                                placeholder="Pilih subblok"
                                class="petugas-activity-field"
                                :aria-invalid="!!errors.cycle_id"
                                :aria-describedby="
                                    [
                                        optionalLocation
                                            ? 'voluntary-cycle-help'
                                            : null,
                                        errors.cycle_id
                                            ? 'voluntary-cycle-error'
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' ') || undefined
                                "
                                ><AppSelectOption
                                    v-if="optionalLocation"
                                    value=""
                                    >{{
                                        form.block_id
                                            ? 'Seluruh blok'
                                            : 'Pilih blok terlebih dahulu'
                                    }}</AppSelectOption
                                ><AppSelectOption
                                    v-for="cycle in availableCycles"
                                    :key="cycle.id"
                                    :value="cycle.id"
                                    >{{
                                        optionalLocation
                                            ? cycle.subblock_label
                                            : `${cycle.block.name} · ${cycle.subblock_label}`
                                    }}</AppSelectOption
                                ></AppSelect
                            >
                            <p
                                v-if="optionalLocation"
                                id="voluntary-cycle-help"
                                class="text-xs leading-5 text-muted-foreground"
                                aria-live="polite"
                            >
                                <!-- {{
                                !form.block_id
                                    ? 'Pilih blok terlebih dahulu untuk memilih subblok.'
                                    : !availableCycles.length
                                      ? 'Belum ada subblok aktif pada blok ini.'
                                      : 'Pilih subblok atau biarkan kosong untuk seluruh blok.'
                            }} -->
                            </p>
                            <p
                                v-if="errors.cycle_id"
                                id="voluntary-cycle-error"
                                class="text-xs text-error"
                                aria-live="polite"
                            >
                                {{ errors.cycle_id }}
                            </p>
                        </div>
                        <p
                            v-if="optionalLocation"
                            class="text-xs leading-5 text-muted-foreground"
                        >
                            Tanpa blok, aktivitas berlaku untuk seluruh kebun.
                            Tanpa subblok, aktivitas berlaku untuk seluruh blok
                            yang dipilih.
                        </p>
                        <p
                            v-if="
                                blockOnly
                                    ? !blocks.length
                                    : !cycles.length && !optionalLocation
                            "
                            class="text-xs text-muted-foreground"
                        >
                            Belum ada
                            {{ blockOnly ? 'blok' : 'subblok' }} aktif. Hubungi
                            Admin untuk menyiapkannya.
                        </p>
                        <p
                            v-if="blockedReason"
                            role="status"
                            class="rounded-xl border border-warning/40 bg-warning/10 p-3 text-sm"
                        >
                            {{ blockedReason }}
                        </p>
                        <div
                            v-if="matches.length"
                            class="rounded-xl border border-primary/25 bg-primary/10 p-3"
                        >
                            <p class="text-xs leading-5">
                                Ada tugas SOP sejenis untuk subblok ini. Buka
                                tugas jika pekerjaan ini merupakan
                                pelaksanaannya, atau lanjutkan sebagai aktivitas
                                mandiri.
                            </p>
                            <Link
                                v-for="task in matches"
                                :key="task.id"
                                :href="`/petugas/tasks/${task.id}`"
                                class="mt-1 flex min-h-11 items-center gap-2 rounded-xl text-sm font-medium text-primary underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                >{{ task.name }} ·
                                {{ scheduledDateLabel(task.scheduled_date) }}
                                <ArrowRight
                                    class="size-4 shrink-0"
                                    aria-hidden="true"
                            /></Link>
                        </div>
                    </template>
                </section>
                <section class="petugas-card space-y-4 p-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-info/10 text-info"
                        >
                            <SquarePen class="size-5" aria-hidden="true" />
                        </span>
                        <h2 class="text-base font-semibold">Hasil pekerjaan</h2>
                    </div>
                    <ActivityWorkerCount
                        v-if="!isIncident"
                        v-model="form.worker_count"
                        :maximum="numericLimits.workerCount"
                        :error="errors.worker_count"
                    />
                    <ActivityDetailFields
                        v-model="form.details"
                        :fields="fields"
                        :errors="errors"
                        :area-limit-m2="areaLimitM2"
                        compact
                    />
                    <div v-if="showIncidentWorkerCount" class="space-y-2">
                        <label
                            for="activity-worker-count"
                            class="text-sm font-medium"
                        >
                            Jumlah pekerja (opsional)
                        </label>
                        <input
                            id="activity-worker-count"
                            v-model="incidentWorkerCount"
                            type="number"
                            inputmode="numeric"
                            min="1"
                            :max="numericLimits.workerCount"
                            step="1"
                            placeholder="Kosongkan jika tidak perlu"
                            class="petugas-activity-field block"
                            :aria-invalid="!!errors.worker_count"
                            :aria-describedby="
                                errors.worker_count
                                    ? 'error-worker_count'
                                    : undefined
                            "
                        />
                        <p
                            v-if="errors.worker_count"
                            id="error-worker_count"
                            class="text-xs text-error"
                            aria-live="polite"
                        >
                            {{ errors.worker_count }}
                        </p>
                    </div>
                    <p v-if="noArea" role="status" class="text-sm text-warning">
                        Tidak ada luas yang dapat dilaporkan untuk lokasi ini.
                    </p>
                    <p v-if="errors.details" class="text-sm text-error">
                        {{ errors.details }}
                    </p>
                    <p
                        v-if="errors['details.bed_area']"
                        class="text-sm text-error"
                        aria-live="polite"
                    >
                        {{ errors['details.bed_area'] }}
                    </p>
                </section>
                <section class="petugas-card space-y-4 p-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                        >
                            <FileText class="size-5" aria-hidden="true" />
                        </span>
                        <h2 class="text-base font-semibold">Bukti pekerjaan</h2>
                    </div>
                    <ActivityPhotoPicker
                        :busy="compressing || form.processing"
                        :status="compressionStatus"
                        :notice="fallbackNotice"
                        :photos="form.photos"
                        :invalid="!!clientError || photoErrors.length > 0"
                        @select="photos"
                        @remove="removePhoto"
                    />
                    <div
                        v-if="clientError || photoErrors.length"
                        id="photo-errors"
                        role="alert"
                        class="space-y-1"
                    >
                        <p v-if="clientError" class="text-xs text-error">
                            {{ clientError }}
                        </p>
                        <p
                            v-for="[key, message] in photoErrors"
                            :key="key"
                            class="text-xs text-error"
                        >
                            {{ message }}
                        </p>
                    </div>
                    <details
                        class="group rounded-xl bg-muted/50"
                        :open="notesOpen"
                        @toggle="syncNotesOpen"
                    >
                        <summary
                            class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-xl px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden"
                        >
                            <FileText
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span class="flex-1">Tambah catatan</span>
                            <ChevronDown
                                class="size-4 text-muted-foreground transition-transform group-open:rotate-180 motion-reduce:transition-none"
                                aria-hidden="true"
                            />
                        </summary>
                        <div class="space-y-2 px-3 pb-3">
                            <label
                                for="voluntary-notes"
                                class="block text-sm font-medium"
                            >
                                Catatan (opsional)
                            </label>
                            <textarea
                                id="voluntary-notes"
                                v-model="form.notes"
                                rows="3"
                                maxlength="5000"
                                placeholder="Metode, bahan, dosis, atau kondisi lapangan..."
                                class="petugas-activity-field block"
                                :aria-invalid="!!errors.notes"
                                :aria-describedby="
                                    errors.notes
                                        ? 'voluntary-notes-error'
                                        : undefined
                                "
                            />
                            <p
                                v-if="errors.notes"
                                id="voluntary-notes-error"
                                class="text-xs text-error"
                            >
                                {{ errors.notes }}
                            </p>
                        </div>
                    </details>
                </section>
            </fieldset>
            <div
                v-if="clientError || Object.keys(form.errors).length"
                role="alert"
                class="space-y-1 rounded-xl border border-error/30 bg-error/10 p-3 text-sm"
            >
                <p>Periksa isian yang ditandai sebelum menyimpan.</p>
                <p v-for="(error, key) in form.errors" :key="key">
                    {{ error }}
                </p>
            </div>
            <Button
                :disabled="
                    form.processing ||
                    compressing ||
                    !!blockedReason ||
                    noArea ||
                    Object.keys(numericErrors).length > 0
                "
                type="submit"
                class="min-h-12 w-full rounded-xl"
            >
                <LoaderCircle
                    v-if="form.processing"
                    class="size-4 animate-spin motion-reduce:animate-none"
                    aria-hidden="true"
                /><Save v-else class="size-4" aria-hidden="true" />{{
                    form.processing
                        ? `Menyimpan ${form.progress?.percentage ?? ''}…`
                        : 'Simpan laporan'
                }}
            </Button>
        </form>
    </div>
</template>
