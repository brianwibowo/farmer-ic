<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { ArrowUpRight, ChevronDown } from "@lucide/vue";
import { computed, ref } from "vue";
import OwnerDashboardCropIcon from "@/components/owner/dashboard/OwnerDashboardCropIcon.vue";
import { withOwnerFarmOrigin } from "@/lib/ownerFarmNavigation";
import { ownerArea } from "@/types/owner";
import type { OwnerDashboardLandTable } from "@/types/owner-dashboard";

const props = defineProps<{
    table: OwnerDashboardLandTable;
    loading?: boolean;
}>();
const expandedGroups = ref<string[]>([]);
const tonFormat = new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 });
const percentageFormat = new Intl.NumberFormat("id-ID", {
    maximumFractionDigits: 0,
});
const expandedSet = computed(() => new Set(expandedGroups.value));
const groupedArea = computed(() =>
    props.table.groups.reduce(
        (total, group) => total + Number(group.area_ha),
        0,
    ),
);
const isExpanded = (groupId: string) => expandedSet.value.has(groupId);
const toggleGroup = (groupId: string) => {
    expandedGroups.value = isExpanded(groupId)
        ? expandedGroups.value.filter((id) => id !== groupId)
        : [...expandedGroups.value, groupId];
};
const groupRowsId = (groupId: string) =>
    `owner-land-group-${groupId.replace(/[^a-z0-9_-]/gi, "-")}`;
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
            <div>
                <h2 id="owner-land-heading" class="text-base font-semibold">
                    Ringkasan lahan
                </h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    Dikelompokkan berdasarkan kota / kabupaten.
                </p>
            </div>
            <Link
                href="/proyeksi"
                class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-md text-sm font-medium text-secondary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
                >Lihat detail<ArrowUpRight class="size-4" aria-hidden="true"
            /></Link>
        </header>
        <div
            role="region"
            aria-label="Tabel lahan per kota atau kabupaten. Pilih baris wilayah untuk menampilkan kebun di dalamnya."
            tabindex="0"
            class="owner-land-scroll isolate overflow-auto overscroll-x-contain border-y border-border focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
            :class="loading ? 'opacity-60' : ''"
        >
            <table
                class="w-full min-w-[860px] table-fixed border-separate border-spacing-0 text-left text-sm leading-5 text-foreground 2xl:text-base 2xl:leading-6"
            >
                <caption class="sr-only">
                    Data kebun pada tenant terpilih yang memiliki kota atau
                    kabupaten, dengan total
                    {{
                        ownerArea(groupedArea)
                    }}
                    hektar.
                </caption>
                <colgroup>
                    <col class="w-[166px] lg:w-[18%]" />
                    <col class="w-[88px] lg:w-[10%]" />
                    <col class="w-[96px] lg:w-[10%]" />
                    <col class="w-36 lg:w-[15%]" />
                    <col class="w-36 lg:w-[16%]" />
                    <col />
                </colgroup>
                <thead>
                    <tr>
                        <th
                            scope="col"
                            class="sticky top-0 left-0 z-30 min-w-[166px] border-r border-b border-border bg-[#1f5b2a] px-3 py-3 font-semibold text-white"
                        >
                            Kota / kabupaten
                        </th>
                        <th
                            scope="col"
                            class="sticky top-0 z-20 border-r border-b border-border bg-[#1f5b2a] px-3 py-3 text-right font-semibold whitespace-nowrap text-white"
                        >
                            Luas (ha)
                        </th>
                        <th
                            scope="col"
                            class="sticky top-0 z-20 border-r border-b border-border bg-[#1f5b2a] px-3 py-3 font-semibold text-white"
                        >
                            Legalitas
                        </th>
                        <th
                            scope="col"
                            class="sticky top-0 z-20 border-r border-b border-border bg-[#1f5b2a] px-3 py-3 font-semibold text-white"
                        >
                            Jenis tanaman
                        </th>
                        <th
                            scope="col"
                            class="sticky top-0 z-20 border-r border-b border-border bg-[#1f5b2a] px-3 py-3 font-semibold text-white"
                        >
                            Progress
                        </th>
                        <th
                            scope="col"
                            class="sticky top-0 z-20 border-b border-border bg-[#1f5b2a] px-3 py-3 font-semibold text-white"
                        >
                            Proyeksi panen
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!table.groups.length">
                        <td
                            colspan="6"
                            class="h-24 px-4 py-6 text-muted-foreground"
                        >
                            Belum ada data lahan berkota/kabupaten pada filter
                            ini.
                        </td>
                    </tr>
                    <template v-for="group in table.groups" :key="group.id">
                        <tr>
                            <th
                                scope="row"
                                class="bg-muted sticky left-0 z-10 border-r border-b border-border p-0 text-left align-top font-medium"
                            >
                                <button
                                    type="button"
                                    class="flex min-h-16 w-full items-start gap-2 px-3 py-3 text-left transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
                                    :aria-expanded="isExpanded(group.id)"
                                    :aria-controls="groupRowsId(group.id)"
                                    @click="toggleGroup(group.id)"
                                >
                                    <ChevronDown
                                        class="mt-0.5 size-4 shrink-0 transition-transform motion-reduce:transition-none"
                                        :class="
                                            isExpanded(group.id)
                                                ? ''
                                                : '-rotate-90'
                                        "
                                        aria-hidden="true"
                                    />
                                    <span class="min-w-0"
                                        ><span class="block break-words">{{
                                            group.name
                                        }}</span
                                        ><span
                                            class="mt-1 block text-xs font-normal text-muted-foreground"
                                            >{{ group.farm_count }} kebun ·
                                            {{
                                                isExpanded(group.id)
                                                    ? "Sembunyikan"
                                                    : "Tampilkan"
                                            }}
                                            rincian</span
                                        ></span
                                    >
                                </button>
                            </th>
                            <td
                                class="border-r border-b border-border bg-primary/10 px-3 py-3 text-right align-top tabular-nums"
                            >
                                {{ ownerArea(group.area_ha) }}
                            </td>
                            <td
                                class="border-r border-b border-border bg-primary/10 px-3 py-3 align-top text-muted-foreground"
                            >
                                —
                            </td>
                            <td
                                class="border-r border-b border-border bg-primary/10 px-3 py-3 align-top"
                            >
                                <ul
                                    v-if="group.crops.length"
                                    class="space-y-1.5"
                                >
                                    <li
                                        v-for="crop in group.crops"
                                        :key="crop.id"
                                        class="flex items-center gap-2 font-medium"
                                    >
                                        <OwnerDashboardCropIcon
                                            :name="crop.name"
                                        />{{ crop.name }}
                                    </li>
                                </ul>
                                <span v-else class="text-muted-foreground"
                                    >Belum tercatat</span
                                >
                            </td>
                            <td
                                class="border-r border-b border-border bg-primary/10 px-3 py-3 align-top"
                            >
                                <template
                                    v-if="group.progress.percentage !== null"
                                    ><p class="font-medium tabular-nums">
                                        {{
                                            ownerArea(
                                                percentageFormat.format(
                                                    group.progress.percentage,
                                                ),
                                            )
                                        }}%
                                    </p></template
                                ><span v-else class="text-muted-foreground"
                                    >Tahap belum tercatat</span
                                >
                            </td>
                            <td
                                class="border-b border-border bg-primary/10 px-3 py-3 align-top"
                            >
                                <div
                                    v-if="group.projection.has_data"
                                    class="space-y-1.5"
                                >
                                    <p
                                        v-for="crop in group.projection
                                            .commodities"
                                        :key="crop.id"
                                        class="flex justify-between gap-2 font-normal"
                                    >
                                        <span class="min-w-0 text-sm">{{
                                            crop.name
                                        }}</span>
                                        <span
                                            class="shrink-0 text-sm tabular-nums"
                                            >{{
                                                tonFormat.format(crop.ton)
                                            }}
                                            ton</span
                                        >
                                    </p>
                                    <p
                                        class="flex justify-between gap-2 border-t border-border/70 pt-1 text-sm font-bold tabular-nums"
                                    >
                                        <span>Total</span>
                                        <span
                                            >{{
                                                tonFormat.format(
                                                    group.projection.total_ton,
                                                )
                                            }}
                                            ton</span
                                        >
                                    </p>
                                </div>
                                <span v-else class="text-muted-foreground"
                                    >Belum tersedia</span
                                >
                            </td>
                        </tr>
                        <template v-if="isExpanded(group.id)">
                            <tr
                                v-for="(row, index) in group.rows"
                                :id="
                                    index === 0
                                        ? groupRowsId(group.id)
                                        : undefined
                                "
                                :key="row.id"
                            >
                                <th
                                    scope="row"
                                    class="sticky left-0 z-10 border-r border-b border-border bg-card px-3 py-3 pl-9 text-left align-top font-medium"
                                >
                                    <Link
                                        :href="
                                            withOwnerFarmOrigin(
                                                row.detail_url,
                                                'home',
                                            )
                                        "
                                        class="block font-bold min-h-11 max-w-full rounded-md [overflow-wrap:anywhere] text-secondary underline decoration-secondary/30 underline-offset-4 focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
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
                                            class="block font-medium text-secondary underline decoration-secondary/30 underline-offset-4 dark:text-primary"
                                        >
                                            {{
                                                row.legality ?? "Belum tercatat"
                                            }}
                                        </span>
                                        <span
                                            v-if="
                                                row.requires_land_tenure_party
                                            "
                                            class="mt-1 block text-xs text-muted-foreground"
                                        >
                                            {{
                                                row.land_tenure_party ||
                                                "Pihak terkait belum diisi"
                                            }}
                                        </span>
                                    </Link>
                                </td>
                                <td
                                    class="border-r border-b border-border bg-card px-3 py-3 align-top"
                                >
                                    <ul
                                        v-if="row.crops.length"
                                        class="space-y-2"
                                    >
                                        <li
                                            v-for="crop in row.crops"
                                            :key="crop.id"
                                            class="flex items-start gap-2"
                                        >
                                            <OwnerDashboardCropIcon
                                                :name="crop.name"
                                            /><span class="min-w-0"
                                                ><p class="font-medium">
                                                    {{ crop.name }}
                                                </p>
                                                <p
                                                    v-if="crop.varieties.length"
                                                    class="mt-0.5 text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        crop.varieties.join(
                                                            ", ",
                                                        )
                                                    }}
                                                </p></span
                                            >
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
                                        class="font-medium tabular-nums"
                                    >
                                        {{
                                            percentageFormat.format(
                                                (row.progress.completed_stages /
                                                    row.progress.total_stages) *
                                                    100,
                                            )
                                        }}%
                                    </p>
                                    <p v-else class="text-muted-foreground">
                                        Tahap belum tercatat
                                    </p>
                                    <p
                                        v-if="
                                            row.progress.current_stages.length
                                        "
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{
                                            row.progress.current_stages.join(
                                                ", ",
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ row.progress.running_batches }} batch
                                        berjalan
                                    </p>
                                </td>
                                <td
                                    class="border-b border-border bg-card px-3 py-3 align-top"
                                >
                                    <ul
                                        v-if="row.projections.length"
                                        class="space-y-1.5"
                                    >
                                        <li
                                            v-for="crop in row.projections"
                                            :key="crop.id"
                                            class="flex justify-between gap-2 font-normal"
                                        >
                                            <span class="min-w-0">{{
                                                crop.name
                                            }}</span>
                                            <span
                                                class="shrink-0 tabular-nums"
                                                >{{
                                                    crop.ton === null
                                                        ? "Belum tersedia"
                                                        : `${tonFormat.format(crop.ton)} ton`
                                                }}</span
                                            >
                                        </li>
                                    </ul>
                                    <span v-else class="text-muted-foreground"
                                        >Belum ada proyeksi panen</span
                                    >
                                </td>
                            </tr>
                        </template>
                    </template>
                </tbody>
            </table>
        </div>
        <footer
            class="flex flex-wrap justify-end gap-x-2 px-4 py-3 text-sm font-semibold tabular-nums lg:text-base"
            aria-label="Total luas lahan yang ditampilkan"
        >
            <span>Total ditampilkan</span
            ><span>{{ ownerArea(groupedArea) }} ha</span>
        </footer>
    </section>
</template>

<style scoped>
.owner-land-scroll {
    max-height: var(
        --owner-land-max-height,
        min(24rem, max(8rem, calc(100dvh - 250px)))
    );
}
@media (min-width: 1024px) {
    .owner-land-scroll {
        --owner-land-max-height: min(36rem, 60dvh);
    }
}
</style>
