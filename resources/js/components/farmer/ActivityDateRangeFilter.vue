<script setup lang="ts">
import { CalendarDays, Check, RotateCcw, X } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    startDate: string;
    endDate: string;
}>();

const emit = defineEmits<{
    'update:startDate': [value: string];
    'update:endDate': [value: string];
}>();

const isOpen = ref(false);

const hasDate = computed(() => Boolean(props.startDate || props.endDate));
const buttonLabel = computed(() => {
    if (props.startDate && props.endDate) return 'Rentang tanggal';
    if (props.startDate) return 'Mulai tanggal';
    if (props.endDate) return 'Sampai tanggal';
    return 'Tanggal';
});

function clearDates(): void {
    emit('update:startDate', '');
    emit('update:endDate', '');
}
</script>

<template>
    <div class="relative shrink-0">
        <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl bg-secondary px-3.5 text-sm font-semibold text-secondary-foreground transition-colors hover:bg-secondary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            :aria-expanded="isOpen"
            aria-haspopup="dialog"
            @click="isOpen = !isOpen"
            @keydown.esc="isOpen = false"
        >
            <CalendarDays :size="17" :stroke-width="1.8" aria-hidden="true" />
            <span>{{ buttonLabel }}</span>
            <span
                v-if="hasDate"
                class="inline-flex size-5 items-center justify-center rounded-full bg-primary text-[11px] text-primary-foreground"
            >
                <Check :size="12" :stroke-width="2.4" aria-hidden="true" />
            </span>
        </button>

        <div
            v-if="isOpen"
            class="absolute top-13 right-0 z-30 w-[min(320px,calc(100vw-32px))] rounded-2xl border border-border bg-card p-4 text-foreground shadow-lg"
            role="dialog"
            aria-label="Filter rentang tanggal"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold">Filter rentang tanggal</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Tampilkan aktivitas pada periode tertentu.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex size-7 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                    aria-label="Tutup filter tanggal"
                    @click="isOpen = false"
                >
                    <X :size="16" aria-hidden="true" />
                </button>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2">
                <label
                    class="min-w-0 text-xs font-medium text-muted-foreground"
                >
                    Dari tanggal
                    <input
                        :value="startDate"
                        type="date"
                        class="mt-1 h-10 w-full min-w-0 rounded-lg border border-input bg-background px-2.5 text-xs text-foreground outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                        @input="
                            emit(
                                'update:startDate',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                </label>
                <label
                    class="min-w-0 text-xs font-medium text-muted-foreground"
                >
                    Sampai tanggal
                    <input
                        :value="endDate"
                        type="date"
                        class="mt-1 h-10 w-full min-w-0 rounded-lg border border-input bg-background px-2.5 text-xs text-foreground outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                        @input="
                            emit(
                                'update:endDate',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                </label>
            </div>

            <div
                class="mt-4 flex items-center justify-between border-t border-border pt-3"
            >
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted-foreground hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                    :disabled="!hasDate"
                    @click="clearDates"
                >
                    <RotateCcw :size="14" aria-hidden="true" />
                    Bersihkan
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground hover:brightness-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    @click="isOpen = false"
                >
                    <Check :size="14" aria-hidden="true" />
                    Terapkan
                </button>
            </div>
        </div>
    </div>
</template>
