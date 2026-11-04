<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, LoaderCircle, RefreshCw, Sprout } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useActivityDate } from '@/composables/useActivityDate';
import type { PlantingCycleContext } from '@/types/planting';

defineProps<{
    farm: { id: string; name: string };
    cycles: (PlantingCycleContext & { canRecord: boolean; canSync: boolean })[];
}>();
const syncForm = useForm({});
const activityDate = useActivityDate();
const pendingId = ref<string | null>(null);
function synchronize(id: string): void {
    if (syncForm.processing) return;
    pendingId.value = id;
    syncForm.post(`/petugas/subbloks/${id}/tasks/sync`, {
        preserveScroll: true,
        onFinish: () => {
            pendingId.value = null;
        },
    });
}
</script>

<template>
    <Head title="Penanaman Subblok" />
    <div class="min-w-0 space-y-5 text-foreground">
        <header>
            <Link
                href="/petugas/aktivitas"
                class="inline-flex min-h-11 items-center gap-2 rounded-xl text-sm text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                ><ArrowLeft class="size-4" aria-hidden="true" />Kembali ke
                aktivitas</Link
            >
            <h1 class="mt-3 text-xl leading-7 font-semibold">
                Catat penanaman
            </h1>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                {{ farm.name }} · Catat tanggal tanam yang benar-benar terjadi,
                bukan tanggal rencana.
            </p>
        </header>
        <p
            class="rounded-xl border border-border bg-muted/50 p-4 text-sm leading-6"
        >
            Pilih subblok yang disiapkan Admin. Penanaman lanjutan dibatasi tiga
            hari kalender sejak tanggal pelaksanaan pertama.
        </p>
        <p
            v-if="!cycles.length"
            class="rounded-xl border border-dashed border-border p-5 text-sm leading-6 text-muted-foreground"
        >
            Belum ada subblok untuk kebun penugasan Anda. Hubungi Admin untuk
            menyiapkannya.
        </p>
        <article
            v-for="cycle in cycles"
            :key="cycle.id"
            class="space-y-3 rounded-xl border border-border bg-card p-4"
        >
            <div class="flex items-start gap-3">
                <Sprout
                    class="mt-1 size-5 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <h2 class="text-base leading-6 font-semibold">
                        {{ cycle.block.name }} · {{ cycle.subblock_label }}
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Batch {{ cycle.batch_number }} ·
                        {{
                            cycle.planted_at
                                ? 'Sudah ditanam'
                                : cycle.status === 'planned'
                                  ? 'Belum dicatat ditanam'
                                  : 'Subblok ditutup'
                        }}
                    </p>
                </div>
            </div>
            <details>
                <summary
                    class="cursor-pointer py-2 text-xs text-muted-foreground"
                >
                    Rencana, realisasi, dan luas
                </summary>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Rencana tanam
                        </dt>
                        <dd>
                            {{
                                cycle.planned_planting_at ?? 'Belum ditentukan'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Tanam aktual
                        </dt>
                        <dd>{{ cycle.planted_at ?? 'Belum dicatat' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Luas</dt>
                        <dd>
                            Alokasi {{ cycle.reserved_area_ha }} ha · Realisasi
                            {{ cycle.planted_area_ha }} ha
                        </dd>
                    </div>
                </dl>
            </details>
            <div class="flex flex-wrap gap-2">
                <Button as-child variant="outline" class="min-h-11 rounded-xl"
                    ><Link
                        :href="`/petugas/subbloks/${cycle.id}/penanaman${activityDate}`"
                        >{{
                            cycle.canRecord
                                ? 'Catat penanaman'
                                : 'Lihat realisasi'
                        }}</Link
                    ></Button
                ><Button
                    v-if="cycle.canSync"
                    type="button"
                    class="min-h-11 rounded-xl"
                    :disabled="syncForm.processing"
                    @click="synchronize(cycle.id)"
                    ><LoaderCircle
                        v-if="pendingId === cycle.id"
                        class="size-4 animate-spin motion-reduce:animate-none"
                        aria-hidden="true"
                    /><RefreshCw v-else class="size-4" aria-hidden="true" />{{
                        pendingId === cycle.id ? 'Menyiapkan…' : 'Siapkan tugas'
                    }}</Button
                >
            </div>
        </article>
        <Button as-child variant="outline" class="min-h-11 w-full rounded-xl"
            ><Link href="/petugas/aktivitas">Lihat to-do kebun</Link></Button
        >
    </div>
</template>
