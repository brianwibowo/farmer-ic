<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BatchTimeline from '@/components/blocks/BatchTimeline.vue';
import type { OwnerCycle } from '@/types/owner';
import { ownerArea, ownerDate, ownerStatus } from '@/types/owner';

defineProps<{ cycle: OwnerCycle }>();
</script>

<template>
    <article
        class="min-w-0 space-y-4 rounded-xl border border-border bg-card p-4"
    >
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <h3 class="font-semibold break-words">{{ cycle.label }}</h3>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{
                        cycle.variety
                            ? [cycle.variety.commodity, cycle.variety.name]
                                  .filter(Boolean)
                                  .join(' · ')
                            : 'Varietas belum ditetapkan'
                    }}
                </p>
            </div>
            <span
                class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-secondary dark:text-primary"
                >{{ ownerStatus[cycle.status] ?? cycle.status }}</span
            >
        </div>
        <dl class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <dt class="text-xs text-muted-foreground">Alokasi batch</dt>
                <dd class="mt-1 font-medium">
                    {{
                        cycle.reserved_area_ha === null
                            ? 'Tanpa alokasi batch'
                            : `${ownerArea(cycle.reserved_area_ha)} ha`
                    }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Realisasi tanam</dt>
                <dd class="mt-1 font-medium">
                    {{ ownerArea(cycle.planted_area_ha) }} ha
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Rencana tanam</dt>
                <dd class="mt-1">{{ ownerDate(cycle.planned_planting_at) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Tanggal tanam</dt>
                <dd class="mt-1">{{ ownerDate(cycle.planted_at) }}</dd>
            </div>
            <div class="col-span-2">
                <dt class="text-xs text-muted-foreground">Estimasi panen</dt>
                <dd class="mt-1">
                    {{ ownerDate(cycle.estimated_harvest_at) }}
                </dd>
            </div>
        </dl>
        <Link
            v-if="cycle.sop"
            :href="`/lainnya/sop/${cycle.sop.id}`"
            class="block rounded-xl bg-muted p-3 text-sm font-medium hover:text-primary focus-visible:outline-2 focus-visible:outline-ring"
            >{{ cycle.sop.name }} · Versi {{ cycle.sop.version }}</Link
        >
        <p v-else class="text-xs text-muted-foreground">
            SOP batch belum tersedia.
        </p>
        <BatchTimeline
            v-if="cycle.stages.length"
            :stages="cycle.stages"
            class="grid-cols-1!"
        />
        <p v-else class="text-sm text-muted-foreground">
            Timeline tahap belum tercatat.
        </p>
        <details class="rounded-xl border border-border">
            <summary
                class="min-h-11 cursor-pointer rounded-xl p-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-ring"
            >
                Jadwal tugas · {{ cycle.tasks.length }}
            </summary>
            <div class="space-y-3 px-3 pb-3">
                <p
                    v-if="!cycle.tasks.length"
                    class="text-sm text-muted-foreground"
                >
                    Belum ada tugas untuk batch ini.
                </p>
                <article
                    v-for="task in cycle.tasks"
                    :key="task.id"
                    class="space-y-1 border-t border-border pt-3"
                >
                    <h4 class="text-sm font-medium">{{ task.name }}</h4>
                    <p class="text-xs text-muted-foreground">
                        {{ ownerStatus[task.status] ?? task.status }} · HST
                        {{ task.offset_days }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Jadwal {{ ownerDate(task.scheduled_date) }} · Tenggat
                        {{ ownerDate(task.due_date) }}
                    </p>
                    <p
                        v-if="task.instructions"
                        class="text-sm leading-6 whitespace-pre-line"
                    >
                        {{ task.instructions }}
                    </p>
                </article>
            </div>
        </details>
    </article>
</template>
