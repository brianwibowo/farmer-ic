<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { LoaderCircle, X } from '@lucide/vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import DailyHeaderContext from '@/components/petugas/DailyHeaderContext.vue';
import ActivityDetailFields, {
    type ActivityField,
} from '@/components/petugas/ActivityDetailFields.vue';
import {
    activityDetailMaximumErrors,
    formatActivityNumber,
    maximumError,
} from '@/lib/activityInputLimits';
const props = defineProps<{
    farm: { id: string; name: string };
    type: { id: string; code: string; name: string };
    today: string;
    fields: ActivityField[];
    numericLimits: { workerCount: number; quantityKg: number };
    blocks: {
        id: string;
        code: string;
        name: string;
        area_limit_m2: number;
        blocked_reason: string | null;
    }[];
    cycles: {
        id: string;
        subblock_label: string;
        block: { name: string };
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
}>();
const form = useForm({
    cycle_id: '',
    block_id: '',
    work_date: props.today,
    start_time: '07:00',
    end_time: '',
    worker_count: 1,
    daily_notes: '',
    notes: '',
    details: Object.fromEntries(
        props.fields.map((f) => [
            f.key,
            f.key === 'area_unit'
                ? 'm²'
                : f.key === 'quantity_unit' || f.key === 'weight_unit'
                  ? 'kg'
                  : '',
        ]),
    ) as Record<string, string>,
    photos: [] as File[],
});
const ready = ref(false);
const existing = ref(false);
const clientError = ref('');
const blockOnly = computed(() =>
    [
        'land_cleaning',
        'loosening',
        'beds',
        'liming',
        'base_fertilization',
    ].includes(props.type.code),
);
const optionalLocation = computed(() =>
    ['incident', 'other'].includes(props.type.code),
);
watch(
    () => form.cycle_id,
    (value) => {
        if (value) form.block_id = '';
    },
);
watch(
    () => form.block_id,
    (value) => {
        if (value) form.cycle_id = '';
    },
);
const areaLimitM2 = computed(
    () =>
        props.cycles.find((cycle) => cycle.id === form.cycle_id)
            ?.area_limit_m2 ??
        props.blocks.find((block) => block.id === form.block_id)
            ?.area_limit_m2 ??
        null,
);
const numericErrors = computed<Record<string, string>>(() => {
    const result = activityDetailMaximumErrors(
        props.fields,
        form.details,
        areaLimitM2.value,
        props.numericLimits.quantityKg,
    );
    const workerError = maximumError(
        form.worker_count,
        props.numericLimits.workerCount,
        'Jumlah pekerja',
    );
    if (workerError) result.worker_count = workerError;
    return result;
});
const errors = computed<Record<string, string>>(() => ({
    ...form.errors,
    ...numericErrors.value,
}));
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
watch(areaLimitM2, () =>
    form.clearErrors('details.area' as keyof typeof form.errors),
);
const matches = computed(() =>
    props.matchingTasks.filter((t) => t.planting_cycle_id === form.cycle_id),
);
const blockedReason = computed(
    () =>
        props.blocks.find((block) => block.id === form.block_id)
            ?.blocked_reason ??
        props.cycles.find((cycle) => cycle.id === form.cycle_id)
            ?.blocked_reason ??
        null,
);
const fieldClass =
    'block min-h-12 w-full rounded-xl border border-border bg-card px-3 py-2 text-base focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [color-scheme:light] dark:[color-scheme:dark]';
function photos(event: Event): void {
    const input = event.target as HTMLInputElement;
    const files = [...(input.files ?? [])];
    clientError.value = '';
    if (
        files.length + form.photos.length > 5 ||
        files.some(
            (f) =>
                f.size > 10485760 ||
                !['image/jpeg', 'image/png', 'image/webp'].includes(f.type),
        )
    )
        clientError.value =
            'Pilih maksimal 5 foto JPG/PNG/WebP, masing-masing maksimal 10 MB.';
    else form.photos.push(...files);
    input.value = '';
}
function submit(): void {
    if (
        form.processing ||
        !ready.value ||
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
    if (!existing.value && form.end_time && form.end_time <= form.start_time) {
        clientError.value = 'Jam selesai harus setelah jam mulai.';
        return;
    }
    form.transform((data) => {
        const details = Object.fromEntries(
            Object.entries(data.details).filter(([key]) => {
                const f = props.fields.find((f) => f.key === key);
                return !f?.when || data.details[f.when[0]] === f.when[1];
            }),
        );
        return {
            ...data,
            details,
            ...(existing.value
                ? {
                      start_time: undefined,
                      end_time: undefined,
                      daily_notes: undefined,
                  }
                : {}),
        };
    }).post(`/petugas/aktivitas/mandiri/${props.type.code}`, {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>
<template>
    <Head :title="`Catat ${type.name}`" />
    <div class="space-y-4">
        <Link
            href="/petugas/laporan"
            class="inline-flex min-h-11 items-center text-sm text-secondary"
            >← Kembali ke laporan</Link
        >
        <header>
            <span class="text-xs font-medium text-secondary"
                >Aktivitas mandiri</span
            >
            <h1 class="mt-1 text-xl font-semibold">{{ type.name }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">{{ farm.name }}</p>
        </header>
        <form class="space-y-4" @submit.prevent="submit">
            <fieldset
                :disabled="form.processing"
                class="space-y-4 disabled:opacity-60"
            >
                <section
                    class="space-y-4 rounded-2xl border border-border bg-card p-4"
                >
                    <label class="block space-y-2 text-sm"
                        ><span>Tanggal pelaksanaan *</span
                        ><input
                            v-model="form.work_date"
                            type="date"
                            required
                            :max="today"
                            :class="fieldClass" /></label
                    ><label
                        v-if="blockOnly || optionalLocation"
                        class="block space-y-2 text-sm"
                    >
                        <span>Blok {{ blockOnly ? '*' : '(opsional)' }}</span>
                        <AppSelect
                            v-model="form.block_id"
                            :required="blockOnly"
                            aria-label="Blok"
                            placeholder="Pilih blok"
                            :class="fieldClass"
                        >
                            <AppSelectOption v-if="optionalLocation" value=""
                                >Tanpa blok</AppSelectOption
                            >
                            <AppSelectOption
                                v-for="block in blocks"
                                :key="block.id"
                                :value="block.id"
                                >{{ block.code }} ·
                                {{ block.name }}</AppSelectOption
                            >
                        </AppSelect> </label
                    ><label v-if="!blockOnly" class="block space-y-2 text-sm"
                        ><span
                            >Blok / subblok{{
                                optionalLocation ? ' (opsional)' : ' *'
                            }}</span
                        ><AppSelect
                            v-model="form.cycle_id"
                            :required="!optionalLocation"
                            aria-label="Subblok"
                            placeholder="Pilih subblok"
                            :class="fieldClass"
                            ><AppSelectOption v-if="optionalLocation" value=""
                                >Seluruh kebun</AppSelectOption
                            ><AppSelectOption
                                v-for="cycle in cycles"
                                :key="cycle.id"
                                :value="cycle.id"
                                >{{ cycle.block.name }} ·
                                {{ cycle.subblock_label }}</AppSelectOption
                            ></AppSelect
                        ></label
                    >
                    <p
                        v-if="
                            blockOnly
                                ? !blocks.length
                                : !cycles.length && !optionalLocation
                        "
                        class="text-xs text-muted-foreground"
                    >
                        Belum ada {{ blockOnly ? 'blok' : 'subblok' }} aktif.
                        Hubungi Admin untuk menyiapkannya.
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
                            Ada tugas SOP sejenis untuk subblok ini. Buka tugas
                            jika pekerjaan ini merupakan pelaksanaannya, atau
                            lanjutkan sebagai aktivitas mandiri.
                        </p>
                        <Link
                            v-for="task in matches"
                            :key="task.id"
                            :href="`/petugas/tasks/${task.id}`"
                            class="mt-1 flex min-h-11 items-center text-sm font-medium text-secondary underline"
                            >{{ task.name }} ·
                            {{ task.scheduled_date ?? 'Belum dijadwalkan' }}
                            →</Link
                        >
                    </div>
                </section>
                <DailyHeaderContext
                    v-model:start="form.start_time"
                    v-model:end="form.end_time"
                    v-model:notes="form.daily_notes"
                    :farm-id="farm.id"
                    :date="form.work_date"
                    :errors="form.errors"
                    @ready="ready = $event"
                    @existing="existing = $event"
                />
                <section
                    class="space-y-4 rounded-2xl border border-border bg-card p-4"
                >
                    <h2 class="font-semibold">Hasil pekerjaan</h2>
                    <label class="block space-y-2 text-sm"
                        ><span>Jumlah pekerja untuk aktivitas ini *</span
                        ><input
                            v-model.number="form.worker_count"
                            type="number"
                            min="1"
                            :max="numericLimits.workerCount"
                            step="1"
                            required
                            :class="fieldClass"
                            :aria-invalid="!!errors.worker_count"
                            aria-describedby="voluntary-worker-help voluntary-worker-error"
                        /><span
                            id="voluntary-worker-help"
                            class="block text-xs text-muted-foreground"
                            >Maksimal
                            {{
                                formatActivityNumber(numericLimits.workerCount)
                            }}
                            pekerja.</span
                        ><span
                            v-if="errors.worker_count"
                            id="voluntary-worker-error"
                            class="block text-xs text-error"
                            aria-live="polite"
                            >{{ errors.worker_count }}</span
                        ></label
                    >
                    <ActivityDetailFields
                        v-model="form.details"
                        :fields="fields"
                        :errors="errors"
                        :area-limit-m2="areaLimitM2"
                    />
                    <p v-if="noArea" role="status" class="text-sm text-warning">
                        Tidak ada luas yang dapat dilaporkan untuk lokasi ini.
                    </p>
                </section>
                <section
                    class="space-y-4 rounded-2xl border border-border bg-card p-4"
                >
                    <label class="block space-y-2 text-sm"
                        ><span>Foto dokumentasi *</span
                        ><input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            :class="fieldClass"
                            @change="photos"
                    /></label>
                    <p class="text-xs text-muted-foreground">
                        Minimal 1, maksimal 5 foto. JPG/PNG/WebP, maksimal 10 MB
                        per foto.
                    </p>
                    <ul class="space-y-2">
                        <li
                            v-for="(photo, index) in form.photos"
                            :key="index"
                            class="flex items-center gap-2 rounded-xl bg-muted px-3"
                        >
                            <span class="min-w-0 flex-1 truncate text-xs">{{
                                photo.name
                            }}</span
                            ><button
                                type="button"
                                class="flex size-11 items-center justify-center"
                                :aria-label="`Hapus ${photo.name}`"
                                @click="form.photos.splice(index, 1)"
                            >
                                <X class="size-4" />
                            </button>
                        </li>
                    </ul>
                    <label class="block space-y-2 text-sm"
                        ><span>Catatan aktivitas (opsional)</span
                        ><textarea
                            v-model="form.notes"
                            rows="3"
                            maxlength="5000"
                            :class="fieldClass"
                        />
                    </label>
                </section>
            </fieldset>
            <div
                v-if="clientError || Object.keys(form.errors).length"
                role="alert"
                class="space-y-1 rounded-xl border border-error/30 bg-error/10 p-3 text-sm"
            >
                <p v-if="clientError">{{ clientError }}</p>
                <p v-for="(error, key) in form.errors" :key="key">
                    {{ error }}
                </p>
            </div>
            <button
                :disabled="
                    form.processing ||
                    !ready ||
                    !!blockedReason ||
                    noArea ||
                    Object.keys(numericErrors).length > 0
                "
                type="submit"
                class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-secondary px-4 font-semibold text-secondary-foreground disabled:opacity-50"
            >
                <LoaderCircle
                    v-if="form.processing"
                    class="size-4 animate-spin motion-reduce:animate-none"
                />{{
                    form.processing
                        ? `Menyimpan ${form.progress?.percentage ?? ''}…`
                        : 'Simpan laporan'
                }}
            </button>
        </form>
    </div>
</template>
