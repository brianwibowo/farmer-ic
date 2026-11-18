<script setup lang="ts">
import { useFarmCycles } from '@/composables/useFarmCycles';
const { cycles } = useFarmCycles();
const number = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 });
</script>

<template>
    <section
        v-if="cycles.length"
        class="rounded-[20px] bg-card p-5"
        aria-label="Ringkasan siklus lahan"
    >
        <h2 class="mb-4 text-base font-semibold">Siklus lahan</h2>
        <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <li
                v-for="cycle in cycles"
                :key="cycle.id"
                class="flex items-center justify-between gap-4 rounded-xl bg-muted/40 px-4 py-3"
            >
                <div>
                    <p class="text-sm font-semibold">
                        Siklus {{ cycle.cycle_number }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ cycle.batch_count }} batch
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-lg font-bold tabular-nums">
                        {{ number.format(Number(cycle.harvested_ton)) }}
                        <span class="text-xs font-medium">ton</span>
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Panen aktual
                    </p>
                </div>
            </li>
        </ul>
    </section>
</template>
