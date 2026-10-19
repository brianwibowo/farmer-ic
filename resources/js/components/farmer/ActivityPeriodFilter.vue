<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    formatIsoMonthToLabel,
    formatIsoMonthToShortLabel,
    type ActivityTimeFilter,
} from '@/data/activityLogs';

const props = defineProps<{
    /** Months that have data, newest first, formatted as 'YYYY-MM'. */
    months: string[];
    stretch?: boolean;
}>();

const filter = defineModel<ActivityTimeFilter>({ default: 'all' });

const isMonthListOpen = ref(false);
const monthMenuRef = ref<HTMLElement | null>(null);

const selectedMonth = computed(() =>
    filter.value.startsWith('month:')
        ? filter.value.slice('month:'.length)
        : null,
);

const selectedLabel = computed(() =>
    selectedMonth.value
        ? formatIsoMonthToShortLabel(selectedMonth.value)
        : 'Semua',
);

function selectMonth(month: string): void {
    filter.value = `month:${month}`;
    isMonthListOpen.value = false;
}

function handleDocumentClick(event: MouseEvent): void {
    const target = event.target as Node | null;
    if (
        isMonthListOpen.value &&
        monthMenuRef.value &&
        target &&
        !monthMenuRef.value.contains(target)
    ) {
        isMonthListOpen.value = false;
    }
}

onMounted(() => document.addEventListener('pointerdown', handleDocumentClick));

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleDocumentClick);
});
</script>

<template>
    <div
        ref="monthMenuRef"
        :class="[
            'relative',
            props.stretch ? 'min-w-0 w-full' : 'w-[104px]',
        ]"
    >
        <button
            type="button"
            aria-haspopup="listbox"
            :aria-expanded="isMonthListOpen"
            aria-label="Filter periode waktu"
            :class="[
                'inline-flex h-[38px] items-center justify-between gap-1 rounded-lg border border-primary bg-card px-3 text-[14px] text-foreground outline-none focus:ring-2 focus:ring-primary/30',
                props.stretch ? 'w-full' : 'w-[104px]',
            ]"
            @click="isMonthListOpen = !isMonthListOpen"
        >
            <span class="truncate">{{ selectedLabel }}</span>
            <ChevronDown
                :size="16"
                :stroke-width="1.8"
                :class="[
                    'shrink-0 text-primary transition-transform duration-150',
                    isMonthListOpen ? 'rotate-180' : '',
                ]"
                aria-hidden="true"
            />
        </button>

        <ul
            v-if="isMonthListOpen"
            role="listbox"
            aria-label="Pilihan bulan"
            class="absolute top-full right-0 z-30 mt-1.5 max-h-[200px] w-[176px] overflow-y-auto rounded-xl border border-border bg-card p-1 shadow-md"
        >
            <li>
                <button
                    type="button"
                    role="option"
                    :aria-selected="filter === 'all'"
                    :class="[
                        'flex w-full items-center rounded-lg px-3 py-2 text-left text-[13px] leading-4',
                        filter === 'all'
                            ? 'bg-muted font-semibold text-secondary'
                            : 'text-foreground hover:bg-muted/70',
                    ]"
                    @click="
                        filter = 'all';
                        isMonthListOpen = false;
                    "
                >
                    Semua
                </button>
            </li>
            <li v-for="month in months" :key="month">
                <button
                    type="button"
                    role="option"
                    :aria-selected="selectedMonth === month"
                    :class="[
                        'flex w-full items-center rounded-lg px-3 py-2 text-left text-[13px] leading-4 transition-colors',
                        selectedMonth === month
                            ? 'bg-muted font-semibold text-secondary'
                            : 'text-foreground hover:bg-muted/70',
                    ]"
                    @click="selectMonth(month)"
                >
                    {{ formatIsoMonthToLabel(month) }}
                </button>
            </li>
        </ul>
    </div>
</template>
