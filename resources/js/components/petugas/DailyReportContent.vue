<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import type { DailyReport } from '@/types/daily-activity';

defineProps<{
    farm: { id: string; name: string };
    date: string;
    log: DailyReport | null;
    canRecord: boolean;
}>();
const adding = ref(false);
</script>

<template>
    <div class="space-y-5 text-foreground">
        <section
            v-if="log"
            class="space-y-2 rounded-xl border border-border bg-card p-4"
        >
            <h2 class="text-lg font-semibold">Ringkasan harian</h2>
            <p class="text-xs leading-5 text-muted-foreground">
                Laporan bersama seluruh Petugas kebun. Dibuat pertama oleh
                {{ log.recorder_name ?? 'Petugas kebun' }}.
            </p>
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
                    ><Link :href="`/petugas/aktivitas?work_date=${date}`"
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
