<script setup lang="ts">
import { computed } from "vue";
import { Handshake, LandPlot, Package, Wheat } from "@lucide/vue";
import {
    executiveCommodityColor,
    executiveNumber as number,
} from "@/services/ownerExecutiveSummary";
import type { executiveTotals } from "@/services/ownerExecutiveSummary";

const props = withDefaults(
    defineProps<{
        totals: ReturnType<typeof executiveTotals>;
        commodities: { id: string; name: string; harvested: number }[];
        partnerCount: number;
        month: string;
        period: string;
        showCommercialCard?: boolean;
        loading?: boolean;
    }>(),
    { loading: false, showCommercialCard: true },
);
const utilization = computed(() =>
    Math.min(100, Math.max(0, props.totals.utilization)),
);
const harvestByCommodity = computed(() =>
    props.commodities
        .filter((crop) => crop.id || crop.harvested > 0)
        .sort((left, right) => left.name.localeCompare(right.name, "id")),
);
</script>

<template>
    <div
        id="ringkasan-kpi"
        class="grid min-w-0 grid-cols-1 items-stretch gap-[18px] md:grid-cols-2"
        :class="
            showCommercialCard
                ? 'xl:grid-cols-4'
                : 'md:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]'
        "
        aria-label="Indikator utama"
        :aria-busy="loading"
    >
        <template v-if="loading">
            <span role="status" class="sr-only"
                >Memuat ringkasan eksekutif…</span
            >
            <div
                v-for="index in showCommercialCard ? 3 : 2"
                :key="index"
                class="admin-card min-h-52 bg-muted motion-safe:animate-pulse"
                :class="
                    showCommercialCard && index === 3 ? 'md:col-span-2' : ''
                "
                aria-hidden="true"
            />
        </template>
        <template v-else>
            <article
                class="admin-card relative isolate min-w-0 overflow-hidden border border-primary/15 bg-card bg-linear-to-bl from-primary/8 via-card to-card p-4 lg:p-[18px]"
                aria-labelledby="executive-land-heading"
            >
                <svg
                    viewBox="0 0 240 118"
                    class="executive-land-contours pointer-events-none absolute top-0 right-0 -z-10 h-24 w-40 text-primary sm:h-28 sm:w-56"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.15"
                    aria-hidden="true"
                    focusable="false"
                >
                    <path
                        d="M245 104C214 105 197 94 172 93S131 106 108 100 84 80 58 76 20 57 21 37 38 3 65-5M245 90C214 92 196 80 175 80S136 93 115 87 94 68 68 63 34 51 35 35 49 7 88-5M245 76C218 79 200 68 180 67S144 80 123 75 105 56 79 50 49 43 50 31 73 4 115-5M245 64C222 67 204 56 186 55S153 69 132 65 118 45 93 39 66 35 67 25 97 2 144-5M245 51C224 54 211 45 192 43S162 58 143 54 131 35 110 29 85 27 86 19 123 0 170-5M245 38C228 42 214 32 199 31S175 46 157 43 147 26 130 20 107 18 110 11 145 0 195-5"
                    />
                </svg>
                <header class="flex items-center gap-2">
                    <LandPlot
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <h2 id="executive-land-heading" class="admin-section-title">
                        Ringkasan lahan
                    </h2>
                </header>
                <dl class="mt-4">
                    <div>
                        <dt class="text-xs leading-5 text-muted-foreground">
                            Total luas lahan
                        </dt>
                        <dd
                            class="mt-0.5 flex flex-wrap items-baseline gap-x-2"
                        >
                            <span
                                class="text-4xl leading-tight font-bold tracking-tight tabular-nums sm:text-5xl"
                                >{{ number(totals.area, 4) }}</span
                            >
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >ha</span
                            >
                        </dd>
                    </div>
                </dl>
                <div class="mt-4">
                    <div
                        class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1"
                    >
                        <p class="text-xs font-medium text-muted-foreground">
                            Utilisasi lahan saat ini
                        </p>
                        <p
                            class="text-base font-bold text-primary tabular-nums"
                        >
                            {{ number(totals.utilization) }}%
                        </p>
                    </div>
                    <div
                        class="mt-2 h-2 overflow-hidden rounded-full bg-primary/18"
                        role="meter"
                        aria-label="Utilisasi lahan saat ini"
                        :aria-valuenow="utilization"
                        :aria-valuemin="0"
                        :aria-valuemax="100"
                        :aria-valuetext="`${number(totals.planted, 4)} hektare tanam aktif dari ${number(totals.area, 4)} hektare`"
                    >
                        <div
                            class="h-full bg-primary"
                            :style="{ width: `${utilization}%` }"
                        />
                    </div>
                    <div
                        class="mt-2 flex flex-wrap justify-between gap-x-4 gap-y-1 text-xs leading-5 text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            <span
                                class="size-2 shrink-0 rounded-full bg-primary"
                                aria-hidden="true"
                            />
                            <span
                                ><strong class="font-semibold text-foreground"
                                    >{{ number(totals.planted, 4) }} ha</strong
                                >
                                tanam aktif</span
                            >
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span
                                class="size-2 shrink-0 rounded-full border border-primary/30 bg-primary/18"
                                aria-hidden="true"
                            />
                            <span
                                ><strong class="font-semibold text-foreground"
                                    >{{ number(totals.unused, 4) }} ha</strong
                                >
                                belum ditanam</span
                            >
                        </span>
                    </div>
                </div>
            </article>
            <article
                v-if="showCommercialCard"
                class="admin-card flex min-w-0 flex-col bg-card p-4 lg:p-[18px]"
                aria-labelledby="executive-partnership-heading"
            >
                <header class="mb-5 flex items-start justify-between gap-2">
                    <h2
                        id="executive-partnership-heading"
                        class="text-sm font-semibold text-foreground"
                    >
                        Kerja sama & penjualan
                    </h2>
                    <Handshake
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                </header>
                <dl class="space-y-5">
                    <div>
                        <dt class="text-xs leading-5 text-muted-foreground">
                            Kerja sama aktif
                        </dt>
                        <dd class="mt-1 flex flex-wrap items-baseline gap-x-2">
                            <span class="text-3xl font-semibold tabular-nums">{{
                                number(totals.agreements, 0)
                            }}</span
                            ><span class="text-xs text-muted-foreground"
                                >perjanjian</span
                            >
                        </dd>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ number(partnerCount, 0) }} mitra · per lahan
                        </p>
                    </div>
                    <div class="border-t border-border pt-4">
                        <dt class="text-xs leading-5 text-muted-foreground">
                            Kontrak penjualan
                        </dt>
                        <dd class="mt-1 flex flex-wrap items-baseline gap-x-2">
                            <span class="text-2xl font-semibold tabular-nums">{{
                                number(totals.contracts)
                            }}</span>
                            <span class="text-xs text-muted-foreground"
                                >ton/bulan</span
                            >
                        </dd>
                        <p class="mt-1 text-xs leading-5 text-muted-foreground">
                            {{ month }}
                        </p>
                    </div>
                </dl>
            </article>
            <article
                class="executive-harvest-card admin-card relative isolate flex min-w-0 flex-col overflow-hidden border border-border bg-card p-4 lg:p-[18px]"
                :class="showCommercialCard ? 'md:col-span-2' : ''"
                aria-labelledby="executive-harvest-heading"
            >
                <svg
                    viewBox="0 0 176 116"
                    class="executive-harvest-corn pointer-events-none absolute top-0 right-0 -z-10 h-auto w-[43%] max-w-44"
                    fill="currentColor"
                    aria-hidden="true"
                    focusable="false"
                >
                    <g opacity="0.45">
                        <path
                            d="M47 116C51 87 49 60 45 33L48 30C55 63 56 91 52 116Z"
                        />
                        <path d="M50 76C37 63 31 50 10 49C29 43 43 53 50 69Z" />
                        <path d="M52 86C58 66 71 63 83 65C67 70 65 84 52 91Z" />
                        <path
                            d="M135 116C138 91 143 69 151 42L154 40C147 71 143 96 140 116Z"
                        />
                        <path
                            d="M146 74C141 60 128 54 116 44C131 46 144 56 147 68Z"
                        />
                        <path
                            d="M144 86C152 66 165 60 182 60C169 66 159 71 153 81C150 85 146 88 144 86Z"
                        />
                    </g>
                    <path
                        d="M99 116C98 82 101 49 111 12L114 9C107 48 105 83 106 116Z"
                    />
                    <path d="M110 21C105 10 106 2 111-5C111 4 115 9 110 21Z" />
                    <path
                        d="M107 38C112 17 130 9 154 10C140 13 130 20 124 28C118 34 114 37 107 38Z"
                    />
                    <path
                        d="M105 51C96 43 90 29 77 21C68 15 61 16 54 19C71 5 90 17 99 31C104 39 106 46 105 51Z"
                    />
                    <path
                        d="M103 67C113 48 126 39 144 38C158 36 168 37 176 34C164 44 150 43 138 48C125 54 123 64 103 67Z"
                    />
                    <path
                        d="M102 80C87 71 77 56 64 51C50 46 43 50 35 52C56 34 77 45 89 58C96 66 101 74 102 80Z"
                    />
                    <path
                        d="M102 94C111 81 126 75 143 78C154 81 161 81 170 78C157 89 143 83 133 86C121 89 116 97 102 94Z"
                    />
                </svg>
                <header class="flex items-center gap-2">
                    <Wheat
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <h2
                        id="executive-harvest-heading"
                        class="admin-section-title"
                    >
                        Panen aktual
                    </h2>
                </header>
                <p
                    class="mt-4 text-xs leading-5 break-words text-muted-foreground"
                >
                    {{ period }}
                </p>
                <p class="mt-0.5 mb-4 flex flex-wrap items-baseline gap-x-2">
                    <span class="admin-metric-value tabular-nums">{{
                        number(totals.harvested, 3)
                    }}</span>
                    <span class="text-sm font-medium text-muted-foreground"
                        >ton</span
                    >
                </p>
                <div class="mt-auto border-t border-border pt-3">
                    <!-- <h3 class="flex items-center gap-2 text-sm font-semibold">
                        <Package
                            class="size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        Stok panen per komoditas
                    </h3> -->
                    <dl
                        v-if="harvestByCommodity.length"
                        class="divide-y divide-border"
                    >
                        <div
                            v-for="crop in harvestByCommodity"
                            :key="crop.id"
                            class="flex min-w-0 items-baseline justify-between gap-3 py-1.5 text-sm"
                        >
                            <dt class="flex min-w-0 items-center gap-2">
                                <span
                                    class="size-2 shrink-0 rounded-full bg-muted-foreground"
                                    :style="{
                                        backgroundColor:
                                            executiveCommodityColor(crop.name),
                                    }"
                                    aria-hidden="true"
                                />
                                <span class="min-w-0 break-words">{{
                                    crop.name
                                }}</span>
                            </dt>
                            <dd class="shrink-0 font-semibold tabular-nums">
                                {{ number(crop.harvested, 3) }}
                                <span
                                    class="text-xs font-normal text-muted-foreground"
                                    >ton</span
                                >
                            </dd>
                        </div>
                    </dl>
                    <p
                        v-else
                        class="mt-1 text-xs leading-5 text-muted-foreground"
                    >
                        Belum ada panen per komoditas pada periode ini.
                    </p>
                </div>
            </article>
        </template>
    </div>
</template>

<style scoped>
.executive-land-contours {
    opacity: 0.26;
    mask-image: radial-gradient(
        ellipse at top right,
        black 30%,
        transparent 76%
    );
}
.executive-harvest-card {
    background-image: radial-gradient(
        ellipse 72% 8rem at 100% 0,
        color-mix(in oklab, var(--commodity-grass) 13%, transparent),
        transparent
    );
}
.executive-harvest-corn {
    color: var(--commodity-grass);
    opacity: 0.31;
    mask-image: linear-gradient(to bottom left, black 40%, transparent 92%);
}
</style>
