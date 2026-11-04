<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import { computed } from 'vue';
import type { OwnerLandComparison } from '@/types/owner-dashboard';
import OwnerChartCard from './OwnerChartCard.vue';
import { chartNumber } from './chartFormat';

const props = defineProps<{ land: OwnerLandComparison }>();
const maximum = computed(() =>
    Math.max(
        1,
        ...props.land.farms.flatMap((farm) => [
            farm.totalAreaHa,
            farm.activeAreaHa,
            farm.plannedAreaHa,
        ]),
    ),
);
const series = [
    {
        key: 'totalAreaHa',
        label: 'Terdaftar',
        className: 'border border-foreground/50 bg-muted',
    },
    {
        key: 'activeAreaHa',
        label: 'Tertanam aktif',
        className: 'border border-secondary/70 bg-primary dark:border-primary',
    },
    {
        key: 'plannedAreaHa',
        label: 'Alokasi rencana',
        className:
            'border border-dashed border-secondary bg-secondary/20 dark:border-primary',
    },
] as const;
</script>

<template>
    <OwnerChartCard
        id="owner-land-comparison"
        title="Lahan dan rencana tanam"
        eyebrow="Komparasi lahan"
        description="Baca luas tercatat, yang sedang ditanami, dan alokasi batch rencana pada skala yang sama."
    >
        <p
            v-if="!land.farms.length"
            class="rounded-xl border border-dashed border-border bg-muted/40 px-4 py-8 text-center text-sm leading-6 text-muted-foreground"
            role="status"
        >
            Data lahan akan muncul setelah kebun dicatat pada tenant ini.
        </p>
        <template v-else>
            <div
                class="mb-4 flex justify-between text-[10px] text-muted-foreground"
                aria-hidden="true"
            >
                <span>0 ha</span>
                <span>{{ chartNumber(maximum) }} ha</span>
            </div>
            <div class="space-y-6">
                <article
                    v-for="farm in land.farms"
                    :key="farm.id"
                    class="min-w-0"
                >
                    <Link
                        :href="farm.href"
                        class="-mt-2 mb-2 flex min-h-11 items-center justify-between gap-2 rounded-md text-sm leading-5 font-medium hover:text-secondary focus-visible:outline-2 focus-visible:outline-ring dark:hover:text-primary"
                    >
                        <span class="min-w-0 break-words">{{ farm.name }}</span>
                        <ArrowUpRight
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </Link>
                    <dl class="space-y-3">
                        <div v-for="item in series" :key="item.key">
                            <div
                                class="mb-1.5 flex justify-between gap-2 text-xs leading-5"
                            >
                                <dt class="text-muted-foreground">
                                    {{ item.label }}
                                </dt>
                                <dd class="shrink-0 tabular-nums">
                                    {{ chartNumber(farm[item.key]) }} ha
                                </dd>
                            </div>
                            <div
                                class="h-2.5 overflow-hidden rounded-sm bg-muted/50"
                                aria-hidden="true"
                            >
                                <div
                                    class="h-full rounded-sm"
                                    :class="item.className"
                                    :style="{
                                        width: `${(farm[item.key] / maximum) * 100}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </dl>
                </article>
            </div>
        </template>
        <details v-if="land.farms.length" class="mt-3 text-xs">
            <summary
                class="min-h-11 cursor-pointer content-center rounded-md font-medium text-secondary focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
            >
                Lihat data lahan
            </summary>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs leading-5">
                    <caption class="sr-only">
                        Perbandingan luas dalam hektare per kebun
                    </caption>
                    <thead class="text-muted-foreground">
                        <tr class="border-b border-border">
                            <th scope="col" class="py-2 pr-2 font-medium">
                                Kebun
                            </th>
                            <th
                                v-for="item in series"
                                :key="item.key"
                                scope="col"
                                class="px-2 py-2 font-medium"
                            >
                                {{ item.label }} (ha)
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="farm in land.farms"
                            :key="farm.id"
                            class="border-b border-border/60"
                        >
                            <th scope="row" class="py-2 pr-2 font-normal">
                                {{ farm.name }}
                            </th>
                            <td
                                v-for="item in series"
                                :key="item.key"
                                class="px-2 py-2 tabular-nums"
                            >
                                {{ chartNumber(farm[item.key]) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>
        <template #footer>
            Luas tertanam dan alokasi rencana adalah ukuran terpisah. Keduanya
            tidak dijumlahkan sebagai pemakaian lahan.
        </template>
    </OwnerChartCard>
</template>
