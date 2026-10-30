<script setup lang="ts">
import { Check, Circle, LoaderCircle } from '@lucide/vue';
import { blockDate, stageLabels, type BatchStage } from '@/types/blocks';

defineProps<{ stages: BatchStage[] }>();
const labels = {
    upcoming: 'Akan datang',
    in_progress: 'Berlangsung',
    completed: 'Selesai',
};
</script>

<template>
    <ol
        aria-label="Timeline tahap penanaman"
        class="grid gap-3 sm:grid-cols-3 xl:grid-cols-6"
    >
        <li
            v-for="stage in stages"
            :key="stage.code"
            class="min-w-0 rounded-xl border border-border p-3"
            :class="
                stage.status === 'in_progress' ? 'bg-primary/10' : 'bg-card'
            "
        >
            <div
                class="mb-3 flex items-center gap-2"
                :class="
                    stage.status === 'completed'
                        ? 'text-success'
                        : stage.status === 'in_progress'
                          ? 'text-foreground'
                          : 'text-muted-foreground'
                "
            >
                <Check
                    v-if="stage.status === 'completed'"
                    class="size-5 shrink-0"
                /><LoaderCircle
                    v-else-if="stage.status === 'in_progress'"
                    class="size-5 shrink-0"
                /><Circle v-else class="size-5 shrink-0" />
                <span class="text-xs font-medium">{{
                    labels[stage.status]
                }}</span>
            </div>
            <h3 class="text-sm font-semibold">
                {{ stageLabels[stage.code] ?? stage.code }}
            </h3>
            <p class="mt-2 text-xs leading-5 text-muted-foreground">
                {{ blockDate(stage.completed_at ?? stage.started_at) }}
            </p>
        </li>
    </ol>
</template>
