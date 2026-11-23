<script setup lang="ts">
import { Minus, Plus } from '@lucide/vue';
import { computed } from 'vue';
import {
    formatActivityNumber,
    stepWorkerCount,
} from '@/lib/activityInputLimits';

const props = defineProps<{ maximum: number; error?: string }>();
const count = defineModel<number>({ required: true });

const number = computed(() => Number(count.value));

function adjust(amount: -1 | 1): void {
    count.value = stepWorkerCount(count.value, amount, props.maximum);
}
</script>

<template>
    <div class="space-y-2">
        <label for="activity-worker-count" class="text-sm font-medium">
            Jumlah pekerja *
        </label>
        <div
            class="flex min-h-12 items-center rounded-xl border bg-background"
            :class="error ? 'border-error' : 'border-border'"
        >
            <button
                type="button"
                aria-label="Kurangi jumlah pekerja"
                class="m-0.5 inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-primary hover:bg-primary/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:text-muted-foreground disabled:opacity-40"
                :disabled="number <= 1"
                @click="adjust(-1)"
            >
                <Minus class="size-5" aria-hidden="true" />
            </button>
            <input
                id="activity-worker-count"
                v-model.number="count"
                type="number"
                inputmode="numeric"
                min="1"
                :max="maximum"
                step="1"
                required
                class="min-h-11 min-w-0 flex-1 rounded-lg bg-transparent px-1 text-center text-base font-semibold text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                :aria-invalid="!!error"
                :aria-describedby="
                    error
                        ? 'worker-count-help error-worker_count'
                        : 'worker-count-help'
                "
            />
            <button
                type="button"
                aria-label="Tambah jumlah pekerja"
                class="m-0.5 inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-primary hover:bg-primary/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:text-muted-foreground disabled:opacity-40"
                :disabled="number >= maximum"
                @click="adjust(1)"
            >
                <Plus class="size-5" aria-hidden="true" />
            </button>
        </div>
        <p id="worker-count-help" class="text-xs text-muted-foreground">
            Maksimal {{ formatActivityNumber(maximum) }} pekerja.
        </p>
        <p
            v-if="error"
            id="error-worker_count"
            class="text-xs text-error"
            aria-live="polite"
        >
            {{ error }}
        </p>
    </div>
</template>
