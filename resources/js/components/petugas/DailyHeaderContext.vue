<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import type { DailyHeader } from '@/types/daily-activity';

const props = defineProps<{
    farmId: string;
    date: string;
    errors: Record<string, string>;
    reportBase?: string;
}>();
const start = defineModel<string>('start', { required: true });
const end = defineModel<string>('end', { required: true });
const workers = defineModel<string | number>('workers', { required: true });
const notes = defineModel<string>('notes', { required: true });
const emit = defineEmits<{
    ready: [value: boolean];
    existing: [value: boolean];
}>();
const log = ref<DailyHeader | null>(null);
const loading = ref(false);
const failed = ref(false);
const canUpdate = ref(false);
const editing = ref(false);
const edit = useForm({
    start_time: '',
    end_time: '',
    worker_count: 1,
    daily_notes: '',
});
function openEditor(): void {
    if (!log.value) return;
    edit.start_time = log.value.start_time;
    edit.end_time = log.value.end_time ?? '';
    edit.worker_count = log.value.worker_count;
    edit.daily_notes = log.value.notes ?? '';
    editing.value = true;
    emit('ready', false);
}
function cancelEdit(): void {
    editing.value = false;
    emit('ready', true);
}
function saveHeader(): void {
    if (!log.value || edit.processing) return;
    edit.clearErrors();
    if (
        !edit.start_time ||
        (edit.end_time && edit.end_time <= edit.start_time)
    ) {
        edit.setError(
            'end_time',
            'Isi jam mulai; jam selesai harus setelahnya.',
        );
        return;
    }
    if (
        !Number.isInteger(Number(edit.worker_count)) ||
        Number(edit.worker_count) < 1
    ) {
        edit.setError('worker_count', 'Jumlah pekerja minimal 1 orang.');
        return;
    }
    edit.patch(`/kebun/${props.farmId}/daily-logs/${log.value.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            editing.value = false;
            void load();
        },
    });
}
let controller: AbortController | undefined;
const fieldClass =
    'block min-h-12 w-full rounded-xl border border-border bg-background px-3 text-base text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-error [color-scheme:light] dark:[color-scheme:dark]';
async function load(): Promise<void> {
    controller?.abort();
    const current = new AbortController();
    controller = current;
    loading.value = true;
    failed.value = false;
    editing.value = false;
    log.value = null;
    emit('ready', false);
    emit('existing', false);
    try {
        if (!props.date) throw new Error('missing date');
        const response = await fetch(
            `/kebun/${props.farmId}/daily-logs/context?work_date=${encodeURIComponent(props.date)}`,
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: current.signal,
            },
        );
        if (!response.ok) throw new Error('lookup failed');
        const result = (await response.json()) as {
            data: DailyHeader | null;
            canUpdate: boolean;
        };
        if (current.signal.aborted) return;
        log.value = result.data;
        canUpdate.value = result.canUpdate;
        emit('existing', log.value !== null);
        emit('ready', true);
    } catch {
        if (!current.signal.aborted) failed.value = true;
    } finally {
        if (!current.signal.aborted) loading.value = false;
    }
}
watch(
    () => [props.date, props.farmId],
    () => {
        void load();
    },
    { immediate: true },
);
onBeforeUnmount(() => controller?.abort());
</script>

<template>
    <section
        class="space-y-4 rounded-xl border border-border bg-card p-4"
        aria-live="polite"
        :aria-busy="loading"
    >
        <h2 class="text-base font-semibold">
            Info kerja harian · {{ date || 'Pilih tanggal' }}
        </h2>
        <p
            v-if="loading"
            role="status"
            class="flex items-center gap-2 text-sm text-muted-foreground"
        >
            <LoaderCircle
                class="size-4 animate-spin motion-reduce:animate-none"
                aria-hidden="true"
            />Memeriksa laporan tanggal ini…
        </p>
        <div v-else-if="failed" class="space-y-2">
            <p role="alert" class="text-sm text-error">
                Laporan belum dapat diperiksa. Pastikan tanggal valid, lalu coba
                lagi.
            </p>
            <Button
                type="button"
                variant="outline"
                class="min-h-11 rounded-xl"
                @click="load"
                >Coba lagi</Button
            >
        </div>
        <template v-else-if="log">
            <p class="text-sm leading-6">
                Aktivitas ini akan ditambahkan ke laporan yang sudah ada.
                Informasi harian tidak ditimpa.
            </p>
            <p class="text-sm font-semibold">
                {{ log.start_time }}–{{ log.end_time ?? 'Belum diisi' }} WIB ·
                {{ log.worker_count }} pekerja
            </p>
            <p
                v-if="log.notes"
                class="text-sm whitespace-pre-line text-muted-foreground"
            >
                {{ log.notes }}
            </p>
            <Button
                v-if="canUpdate && !editing"
                type="button"
                variant="outline"
                class="min-h-11"
                @click="openEditor"
                >Ubah info harian</Button
            >
            <div v-if="editing" class="space-y-3 border-t border-border pt-3">
                <p class="text-xs text-muted-foreground">
                    Perubahan berlaku untuk seluruh aktivitas kebun pada tanggal
                    ini.
                </p>
                <label class="block space-y-1 text-sm"
                    ><span>Jam mulai</span
                    ><input
                        v-model="edit.start_time"
                        type="time"
                        :disabled="edit.processing"
                        :class="fieldClass"
                /></label>
                <label class="block space-y-1 text-sm"
                    ><span>Jam selesai (opsional)</span
                    ><input
                        v-model="edit.end_time"
                        type="time"
                        :disabled="edit.processing"
                        :class="fieldClass"
                /></label>
                <label class="block space-y-1 text-sm"
                    ><span>Total orang berbeda hari ini</span
                    ><input
                        v-model.number="edit.worker_count"
                        type="number"
                        min="1"
                        max="32767"
                        :disabled="edit.processing"
                        :class="fieldClass"
                /></label>
                <label class="block space-y-1 text-sm"
                    ><span>Catatan harian</span
                    ><textarea
                        v-model="edit.daily_notes"
                        rows="2"
                        maxlength="5000"
                        :disabled="edit.processing"
                        :class="fieldClass"
                    />
                </label>
                <p
                    v-for="(message, key) in edit.errors"
                    :key="key"
                    role="alert"
                    class="text-xs text-error"
                >
                    {{ message }}
                </p>
                <div class="flex gap-2">
                    <Button
                        type="button"
                        :disabled="edit.processing"
                        @click="saveHeader"
                        >{{
                            edit.processing
                                ? 'Menyimpan…'
                                : 'Simpan info harian'
                        }}</Button
                    ><Button
                        type="button"
                        variant="outline"
                        :disabled="edit.processing"
                        @click="cancelEdit"
                        >Batal</Button
                    >
                </div>
            </div>
            <Link
                v-if="!canUpdate"
                :href="
                    reportBase
                        ? `${reportBase}/${log.id}`
                        : `/petugas/laporan/harian?work_date=${date}`
                "
                class="inline-flex min-h-11 items-center text-sm font-semibold text-primary underline focus-visible:outline-2 focus-visible:outline-ring"
                >Lihat / ubah informasi harian</Link
            >
        </template>
        <template v-else>
            <p class="text-sm leading-6 text-muted-foreground">
                Aktivitas pertama akan membuat laporan harian. Isi jam kerja
                keseluruhan dan total pekerja hari ini, bukan hanya pekerjaan
                ini.
            </p>
            <div class="grid grid-cols-2 gap-3">
                <label class="space-y-2 text-sm"
                    ><span>Mulai kerja harian</span
                    ><input
                        v-model="start"
                        type="time"
                        required
                        :class="fieldClass"
                        :aria-invalid="!!errors.start_time"
                    /><span
                        v-if="errors.start_time"
                        class="block text-xs text-error"
                        >{{ errors.start_time }}</span
                    ></label
                >
                <label class="space-y-2 text-sm"
                    ><span>Jam selesai (boleh menyusul)</span
                    ><input
                        v-model="end"
                        type="time"
                        :class="fieldClass"
                        :aria-invalid="!!errors.end_time"
                    /><span
                        v-if="errors.end_time"
                        class="block text-xs text-error"
                        >{{ errors.end_time }}</span
                    ></label
                >
            </div>
            <label class="block space-y-2 text-sm"
                ><span>Total pekerja harian</span
                ><input
                    v-model="workers"
                    type="number"
                    min="1"
                    max="32767"
                    step="1"
                    required
                    :class="fieldClass"
                    :aria-invalid="!!errors.worker_count"
                /><span
                    v-if="errors.worker_count"
                    class="block text-xs text-error"
                    >{{ errors.worker_count }}</span
                ></label
            >
            <label class="block space-y-2 text-sm"
                ><span>Catatan umum hari ini (opsional)</span
                ><textarea
                    v-model="notes"
                    rows="3"
                    maxlength="5000"
                    :class="fieldClass"
                    :aria-invalid="!!errors.daily_notes"
                /><span
                    v-if="errors.daily_notes"
                    class="block text-xs text-error"
                    >{{ errors.daily_notes }}</span
                ></label
            >
        </template>
    </section>
</template>
