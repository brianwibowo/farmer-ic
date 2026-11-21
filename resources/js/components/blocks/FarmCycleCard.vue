<script setup lang="ts">
import { computed } from 'vue';
import type { FarmCycleDetail } from '@/types/farm-cycles';

const props = defineProps<{ cycle: FarmCycleDetail }>();
const number = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 });
const createdOn = computed(() =>
    props.cycle.created_at
        ? new Intl.DateTimeFormat('id-ID', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
              timeZone: 'Asia/Jakarta',
          }).format(new Date(props.cycle.created_at))
        : '-',
);
</script>

<template>
    <article
        class="rounded-[20px] bg-card p-5"
        :aria-label="`Siklus ${cycle.cycle_number}`"
    >
        <header class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-semibold">
                Siklus {{ cycle.cycle_number }}
            </h2>
            <p class="text-xs text-muted-foreground">{{ createdOn }}</p>
        </header>
        <dl class="mt-5 grid grid-cols-2 divide-x divide-border">
            <div
                class="cycle-detail-metric flex min-w-0 flex-col px-2 py-2 text-center"
            >
                <dt
                    class="order-2 mt-3 text-xs leading-5 text-muted-foreground"
                >
                    Panen aktual
                </dt>
                <dd
                    class="order-1 inline-flex items-start justify-center gap-1"
                >
                    <span
                        class="cycle-detail-value font-bold tracking-tight tabular-nums"
                        >{{ number.format(Number(cycle.harvested_ton)) }}</span
                    >
                    <span class="mt-1 text-xs font-semibold text-primary"
                        >ton</span
                    >
                </dd>
            </div>
            <div
                class="cycle-detail-metric flex min-w-0 flex-col px-2 py-2 text-center"
            >
                <dt
                    class="order-2 mt-3 text-xs leading-5 text-muted-foreground"
                >
                    Jumlah subblok
                </dt>
                <dd
                    class="order-1 inline-flex items-start justify-center gap-1"
                >
                    <span
                        class="cycle-detail-value font-bold tracking-tight tabular-nums"
                        >{{ number.format(cycle.batch_count) }}</span
                    >
                    <span class="mt-1 text-xs font-semibold text-primary"
                        >subblok</span
                    >
                </dd>
            </div>
        </dl>
    </article>
</template>

<style scoped>
.cycle-detail-metric {
    container-type: inline-size;
}
.cycle-detail-value {
    font-size: clamp(1.375rem, 20cqi, 2.5rem);
    line-height: 1.1;
}
</style>
