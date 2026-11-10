<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import { withOwnerFarmOrigin } from '@/lib/ownerFarmNavigation';
import { ownerArea, ownerDate, ownerStatus } from '@/types/owner';
import type { OwnerDashboardLandTable } from '@/types/owner-dashboard';

defineProps<{ table: OwnerDashboardLandTable; loading?: boolean }>();
</script>

<template>
    <section
        class="min-w-0 overflow-hidden rounded-xl border border-border bg-card"
        aria-labelledby="owner-land-heading"
        :aria-busy="loading"
    >
        <header
            class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 px-4 py-3"
        >
            <h2 id="owner-land-heading" class="text-base font-semibold">
                Ringkasan lahan
            </h2>
            <Link
                href="/proyeksi"
                class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-md text-sm font-medium text-secondary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
                >Lihat detail<ArrowUpRight class="size-4" aria-hidden="true"
            /></Link>
        </header>
        <div
            role="region"
            aria-label="Tabel lahan. Geser untuk melihat semua kolom. Header dan Site/lahan tetap terlihat."
            tabindex="0"
            class="owner-land-scroll isolate overflow-auto overscroll-x-contain border-y border-border focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
            :class="loading ? 'opacity-60' : ''"
            style="
                max-height: var(
                    --owner-land-max-height,
                    min(24rem, max(8rem, calc(100dvh - 250px)))
                );
            "
        >
            <table
                class="w-full min-w-[800px] table-fixed border-separate border-spacing-0 text-left text-sm leading-5 text-foreground 2xl:text-base 2xl:leading-6"
            >
                <caption class="sr-only">
                    Data kebun pada tenant dan filter terpilih, dengan total
                    {{
                        ownerArea(table.total_area_ha)
                    }}
                    hektar.
                </caption>
                <colgroup>
                    <col class="w-[120px] lg:w-[18%]" />
                    <col class="w-[88px] lg:w-[10%]" />
                    <col class="w-[120px] lg:w-[12%]" />
                    <col class="w-36 lg:w-[16%]" />
                    <col class="w-40 lg:w-[20%]" />
                    <col />
                </colgroup>
                <thead>
                    <tr>
                        <th
                            scope="col"
                            class="sticky top-0 left-0 z-30 min-w-[120px] border-r border-b border-border bg-muted px-3 py-3 font-semibold"
                        >
                            Site / lahan
                        </th>
                        <th
                            scope="col"
                            class="sticky top-0 z-20 border-r border-b border-border bg-muted px-3 py-3 text-right font-semibold whitespace-nowrap"
                        >
                            Luas (ha)
                        </th>
                        <th
                            scope="col"
                            class="sticky top-0 z-20 border-r border-b border-border bg-muted px-3 py-3 font-semibold"
                        >
                            Legalitas
                        </th>
                        <th
                            scope="col"
                            class="sticky top-0 z-20 border-r border-b border-border bg-muted px-3 py-3 font-semibold"
                        >
                            Jenis tanaman
                        </th>
                        <th
                            scope="col"
                            class="sticky top-0 z-20 border-r border-b border-border bg-muted px-3 py-3 font-semibold"
                        >
                            Progress
                        </th>
                        <th
                            scope="col"
                            class="sticky top-0 z-20 border-b border-border bg-muted px-3 py-3 font-semibold"
                        >
                            Resume
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!table.rows.length">
                        <td
                            colspan="6"
                            class="h-24 px-4 py-6 text-muted-foreground"
                        >
                            Belum ada data lahan pada filter ini.
                        </td>
                    </tr>
                    <tr v-for="row in table.rows" :key="row.id">
                        <th
                            scope="row"
                            class="sticky left-0 z-10 border-r border-b border-border bg-card px-3 py-3 text-left align-top font-medium"
                        >
                            <Link
                                :href="
                                    withOwnerFarmOrigin(row.detail_url, 'home')
                                "
                                class="block min-h-11 max-w-full rounded-md [overflow-wrap:anywhere] text-secondary underline decoration-secondary/30 underline-offset-4 focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
                                >{{ row.name }}</Link
                            >
                        </th>
                        <td
                            class="border-r border-b border-border bg-card px-3 py-3 text-right align-top tabular-nums"
                        >
                            {{ ownerArea(row.area_ha) }}
                        </td>
                        <td
                            class="border-r border-b border-border bg-card px-3 py-3 align-top"
                        >
                            <Link
                                :href="
                                    withOwnerFarmOrigin(
                                        row.legality_url,
                                        'home',
                                    )
                                "
                                :aria-label="`Lihat legalitas ${row.name}`"
                                class="block min-h-11 max-w-full rounded-md [overflow-wrap:anywhere] focus-visible:outline-2 focus-visible:outline-ring"
                            >
                                <span
                                    class="block font-bold text-secondary underline decoration-secondary/30 underline-offset-4 dark:text-primary"
                                >
                                    {{ row.legality ?? 'Belum tercatat' }}
                                </span>
                                <span
                                    v-if="row.requires_land_tenure_party"
                                    class="mt-1 block text-xs text-muted-foreground"
                                >
                                    {{
                                        row.land_tenure_party ||
                                        'Pihak terkait belum diisi'
                                    }}
                                </span>
                            </Link>
                        </td>
                        <td
                            class="border-r border-b border-border bg-card px-3 py-3 align-top"
                        >
                            <ul v-if="row.crops.length" class="space-y-2">
                                <li v-for="crop in row.crops" :key="crop.id">
                                    <p class="font-bold">{{ crop.name }}</p>
                                    <p
                                        v-if="crop.varieties.length"
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        {{ crop.varieties.join(', ') }}
                                    </p>
                                </li>
                            </ul>
                            <span v-else class="text-muted-foreground"
                                >Belum tercatat</span
                            >
                        </td>
                        <td
                            class="border-r border-b border-border bg-card px-3 py-3 align-top"
                        >
                            <p
                                v-if="row.progress.total_stages"
                                class="font-bold tabular-nums"
                            >
                                {{ row.progress.completed_stages }}/{{
                                    row.progress.total_stages
                                }}
                                tahap selesai
                            </p>
                            <p v-else class="text-muted-foreground">
                                Tahap belum tercatat
                            </p>
                            <p
                                v-if="row.progress.current_stages.length"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ row.progress.current_stages.join(', ') }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ row.progress.running_batches }} batch
                                berjalan
                            </p>
                        </td>
                        <td
                            class="border-b border-border bg-card px-3 py-3 align-top"
                        >
                            <template v-if="row.resume">
                                <Link
                                    :href="row.resume.detail_url"
                                    class="inline-flex min-h-11 items-start rounded-md font-bold text-secondary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
                                    >{{
                                        row.resume.title ?? 'Aktivitas tercatat'
                                    }}</Link
                                >
                                <p
                                    v-if="row.resume.description"
                                    class="mt-1 break-words"
                                >
                                    {{ row.resume.description }}
                                </p>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    {{ ownerDate(row.resume.date) }} ·
                                    {{
                                        ownerStatus[row.resume.status ?? ''] ??
                                        row.resume.status ??
                                        'Status belum tercatat'
                                    }}
                                </p>
                            </template>
                            <span v-else class="text-muted-foreground"
                                >Belum ada aktivitas tercatat</span
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <footer
            class="flex flex-wrap justify-end gap-x-2 px-4 py-3 text-sm font-semibold tabular-nums lg:text-base"
            aria-label="Total luas lahan sesuai filter"
        >
            <span>Total</span
            ><span>{{ ownerArea(table.total_area_ha) }} ha</span>
        </footer>
    </section>
</template>

<style scoped>
@media (min-width: 1024px) {
    .owner-land-scroll {
        --owner-land-max-height: min(36rem, 60dvh);
    }
}
</style>
