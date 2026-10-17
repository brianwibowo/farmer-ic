<script setup lang="ts">
import { CalendarDays } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { formatIsoToShortDate } from '@/data/activityLogs';

const startDate = defineModel<string>('startDate', { default: '' });
const endDate = defineModel<string>('endDate', { default: '' });

const isDateFilterOpen = ref(false);
const dateFilterRef = ref<HTMLElement | null>(null);

const hasActiveDateFilter = computed(
    () => Boolean(startDate.value) || Boolean(endDate.value),
);

const dateFilterLabel = computed(() => {
    if (startDate.value && endDate.value) {
        if (startDate.value === endDate.value) {
            return formatIsoToShortDate(startDate.value);
        }
        return `${formatIsoToShortDate(startDate.value)} – ${formatIsoToShortDate(endDate.value)}`;
    }

    if (startDate.value) {
        return `≥ ${formatIsoToShortDate(startDate.value)}`;
    }

    if (endDate.value) {
        return `≤ ${formatIsoToShortDate(endDate.value)}`;
    }

    return 'Tanggal';
});

function resetDateFilter(): void {
    startDate.value = '';
    endDate.value = '';
}

function handleDocumentClick(event: MouseEvent): void {
    const target = event.target as Node | null;
    if (
        isDateFilterOpen.value &&
        dateFilterRef.value &&
        target &&
        !dateFilterRef.value.contains(target)
    ) {
        isDateFilterOpen.value = false;
    }
}

onMounted(() => document.addEventListener('pointerdown', handleDocumentClick));

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleDocumentClick);
});
</script>

<template>
    <div ref="dateFilterRef" class="relative shrink-0">
        <button
            type="button"
            aria-label="Filter rentang tanggal aktivitas"
            :aria-expanded="isDateFilterOpen"
            aria-haspopup="dialog"
            class="inline-flex h-[38px] items-center gap-2 rounded-lg bg-secondary px-3 text-[13px] font-medium text-secondary-foreground shadow-xs transition-opacity hover:opacity-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            @click="isDateFilterOpen = !isDateFilterOpen"
        >
            <span class="max-w-[125px] truncate">{{ dateFilterLabel }}</span>
            <CalendarDays
                :size="17"
                :stroke-width="1.8"
                class="shrink-0 text-secondary-foreground"
                aria-hidden="true"
            />
        </button>

        <div
            v-if="isDateFilterOpen"
            role="dialog"
            aria-label="Pilih rentang tanggal"
            class="absolute top-[calc(100%+6px)] right-0 z-30 w-[264px] rounded-xl border border-border bg-card p-3.5 shadow-lg"
        >
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-foreground">
                    Filter Rentang Tanggal
                </span>
                <button
                    v-if="hasActiveDateFilter"
                    type="button"
                    class="text-xs font-medium text-secondary hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    @click="resetDateFilter"
                >
                    Reset
                </button>
            </div>

            <div class="mt-3 space-y-2.5">
                <label class="block">
                    <span
                        class="mb-1 block text-[11px] font-medium text-muted-foreground"
                    >
                        Dari Tanggal
                    </span>
                    <input
                        v-model="startDate"
                        type="date"
                        :max="endDate || undefined"
                        class="h-9 w-full rounded-lg border border-border bg-card px-2.5 text-xs text-foreground outline-none focus:border-primary focus:ring-2 focus:ring-primary/30"
                    />
                </label>

                <label class="block">
                    <span
                        class="mb-1 block text-[11px] font-medium text-muted-foreground"
                    >
                        Sampai Tanggal
                    </span>
                    <input
                        v-model="endDate"
                        type="date"
                        :min="startDate || undefined"
                        class="h-9 w-full rounded-lg border border-border bg-card px-2.5 text-xs text-foreground outline-none focus:border-primary focus:ring-2 focus:ring-primary/30"
                    />
                </label>
            </div>

            <div class="mt-3 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    @click="resetDateFilter"
                >
                    Hapus
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-secondary px-3 py-1.5 text-xs font-semibold text-secondary-foreground transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    @click="isDateFilterOpen = false"
                >
                    Terapkan
                </button>
            </div>
        </div>
    </div>
</template>
