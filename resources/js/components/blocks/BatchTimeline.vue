<script setup lang="ts">
import { Check, Circle, LoaderCircle } from '@lucide/vue';
import { blockDate, stageLabels, type BatchStage } from '@/types/blocks';
defineProps<{ stages: BatchStage[] }>();
const labels = {
    upcoming: 'Belum dimulai',
    in_progress: 'Berlangsung',
    completed: 'Selesai',
};
</script>
<template>
    <ol
        aria-label="Tahap dan tanggal progres"
        class="grid gap-x-4 gap-y-4 sm:grid-cols-3 xl:grid-cols-6"
    >
        <li
            v-for="stage in stages"
            :key="stage.code"
            class="flex min-w-0 items-start gap-2 sm:block"
            :aria-current="stage.status === 'in_progress' ? 'step' : undefined"
        >
            <div
                class="flex items-center gap-2 sm:mb-2"
                :class="
                    stage.status === 'completed'
                        ? 'text-success'
                        : stage.status === 'in_progress'
                          ? 'text-primary'
                          : 'text-muted-foreground'
                "
            >
                <Check
                    v-if="stage.status === 'completed'"
                    class="size-4 shrink-0"
                /><LoaderCircle
                    v-else-if="stage.status === 'in_progress'"
                    class="size-4 shrink-0"
                /><Circle v-else class="size-4 shrink-0" />
                <span class="hidden text-xs sm:inline">{{
                    labels[stage.status]
                }}</span>
            </div>
            <div
                class="flex min-w-0 flex-1 items-start justify-between gap-3 sm:block"
            >
                <p
                    class="text-sm"
                    :class="
                        stage.status === 'in_progress'
                            ? 'font-semibold'
                            : 'font-medium'
                    "
                >
                    {{ stageLabels[stage.code] ?? stage.code
                    }}<span
                        v-if="stage.status === 'in_progress'"
                        class="block text-xs font-normal text-primary sm:hidden"
                        >Berlangsung</span
                    >
                </p>
                <p class="shrink-0 text-xs text-muted-foreground sm:mt-1">
                    {{
                        stage.started_at
                            ? blockDate(stage.started_at)
                            : 'Belum dimulai'
                    }}<span v-if="stage.completed_at" class="block sm:mt-1"
                        >Selesai {{ blockDate(stage.completed_at) }}</span
                    >
                </p>
            </div>
        </li>
    </ol>
</template>
