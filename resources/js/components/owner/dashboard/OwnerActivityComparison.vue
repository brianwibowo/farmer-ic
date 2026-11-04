<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { OwnerActivityComparison } from '@/types/owner-dashboard';
import OwnerChartCard from './OwnerChartCard.vue';
import { chartNumber } from './chartFormat';

const props = defineProps<{ comparison: OwnerActivityComparison }>();
const selected = ref<number | null>(null);
const selectedBucket = computed(() =>
    selected.value === null ? null : props.comparison.buckets[selected.value],
);
const maximum = computed(() =>
    Math.max(
        2,
        Math.ceil(
            Math.max(
                0,
                ...props.comparison.buckets.flatMap((bucket) => [
                    bucket.current,
                    bucket.previous,
                ]),
            ) / 2,
        ) * 2,
    ),
);
const hasActivity = computed(
    () => props.comparison.currentTotal + props.comparison.previousTotal > 0,
);
const takeaway = computed(() => {
    const { currentTotal, previousTotal } = props.comparison;
    if (!hasActivity.value) return 'Belum ada aktivitas di kedua periode.';
    if (currentTotal > previousTotal) return 'Pencatatan aktivitas meningkat.';
    if (currentTotal < previousTotal) return 'Pencatatan aktivitas menurun.';
    return 'Jumlah aktivitas sama dengan periode sebelumnya.';
});
watch(
    () => props.comparison,
    () => (selected.value = null),
);
</script>

<template>
    <OwnerChartCard
        id="owner-activity-comparison"
        title="Ritme kegiatan"
        eyebrow="Komparasi"
        description="Bandingkan jumlah catatan pada dua periode dengan durasi yang sama."
    >
        <p class="text-sm leading-6 font-medium">{{ takeaway }}</p>
        <div class="mt-3 space-y-1.5 text-xs leading-5 text-muted-foreground">
            <p class="flex items-start gap-2">
                <span
                    class="mt-1 size-2.5 shrink-0 rounded-sm border border-secondary/70 bg-primary dark:border-primary"
                    aria-hidden="true"
                />
                <span>Periode ini · {{ comparison.currentLabel }}</span>
            </p>
            <p class="flex items-start gap-2">
                <span
                    class="mt-1 size-2.5 shrink-0 rounded-sm border border-foreground/60 bg-muted"
                    aria-hidden="true"
                />
                <span>Sebelumnya · {{ comparison.previousLabel }}</span>
            </p>
        </div>

        <div v-if="hasActivity" class="mt-5 flex gap-2">
            <div
                class="flex h-40 w-5 shrink-0 flex-col justify-between text-right text-[10px] text-muted-foreground"
                aria-hidden="true"
            >
                <span>{{ chartNumber(maximum, 0) }}</span>
                <span>{{ chartNumber(maximum / 2, 0) }}</span>
                <span>0</span>
            </div>
            <div
                class="min-w-0 flex-1 overflow-x-auto rounded-sm pb-1 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                role="region"
                aria-label="Grafik perbandingan aktivitas; geser mendatar bila diperlukan"
                tabindex="0"
            >
                <div
                    class="grid gap-1.5"
                    :style="{
                        gridTemplateColumns: `repeat(${comparison.buckets.length}, minmax(44px, 1fr))`,
                    }"
                >
                    <button
                        v-for="(bucket, index) in comparison.buckets"
                        :key="index"
                        type="button"
                        class="group min-w-11 rounded-md text-center focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        :aria-pressed="selected === index"
                        :aria-label="`${bucket.label}: periode ini ${bucket.current}, sebelumnya ${bucket.previous} aktivitas`"
                        @click="selected = index"
                    >
                        <span
                            class="relative flex h-40 items-end justify-center gap-1 border-b border-border px-1"
                            aria-hidden="true"
                        >
                            <span
                                class="absolute inset-x-0 top-0 border-t border-dashed border-border"
                            />
                            <span
                                class="absolute inset-x-0 top-1/2 border-t border-dashed border-border"
                            />
                            <span
                                class="z-1 w-3.5 rounded-t-sm border border-secondary/70 bg-primary transition-opacity group-hover:opacity-80 dark:border-primary"
                                :style="{
                                    height: `${(bucket.current / maximum) * 100}%`,
                                    minHeight: bucket.current ? '3px' : '0',
                                }"
                            />
                            <span
                                class="z-1 w-3.5 rounded-t-sm border border-foreground/60 bg-muted"
                                :style="{
                                    height: `${(bucket.previous / maximum) * 100}%`,
                                    minHeight: bucket.previous ? '3px' : '0',
                                }"
                            />
                        </span>
                        <span
                            class="mt-2 block min-h-8 text-[10px] leading-4 text-muted-foreground"
                            :class="
                                selected === index
                                    ? 'font-semibold text-foreground'
                                    : ''
                            "
                            >{{ bucket.label }}</span
                        >
                    </button>
                </div>
            </div>
        </div>
        <div
            v-else
            class="mt-5 rounded-xl border border-dashed border-border bg-muted/40 px-4 py-8 text-center text-sm leading-6 text-muted-foreground"
            role="status"
        >
            Grafik akan terisi setelah aktivitas pada periode ini atau periode
            sebelumnya dicatat.
        </div>
        <p
            v-if="hasActivity"
            class="mt-3 min-h-10 rounded-lg bg-muted/50 px-3 py-2 text-xs leading-5 text-muted-foreground"
            aria-live="polite"
        >
            <template v-if="selectedBucket">
                {{ selectedBucket.label }} ·
                {{ selectedBucket.current }} aktivitas periode ini,
                {{ selectedBucket.previous }} sebelumnya.
            </template>
            <template v-else>
                Ketuk sepasang batang untuk membaca perbandingannya.
            </template>
        </p>
        <details class="mt-1 text-xs">
            <summary
                class="min-h-11 cursor-pointer content-center rounded-md font-medium text-secondary focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
            >
                Lihat data aktivitas
            </summary>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs leading-5">
                    <caption class="sr-only">
                        Jumlah aktivitas per interval pada dua periode dengan
                        durasi sama
                    </caption>
                    <thead class="text-muted-foreground">
                        <tr class="border-b border-border">
                            <th scope="col" class="py-2 pr-2 font-medium">
                                Urutan periode
                            </th>
                            <th scope="col" class="px-2 py-2 font-medium">
                                Ini
                            </th>
                            <th scope="col" class="py-2 pl-2 font-medium">
                                Sebelumnya
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(bucket, index) in comparison.buckets"
                            :key="index"
                            class="border-b border-border/60"
                        >
                            <th scope="row" class="py-2 pr-2 font-normal">
                                {{ bucket.label }}
                            </th>
                            <td class="px-2 py-2 tabular-nums">
                                {{ bucket.current }}
                            </td>
                            <td class="py-2 pl-2 tabular-nums">
                                {{ bucket.previous }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" class="pt-2 font-medium">Total</th>
                            <td class="px-2 pt-2 font-medium tabular-nums">
                                {{ comparison.currentTotal }}
                            </td>
                            <td class="pt-2 pl-2 font-medium tabular-nums">
                                {{ comparison.previousTotal }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </details>
        <template #footer>
            Jumlah catatan menggambarkan aktivitas yang dilaporkan, bukan ukuran
            produktivitas atau mutu pekerjaan.
        </template>
    </OwnerChartCard>
</template>
