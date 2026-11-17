<script setup lang="ts">
import { MapPin } from '@lucide/vue';
import type { PetugasTask } from '@/types/petugas-task';
import { sopTaskCategoryLabel } from '@/types/sop';
withDefaults(defineProps<{ task: PetugasTask; compact?: boolean }>(), {
    compact: false,
});
</script>

<template>
    <section
        class="space-y-2 text-foreground"
        :class="
            compact
                ? 'px-4 pb-4'
                : 'rounded-xl border border-primary/30 bg-primary/10 p-4'
        "
        aria-label="Konteks tugas dari SOP"
    >
        <p class="text-xs font-medium">
            {{ sopTaskCategoryLabel(task) }}
        </p>
        <h2 v-if="!compact" class="text-lg leading-7 font-semibold">
            {{ task.name }}
        </h2>
        <p v-if="!compact" class="flex items-start gap-2 text-sm leading-6">
            <MapPin class="mt-1 size-4 shrink-0" aria-hidden="true" /><span
                >{{ task.subblok.farm.name }} · {{ task.subblok.block.name }} ·
                {{
                    task.subblok.label ?? `Subblok ${task.subblok.number}`
                }}</span
            >
        </p>
        <p class="text-xs leading-5 text-muted-foreground">
            {{ task.subblok.variety.name }} ·
            {{
                task.subblok.hst === null
                    ? task.subblok.planted_at
                        ? 'HST belum dimulai'
                        : 'Belum ditanam'
                    : `${task.subblok.hst} HST`
            }}
        </p>
        <p v-if="task.reporting_window" class="text-sm leading-6">
            Jendela pelaporan: {{ task.reporting_window.start }} sampai
            {{ task.reporting_window.end }} (WIB), termasuk kedua tanggal.
        </p>
        <p
            v-if="task.instructions"
            class="text-sm leading-6 whitespace-pre-line"
        >
            {{ task.instructions }}
        </p>
    </section>
</template>
