<script setup lang="ts">
import { Check, Circle, LoaderCircle } from '@lucide/vue';
import { blockDate, stageLabels, type BatchStage } from '@/types/blocks';
defineProps<{ stages: BatchStage[] }>();
</script>
<template>
    <ol
        aria-label="Tahap dan tanggal progres"
        class="grid grid-cols-2 gap-x-4 gap-y-4 sm:grid-cols-3 xl:grid-cols-6"
    >
        <li
            v-for="stage in stages"
            :key="stage.code"
            class="min-w-0 border-t-2 pt-3"
            :class="
                stage.status === 'completed'
                    ? 'border-success'
                    : stage.status === 'in_progress'
                      ? 'border-primary'
                      : 'border-border'
            "
            :aria-current="stage.status === 'in_progress' ? 'step' : undefined"
        >
            <div
                class="mb-2 flex items-center gap-2"
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
                <span
                    v-if="stage.status === 'in_progress'"
                    class="text-xs font-semibold"
                    >Sekarang</span
                >
                <span v-else class="text-xs">{{
                    stage.status === 'completed' ? 'Selesai' : 'Belum dimulai'
                }}</span>
            </div>
            <div class="min-w-0">
                <p
                    class="text-sm"
                    :class="
                        stage.status === 'in_progress'
                            ? 'font-semibold'
                            : 'font-medium'
                    "
                >
                    {{
                        stage.code === 'land_clearing'
                            ? 'Pembersihan lahan'
                            : (stageLabels[stage.code] ?? stage.code)
                    }}
                </p>
                <p
                    v-if="stage.started_at || stage.completed_at"
                    class="mt-1 text-xs leading-relaxed text-muted-foreground"
                >
                    {{
                        stage.started_at
                            ? `Mulai ${blockDate(stage.started_at)}`
                            : 'Tanggal belum tersedia'
                    }}<span v-if="stage.completed_at" class="block"
                        >Selesai {{ blockDate(stage.completed_at) }}</span
                    >
                </p>
            </div>
        </li>
    </ol>
</template>
