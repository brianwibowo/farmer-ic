<script setup lang="ts">
import { computed } from 'vue';
import { Handshake, LandPlot, Package } from '@lucide/vue';
import { executiveNumber as number } from '@/services/ownerExecutiveSummary';
import type {
    executiveCommodities,
    executiveTotals,
} from '@/services/ownerExecutiveSummary';

const props = withDefaults(
    defineProps<{
        totals: ReturnType<typeof executiveTotals>;
        commodities: ReturnType<typeof executiveCommodities>;
        partnerCount: number;
        month: string;
        snapshotLabel?: string;
        showCommercialCard?: boolean;
        loading?: boolean;
    }>(),
    { loading: false, showCommercialCard: true },
);
const utilization = computed(() =>
    Math.min(100, Math.max(0, props.totals.utilization)),
);
</script>

<template>
    <div
        id="ringkasan-kpi"
        class="grid min-w-0 grid-cols-1 items-stretch gap-[18px] md:grid-cols-2"
        :class="showCommercialCard ? 'xl:grid-cols-4' : 'xl:grid-cols-2'"
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
                class="admin-card min-h-72 bg-muted motion-safe:animate-pulse"
                :class="
                    showCommercialCard && index === 3 ? 'md:col-span-2' : ''
                "
                aria-hidden="true"
            />
        </template>
        <template v-else>
            <article
                class="admin-card min-w-0 bg-card p-4 lg:p-[18px]"
                aria-labelledby="executive-land-heading"
            >
                <header class="mb-5 flex items-start justify-between gap-2">
                    <h2
                        id="executive-land-heading"
                        class="text-sm font-semibold text-foreground"
                    >
                        Ringkasan lahan
                    </h2>
                    <LandPlot
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                </header>
                <dl class="space-y-4">
                    <div>
                        <dt class="text-xs leading-5 text-muted-foreground">
                            Total luas lahan
                        </dt>
                        <dd class="mt-1 flex flex-wrap items-baseline gap-x-2">
                            <span class="text-3xl font-semibold tabular-nums">{{
                                number(totals.area)
                            }}</span
                            ><span class="text-xs text-muted-foreground"
                                >Ha</span
                            >
                        </dd>
                    </div>
                    <div class="border-t border-border pt-3">
                        <dt class="text-xs leading-5 text-muted-foreground">
                            Luas tanam aktif · posisi hari ini
                        </dt>
                        <dd class="mt-1 flex flex-wrap items-baseline gap-x-2">
                            <span class="text-2xl font-semibold tabular-nums">{{
                                number(totals.planted)
                            }}</span
                            ><span class="text-xs text-muted-foreground"
                                >Ha</span
                            >
                        </dd>
                    </div>
                    <div class="border-t border-border pt-3">
                        <div
                            class="flex flex-wrap items-baseline justify-between gap-2"
                        >
                            <dt class="text-xs text-muted-foreground">
                                Utilisasi lahan
                            </dt>
                            <dd
                                class="text-lg font-semibold text-primary tabular-nums"
                            >
                                {{ number(totals.utilization) }}%
                            </dd>
                        </div>
                        <div
                            class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            aria-label="Utilisasi lahan"
                            :aria-valuenow="utilization"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-valuetext="`${number(totals.utilization)}% lahan tertanam`"
                        >
                            <div
                                class="h-full rounded-full bg-primary"
                                :style="{ width: `${utilization}%` }"
                            />
                        </div>
                    </div>
                </dl>
                <p class="mt-2 text-xs leading-5 text-muted-foreground">
                    {{ number(totals.unused) }} Ha belum ditanam
                </p>
                <!-- <p
                    v-if="snapshotLabel"
                    class="mt-1 text-xs leading-5 text-muted-foreground"
                >
                    Posisi {{ snapshotLabel }}
                </p> -->
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
                            }}</span
                            ><span class="text-xs text-muted-foreground"
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
                class="admin-card flex min-w-0 flex-col bg-card p-4 lg:p-[18px]"
                :class="showCommercialCard ? 'md:col-span-2' : ''"
                aria-labelledby="executive-stock-heading"
            >
                <header class="mb-3 flex items-start justify-between gap-2">
                    <h2
                        id="executive-stock-heading"
                        class="text-sm font-semibold text-foreground"
                    >
                        Stok panen tersedia
                    </h2>
                    <Package
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                </header>
                <p class="flex flex-wrap items-baseline gap-x-2 text-primary">
                    <span class="admin-metric-value tabular-nums">{{
                        number(totals.stock)
                    }}</span
                    ><span
                        v-if="totals.stock !== null"
                        class="text-sm font-medium"
                        >ton</span
                    >
                </p>
                <ul
                    v-if="totals.stock !== null && commodities.length"
                    class="mt-4 divide-y divide-border border-t border-border"
                    aria-label="Stok panen per komoditas"
                >
                    <li
                        v-for="commodity in commodities"
                        :key="commodity.id"
                        class="flex min-w-0 items-baseline justify-between gap-4 py-3"
                    >
                        <span
                            class="min-w-0 text-sm break-words text-muted-foreground"
                            >{{ commodity.name }}</span
                        >
                        <span
                            class="shrink-0 text-base font-semibold tabular-nums"
                            >{{ number(commodity.stock) }}
                            <span
                                class="text-xs font-normal text-muted-foreground"
                                >ton</span
                            ></span
                        >
                    </li>
                </ul>
                <p
                    v-else
                    role="status"
                    class="mt-4 text-sm text-muted-foreground"
                >
                    Belum tersedia. Saldo stok setelah pengiriman dan
                    penyesuaian belum tercatat.
                </p>
                <!-- <p class="mt-auto pt-3 text-xs leading-5 text-muted-foreground">
                    Stok tersedia setelah pengiriman dan penyesuaian<span
                        v-if="snapshotLabel"
                    >
                        · posisi {{ snapshotLabel }}</span
                    >.
                </p> -->
            </article>
        </template>
    </div>
</template>
