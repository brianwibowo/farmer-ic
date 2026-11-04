<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Pencil, Plus } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import type { DailyReport } from '@/types/daily-activity';

const props = defineProps<{
    farm: { id: string; name: string };
    date: string;
    log: DailyReport | null;
    canUpdate: boolean;
    canRecord: boolean;
}>();
const editing = ref(false);
const adding = ref(false);
const header = useForm({
    start_time: '',
    end_time: '',
    daily_notes: '',
});
const fieldClass =
    'block min-h-12 w-full rounded-xl border border-border bg-background px-3 py-2 text-base text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-error [color-scheme:light] dark:[color-scheme:dark]';
watch(
    () => props.log,
    (log) => {
        if (!log) return;
        header.defaults({
            start_time: log.start_time,
            end_time: log.end_time,
            daily_notes: log.notes ?? '',
        });
        header.reset();
        editing.value = false;
    },
    { immediate: true },
);
function save(): void {
    if (!props.log || header.processing) return;
    header.clearErrors();
    if (header.end_time <= header.start_time) {
        header.setError('end_time', 'Waktu selesai harus setelah waktu mulai.');
        return;
    }
    header.patch(`/kebun/${props.farm.id}/daily-logs/${props.log.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="space-y-5 text-foreground">
        <section
            v-if="log"
            class="space-y-4 rounded-xl border border-border bg-card p-4"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="text-lg font-semibold">Ringkasan harian</h2>
                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                        Laporan bersama seluruh Petugas kebun. Dibuat pertama
                        oleh {{ log.recorder_name ?? 'Petugas kebun' }}.
                    </p>
                </div>
                <Button
                    v-if="canUpdate"
                    type="button"
                    variant="outline"
                    class="min-h-11 rounded-xl"
                    :disabled="header.processing"
                    :aria-expanded="editing"
                    @click="editing = !editing"
                    ><Pencil class="size-4" aria-hidden="true" />{{
                        editing ? 'Tutup' : 'Ubah'
                    }}</Button
                >
            </div>
            <p class="text-base font-semibold">
                {{ log.start_time }}–{{ log.end_time }} WIB
            </p>
            <p
                v-if="log.notes"
                class="text-sm leading-6 whitespace-pre-line text-muted-foreground"
            >
                {{ log.notes }}
            </p>
            <form
                v-if="editing && canUpdate"
                class="space-y-3 border-t border-border pt-4"
                @submit.prevent="save"
            >
                <p class="text-xs leading-5 text-muted-foreground">
                    Perubahan berlaku untuk header bersama, bukan jam atau
                    jumlah pekerja per aktivitas.
                </p>
                <fieldset :disabled="header.processing" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <label class="space-y-2 text-sm"
                            ><span>Mulai kerja harian</span
                            ><input
                                v-model="header.start_time"
                                required
                                type="time"
                                :class="fieldClass"
                                :aria-invalid="
                                    !!header.errors.start_time
                                " /></label
                        ><label class="space-y-2 text-sm"
                            ><span>Selesai kerja harian</span
                            ><input
                                v-model="header.end_time"
                                required
                                type="time"
                                :class="fieldClass"
                                :aria-invalid="!!header.errors.end_time"
                        /></label>
                    </div>
                    <label class="block space-y-2 text-sm"
                        ><span>Catatan umum hari ini</span
                        ><textarea
                            v-model="header.daily_notes"
                            rows="3"
                            maxlength="5000"
                            :class="fieldClass"
                            :aria-invalid="!!header.errors.daily_notes"
                        />
                    </label>
                </fieldset>
                <p
                    v-for="(message, key) in header.errors"
                    :key="key"
                    role="alert"
                    class="text-sm text-error"
                >
                    {{ message }}
                </p>
                <Button
                    type="submit"
                    class="min-h-12 w-full rounded-xl"
                    :disabled="header.processing"
                    ><LoaderCircle
                        v-if="header.processing"
                        class="size-4 animate-spin motion-reduce:animate-none"
                        aria-hidden="true"
                    />{{
                        header.processing
                            ? 'Menyimpan…'
                            : 'Simpan informasi harian'
                    }}</Button
                >
            </form>
        </section>
        <p
            v-else
            class="rounded-xl border border-dashed border-border p-5 text-sm leading-6 text-muted-foreground"
        >
            Belum ada laporan pada tanggal ini. Catat aktivitas pertama untuk
            membuat laporan harian bersama.
        </p>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">
                {{ log?.activities.length ?? 0 }} aktivitas tercatat
            </h2>
            <article
                v-for="activity in log?.activities ?? []"
                :key="activity.id"
                class="space-y-3 rounded-xl border border-border bg-card p-4"
            >
                <div>
                    <h3 class="text-base font-semibold">
                        {{ activity.position }}. {{ activity.name }}
                    </h3>
                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                        {{ activity.performer_name ?? 'Petugas kebun'
                        }}<template v-if="activity.worker_count !== null">
                            · {{ activity.worker_count }} pekerja</template
                        ><template v-if="activity.subblok">
                            ·
                            {{
                                activity.block ? `${activity.block} · ` : ''
                            }}Subblok {{ activity.subblok }}</template
                        >
                    </p>
                </div>
                <dl
                    v-if="activity.details.length"
                    class="grid grid-cols-1 gap-2 text-sm min-[375px]:grid-cols-2"
                >
                    <div v-for="detail in activity.details" :key="detail.label">
                        <dt class="text-xs text-muted-foreground">
                            {{ detail.label }}
                        </dt>
                        <dd class="break-words">{{ detail.value }}</dd>
                    </div>
                </dl>
                <p
                    v-if="activity.notes"
                    class="text-sm leading-6 whitespace-pre-line"
                >
                    {{ activity.notes }}
                </p>
                <div
                    v-if="activity.photos.length"
                    class="grid grid-cols-2 gap-2"
                >
                    <a
                        v-for="(photo, index) in activity.photos"
                        :key="photo.id"
                        :href="photo.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        ><img
                            :src="photo.url"
                            :alt="`Bukti ${activity.name}, foto ${index + 1}`"
                            class="aspect-square w-full rounded-xl object-cover"
                            loading="lazy"
                    /></a>
                </div>
                <Link
                    v-if="activity.task_id && canRecord"
                    :href="`/petugas/tasks/${activity.task_id}`"
                    class="inline-flex min-h-11 items-center text-sm font-semibold text-primary underline focus-visible:outline-2 focus-visible:outline-ring"
                    >Lihat tugas SOP asal</Link
                >
            </article>
        </section>
        <div v-if="canRecord" class="space-y-3">
            <Button
                type="button"
                class="min-h-12 w-full rounded-xl"
                :aria-expanded="adding"
                @click="adding = !adding"
                ><Plus class="size-4" aria-hidden="true" />{{
                    log ? 'Tambah aktivitas' : 'Catat aktivitas pertama'
                }}</Button
            >
            <div
                v-if="adding"
                class="grid gap-2 rounded-xl border border-border bg-card p-3"
            >
                <Button as-child variant="outline" class="min-h-12 rounded-xl"
                    ><Link :href="`/petugas/tasks?work_date=${date}`"
                        >Dari tugas SOP</Link
                    ></Button
                ><Button as-child variant="outline" class="min-h-12 rounded-xl"
                    ><Link :href="`/petugas/penanaman?work_date=${date}`"
                        >Penanaman awal subblok</Link
                    ></Button
                >
            </div>
        </div>
    </div>
</template>
