<script setup lang="ts">
import { ListFilter } from '@lucide/vue';
import { activityTypeOptions, type ActivityType } from '@/data/activityLogs';

const props = defineProps<{
    modelValue: '' | 'all' | ActivityType;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: '' | 'all' | ActivityType];
}>();

function updateValue(event: Event): void {
    emit(
        'update:modelValue',
        (event.target as HTMLSelectElement).value as '' | 'all' | ActivityType,
    );
}
</script>

<template>
    <label class="relative block min-w-[126px] shrink-0">
        <span class="sr-only">Filter jenis aktivitas</span>
        <ListFilter
            :size="16"
            :stroke-width="1.8"
            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-primary"
            aria-hidden="true"
        />
        <select
            :value="props.modelValue"
            class="h-11 w-full appearance-none rounded-xl border border-primary bg-card py-0 pr-7 pl-9 text-sm text-foreground outline-none focus:ring-2 focus:ring-primary/20"
            aria-label="Filter jenis aktivitas"
            @change="updateValue"
        >
            <option value="">Semua aktivitas</option>
            <option
                v-for="option in activityTypeOptions"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </select>
        <span
            class="pointer-events-none absolute top-1/2 right-3 size-1.5 -translate-y-1/2 rotate-45 border-r border-b border-primary"
            aria-hidden="true"
        />
    </label>
</template>
