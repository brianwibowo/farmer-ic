<script setup lang="ts">
import {
    AlertTriangle,
    CalendarCheck2,
    CalendarClock,
    CheckCircle2,
    Layers,
    ListTodo,
} from '@lucide/vue';
import { computed, type Component } from 'vue';
import {
    adminTaskViewOptions,
    type AdminTaskSummary,
    type AdminTaskView,
} from '@/types/tasks';

const props = defineProps<{
    active: AdminTaskView;
    summary: AdminTaskSummary;
    processing?: boolean;
}>();

const emit = defineEmits<{
    select: [view: AdminTaskView];
}>();

const viewIcons: Record<AdminTaskView, Component> = {
    actionable: ListTodo,
    overdue: AlertTriangle,
    today: CalendarCheck2,
    upcoming: CalendarClock,
    completed: CheckCircle2,
    all: Layers,
};

const tabs = computed(() =>
    adminTaskViewOptions.map(([value, label]) => {
        let count: number | undefined;
        if (value === 'actionable') count = props.summary.open;
        else if (value === 'overdue') count = props.summary.overdue;
        else if (value === 'today') count = props.summary.today;
        else if (value === 'completed') count = props.summary.completed;
        else if (value === 'all') count = props.summary.total;

        return {
            value,
            label,
            icon: viewIcons[value],
            count,
        };
    }),
);
</script>

<template>
    <nav class="user-directory-tabs" aria-label="Tampilan tugas">
        <button
            v-for="tab in tabs"
            :key="tab.value"
            type="button"
            class="user-directory-tab"
            :class="{ 'is-active': active === tab.value }"
            :aria-pressed="active === tab.value"
            :disabled="processing"
            @click="emit('select', tab.value)"
        >
            <component :is="tab.icon" class="size-4" aria-hidden="true" />
            <span>{{ tab.label }}</span>
            <span
                v-if="tab.count !== undefined"
                class="user-directory-tab-count"
                >{{ tab.count }}</span
            >
        </button>
    </nav>
</template>
