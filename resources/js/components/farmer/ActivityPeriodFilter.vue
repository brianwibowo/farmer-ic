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
}>();

const filter = defineModel<ActivityTimeFilter>({ default: 'all' });

const isMonthListOpen = ref(false);
const monthMenuRef = ref<HTMLElement | null>(null);

const periodOptions: Array<{ value: ActivityTimeFilter; label: string }> = [
    { value: 'all', label: 'Semua' },
    { value: 'week', label: '1 Minggu' },
    { value: 'month', label: '1 Bulan' },
];

const selectedMonth = computed(() =>
    filter.value.startsWith('month:')
        ? filter.value.slice('month:'.length)
        : null,
);

const moreLabel = computed(() =>
    selectedMonth.value
        ? formatIsoMonthToShortLabel(selectedMonth.value)
        : 'Lainnya',
);

/** Widest label the month chip can show, so its width never changes. */
const monthChipSizerLabel = computed(() => {
    let longest = 'Lainnya';

    for (const month of props.months) {
        const label = formatIsoMonthToShortLabel(month);
        if (label.length > longest.length) {
            longest = label;
        }
    }

    return longest;
});

const chipBaseClass =
    'inline-flex h-[34px] items-center justify-center gap-1 rounded-lg border px-2.5 text-[13px] font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';
const chipActiveClass =
    'border-secondary bg-secondary text-secondary-foreground';
const chipIdleClass =
    'border-border bg-card text-foreground hover:border-primary hover:text-secondary';

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
    <div ref="monthMenuRef" class="relative">
        <div
            class="period-chip-row flex items-center justify-between gap-1.5 overflow-x-auto"
            role="group"
            aria-label="Filter periode waktu"
        >
            <button
                v-for="option in periodOptions"
                :key="option.value"
                type="button"
                :aria-pressed="filter === option.value"
                :class="[
                    chipBaseClass,
                    'shrink-0',
                    filter === option.value ? chipActiveClass : chipIdleClass,
                ]"
                @click="filter = option.value"
            >
                {{ option.label }}
            </button>

            <button
                v-if="months.length > 0"
                type="button"
                aria-haspopup="listbox"
                :aria-expanded="isMonthListOpen"
                :class="[
                    chipBaseClass,
                    'relative shrink-0',
                    isMonthListOpen || selectedMonth
                        ? chipActiveClass
                        : chipIdleClass,
                ]"
                @click="isMonthListOpen = !isMonthListOpen"
            >
                <span
                    class="invisible flex items-center gap-1"
                    aria-hidden="true"
                >
                    {{ monthChipSizerLabel }}
                    <ChevronDown :size="12" :stroke-width="1.8" />
                </span>
                <span
                    class="absolute inset-0 flex items-center justify-center gap-1"
                >
                    <span class="min-w-0 truncate">{{ moreLabel }}</span>
                    <ChevronDown
                        :size="12"
                        :stroke-width="1.8"
                        :class="[
                            'shrink-0 transition-transform duration-150',
                            isMonthListOpen ? 'rotate-180' : '',
                        ]"
                        aria-hidden="true"
                    />
                </span>
            </button>
        </div>

        <ul
            v-if="isMonthListOpen"
            role="listbox"
            aria-label="Pilihan bulan"
            class="absolute top-full right-0 z-30 mt-1.5 max-h-[200px] w-[176px] overflow-y-auto rounded-xl border border-border bg-card p-1 shadow-md"
        >
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

<style scoped>
.period-chip-row {
    margin: -4px;
    padding: 4px;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.period-chip-row::-webkit-scrollbar {
    display: none;
}
</style>
