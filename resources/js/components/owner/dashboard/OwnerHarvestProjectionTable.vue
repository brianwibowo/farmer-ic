<script setup lang="ts">
import type { HarvestProjection } from '@/types/harvest-projection';
import { projectionNumber } from '@/types/harvest-projection';

defineProps<{ projection: HarvestProjection; loading: boolean }>();
</script>

<template>
    <section
        class="min-w-0 overflow-hidden rounded-xl border border-border bg-card"
        aria-labelledby="farm-projection-heading"
        :aria-busy="loading"
    >
        <div
            class="flex flex-wrap items-baseline justify-between gap-2 p-4 lg:p-5"
        >
            <h2
                id="farm-projection-heading"
                class="text-base font-semibold lg:text-lg"
            >
                Proyeksi per kebun
            </h2>
            <p class="text-xs text-muted-foreground">
                {{ projection.farm_count }} kebun ·
                {{ projection.period === 'year' ? 'Per tahun' : 'Per cycle' }}
            </p>
        </div>
        <div
            v-if="!projection.rows.length"
            role="status"
            class="border-t border-border px-4 py-12 text-center"
        >
            <p class="text-base font-semibold">Belum ada kebun</p>
            <p class="mt-2 text-sm text-muted-foreground">
                Proyeksi akan muncul setelah kebun dan batch aktif dicatat.
            </p>
        </div>
        <div
            v-else
            role="region"
            aria-label="Tabel proyeksi panen per kebun, geser untuk melihat seluruh kolom"
            tabindex="0"
            class="max-h-[65vh] overflow-auto focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-ring"
        >
            <table
                class="w-full min-w-[480px] border-collapse text-left text-sm lg:text-base"
            >
                <caption class="sr-only">
                    Proyeksi panen
                    {{
                        projection.period === 'year'
                            ? 'per tahun berdasarkan total luas kebun dan frekuensi panen'
                            : 'per cycle berdasarkan total luas kebun'
                    }}.
                </caption>
                <thead class="sticky top-0 z-10 bg-muted text-muted-foreground">
                    <tr>
                        <th
                            scope="col"
                            class="w-2/5 border-y border-border px-4 py-3 font-medium lg:px-5"
                        >
                            Nama kebun
                        </th>
                        <th
                            scope="col"
                            class="border-y border-border px-4 py-3 font-medium"
                        >
                            Tanaman
                        </th>
                        <th
                            scope="col"
                            class="border-y border-border px-4 py-3 text-right font-medium lg:px-5"
                        >
                            Proyeksi panen (TON)
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="row in projection.rows" :key="row.key">
                        <th
                            scope="row"
                            class="px-4 py-4 align-top font-medium break-words lg:px-5"
                        >
                            {{ row.farm_name }}
                        </th>
                        <td class="px-4 py-4 align-top">
                            <span
                                :class="
                                    row.crop_name ? '' : 'text-muted-foreground'
                                "
                                >{{
                                    row.crop_name ?? 'Belum ada tanaman aktif'
                                }}</span
                            >
                        </td>
                        <td class="px-4 py-4 text-right align-top lg:px-5">
                            <span class="font-bold tabular-nums">{{
                                projectionNumber(row.projection_ton)
                            }}</span>
                            <p
                                v-if="row.missing_count"
                                class="mt-1 text-xs leading-5 text-muted-foreground"
                            >
                                Data luas kebun atau rumus tanaman belum tersedia
                            </p>
                            <p
                                v-else-if="!row.batch_count"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Belum ada batch aktif
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
