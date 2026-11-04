<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { LoaderCircle, X } from '@lucide/vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import DailyHeaderContext from '@/components/petugas/DailyHeaderContext.vue';
import ActivityDetailFields, {
    type ActivityField,
} from '@/components/petugas/ActivityDetailFields.vue';
const props = defineProps<{
    farm: { id: string; name: string };
    type: { id: string; code: string; name: string };
    today: string;
    fields: ActivityField[];
    cycles: {
        id: string;
        subblock_label: string;
        block: { name: string };
        reserved_area_ha: string;
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
const matches = computed(() =>
    props.matchingTasks.filter((t) => t.planting_cycle_id === form.cycle_id),
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
    if (form.processing || !ready.value) return;
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
                      worker_count: undefined,
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
            href="/petugas/aktivitas"
            class="inline-flex min-h-11 items-center text-sm text-secondary"
            >← Kembali ke aktivitas</Link
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
                    ><label class="block space-y-2 text-sm"
                        ><span
                            >Blok / subblok{{
                                type.code === 'other' ? ' (opsional)' : ' *'
                            }}</span
                        ><AppSelect
                            v-model="form.cycle_id"
                            :required="type.code !== 'other'"
                            aria-label="Subblok"
                            placeholder="Pilih subblok"
                            :class="fieldClass"
                            ><AppSelectOption
                                v-if="type.code === 'other'"
                                value=""
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
                        v-if="!cycles.length"
                        class="text-xs text-muted-foreground"
                    >
                        Belum ada subblok aktif. Hubungi Admin untuk
                        menyiapkannya.
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
                    v-model:workers="form.worker_count"
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
                    <ActivityDetailFields
                        v-model="form.details"
                        :fields="fields"
                        :errors="form.errors"
                    />
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
                :disabled="form.processing || !ready"
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
