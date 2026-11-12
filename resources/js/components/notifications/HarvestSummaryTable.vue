<script setup lang="ts">
import { computed } from 'vue';
import { CircleAlert, Wheat } from '@lucide/vue';
import type { HarvestCommodity, HarvestSummary } from '@/types/notifications';
import { number } from './format';

const props = defineProps<{ summary: HarvestSummary; label: string }>();
const harvestCount = computed(() =>
    props.summary.commodities.reduce(
        (sum, crop) => sum + crop.harvest_count,
        0,
    ),
);
function harvest(crop: HarvestCommodity): string {
    return crop.harvest_count > 0 && crop.harvest_missing === crop.harvest_count
        ? 'Belum diisi'
        : number(crop.harvest_ton) + ' ton';
}
</script>

<template>
    <div class="@container space-y-3">
        <p
            v-if="harvestCount === 0"
            class="flex items-start gap-2 rounded-lg bg-muted/60 px-3 py-2.5 text-sm text-muted-foreground"
        >
            <Wheat class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            Tidak ada panen yang terjadi pada periode ini.
        </p>
        <div v-if="summary.commodities.length" class="hidden @xl:block">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">
                    {{
                        label
                    }}
                </caption>
                <thead
                    class="border-b border-border text-xs text-muted-foreground"
                >
                    <tr>
                        <th scope="col" class="py-2 pr-3 font-medium">
                            Komoditas
                        </th>
                        <th
                            scope="col"
                            class="px-3 py-2 text-right font-medium"
                        >
                            Target / siklus
                        </th>
                        <th
                            scope="col"
                            class="px-3 py-2 text-right font-medium"
                        >
                            Panen periode ini
                        </th>
                        <th
                            scope="col"
                            class="py-2 pl-3 text-right font-medium"
                        >
                            Luas panen
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="crop in summary.commodities" :key="crop.name">
                        <th scope="row" class="py-3 pr-3 align-top font-medium">
                            {{ crop.name }}
                        </th>
                        <td class="px-3 py-3 text-right align-top tabular-nums">
                            {{
                                crop.target_ton === null
                                    ? 'Belum tersedia'
                                    : number(crop.target_ton) + ' ton'
                            }}
                            <span
                                v-if="crop.target_missing > 0"
                                class="block text-xs text-warning"
                                >Target belum lengkap</span
                            >
                        </td>
                        <td
                            class="px-3 py-3 text-right align-top font-semibold text-primary tabular-nums"
                        >
                            {{ harvest(crop) }}
                            <span
                                v-if="crop.harvest_missing > 0"
                                class="block text-xs font-normal text-warning"
                            >
                                {{ crop.harvest_missing }} laporan belum
                                memiliki berat
                            </span>
                        </td>
                        <td class="py-3 pl-3 text-right align-top tabular-nums">
                            {{ number(crop.harvest_area_ha, 4) }} ha
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <ul class="divide-y divide-border @xl:hidden" :aria-label="label">
            <li
                v-for="crop in summary.commodities"
                :key="crop.name"
                class="py-3 first:pt-0 last:pb-0"
            >
                <h4 class="mb-2 text-sm font-semibold">{{ crop.name }}</h4>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Target per siklus
                        </dt>
                        <dd class="mt-1 font-medium tabular-nums">
                            {{
                                crop.target_ton === null
                                    ? 'Belum tersedia'
                                    : number(crop.target_ton) + ' ton'
                            }}
                        </dd>
                    </div>
                    <div class="text-right">
                        <dt class="text-xs text-muted-foreground">
                            Panen periode ini
                        </dt>
                        <dd
                            class="mt-1 font-semibold text-primary tabular-nums"
                        >
                            {{ harvest(crop) }}
                        </dd>
                    </div>
                </dl>
                <p class="mt-2 text-xs text-muted-foreground">
                    Luas panen periode ini:
                    {{ number(crop.harvest_area_ha, 4) }} ha
                </p>
                <p
                    v-if="crop.target_missing > 0 || crop.harvest_missing > 0"
                    class="mt-2 flex items-start gap-1.5 text-xs text-warning"
                >
                    <CircleAlert class="size-3.5 shrink-0" aria-hidden="true" />
                    {{
                        crop.harvest_missing > 0
                            ? crop.harvest_missing +
                              ' laporan belum memiliki berat panen.'
                            : 'Sebagian target belum tersedia.'
                    }}
                </p>
            </li>
        </ul>
        <p
            v-if="summary.unidentified_batches > 0"
            class="text-xs leading-5 text-warning"
        >
            {{ summary.unidentified_batches }} siklus aktif belum memiliki data
            komoditas untuk proyeksi.
        </p>
    </div>
</template>
