<script setup lang="ts">
import { Sprout } from '@lucide/vue';
import { computed } from 'vue';
import { stageLabels } from '@/types/blocks';
import type { GardenBatch } from '@/types/petugas-garden';
import { gardenArea, gardenDate } from '@/types/petugas-garden';

const props = defineProps<{ batch: GardenBatch }>();
const statuses: Record<string, { label: string; color: string }> = {
    planned: { label: 'Pra tanam', color: 'bg-muted text-muted-foreground' },
    active: {
        label: 'Aktif',
        color: 'bg-primary/10 text-secondary dark:text-primary',
    },
    harvested: {
        label: 'Panen selesai',
        color: 'bg-success/10 text-secondary dark:text-primary',
    },
    cancelled: { label: 'Dibatalkan', color: 'bg-muted text-muted-foreground' },
    failed: { label: 'Gagal', color: 'bg-error/10 text-error' },
};
const status = computed(
    () =>
        statuses[props.batch.status] ?? {
            label: props.batch.status,
            color: 'bg-muted text-muted-foreground',
        },
);
const completed = computed(
    () =>
        props.batch.stages.filter((stage) => stage.status === 'completed')
            .length,
);
const stageStatuses = {
    upcoming: 'Belum dimulai',
    in_progress: 'Berjalan',
    completed: 'Selesai',
};
</script>

<template>
    <article
        class="petugas-card space-y-4 p-4"
        :aria-label="`Batch ${batch.batch_number}, subblok ${batch.subblock_label}`"
    >
        <header class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-start gap-2.5">
                <Sprout
                    class="mt-0.5 size-5 shrink-0 text-secondary dark:text-primary"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <h4 class="text-base font-semibold">
                        Batch {{ batch.batch_number }}
                    </h4>
                    <p
                        class="mt-1 text-xs leading-5 break-words text-muted-foreground"
                    >
                        Subblok {{ batch.subblock_label }}
                    </p>
                </div>
            </div>
            <span
                class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-medium"
                :class="status.color"
                >{{ status.label }}</span
            >
        </header>
        <dl class="grid grid-cols-2 gap-3">
            <div>
                <dt class="text-xs text-muted-foreground">Alokasi</dt>
                <dd class="mt-1 text-xl font-bold tabular-nums">
                    {{ gardenArea(batch.reserved_area_ha) }}
                    <span class="text-xs font-normal text-muted-foreground"
                        >ha</span
                    >
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Tertanam</dt>
                <dd class="mt-1 text-xl font-bold tabular-nums">
                    {{ gardenArea(batch.planted_area_ha) }}
                    <span class="text-xs font-normal text-muted-foreground"
                        >ha</span
                    >
                </dd>
            </div>
        </dl>
        <div class="space-y-2">
            <div
                class="flex flex-wrap items-center justify-between gap-2 text-xs"
            >
                <span class="font-medium text-secondary dark:text-primary">{{
                    batch.current_stage
                        ? (stageLabels[batch.current_stage] ??
                          batch.current_stage)
                        : 'Belum ada tahap'
                }}</span
                ><span
                    v-if="batch.stages.length"
                    class="font-bold text-muted-foreground"
                    >{{ completed }}/{{ batch.stages.length }} tahap
                    selesai</span
                >
            </div>
            <ol
                v-if="batch.stages.length"
                class="flex gap-1"
                aria-label="Tahapan penanaman"
            >
                <li
                    v-for="stage in batch.stages"
                    :key="stage.code"
                    class="h-1.5 min-w-0 flex-1 rounded-full"
                    :class="
                        stage.status === 'completed'
                            ? 'bg-secondary dark:bg-primary'
                            : stage.status === 'in_progress'
                              ? 'bg-primary/50'
                              : 'bg-muted'
                    "
                    :title="`${stageLabels[stage.code] ?? stage.code}: ${stageStatuses[stage.status]}`"
                >
                    <span class="sr-only"
                        >{{ stageLabels[stage.code] ?? stage.code }}:
                        {{ stageStatuses[stage.status] }}</span
                    >
                </li>
            </ol>
        </div>
        <dl
            class="grid grid-cols-2 gap-3 border-t border-border pt-3 text-xs leading-5"
        >
            <div>
                <dt class="text-muted-foreground">
                    {{ batch.planted_at ? 'Tanggal tanam' : 'Rencana tanam' }}
                </dt>
                <dd class="mt-1 font-medium">
                    {{
                        gardenDate(
                            batch.planted_at ?? batch.planned_planting_at,
                        )
                    }}<span
                        v-if="batch.hst !== null"
                        class="mt-0.5 block font-normal text-muted-foreground"
                        >{{ batch.hst }} HST</span
                    >
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">
                    {{
                        batch.status === 'harvested'
                            ? 'Status panen'
                            : 'Estimasi awal panen'
                    }}
                </dt>
                <dd class="mt-1 font-medium">
                    {{
                        batch.status === 'harvested'
                            ? 'Panen selesai'
                            : batch.estimated_harvest_at
                              ? gardenDate(batch.estimated_harvest_at)
                              : 'Belum ada estimasi'
                    }}
                    <span
                        v-if="batch.status === 'harvested' && batch.estimated_harvest_at"
                        class="mt-0.5 block text-muted-foreground"
                    >
                        Estimasi sebelumnya:
                        {{ gardenDate(batch.estimated_harvest_at) }}
                    </span>
                </dd>
            </div>
        </dl>
    </article>
</template>
