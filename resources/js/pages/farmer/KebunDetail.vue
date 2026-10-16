<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    ArrowLeft,
    CalendarDays,
    CircleCheck,
    LandPlot,
    MapPin,
    Sprout,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';

const detail = {
    name: 'Detail Kebon A',
    location: 'Wonosobo',
    crop: 'Jagung',
    totalArea: '1.000 ha',
    plantedArea: '300 ha',
    plantedAt: '11 Januari 2026',
    harvestAt: '11 Maret 2026',
    revenue: 50_000_000,
    expenses: 18_500_000,
    growth: 78,
    harvestStatus: 'Tumbuh baik',
};

const formatRupiah = (value: number): string =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);

function goBack(): void {
    void router.visit('/kebun');
}

function openKebunActivities(): void {
    void router.visit('/kebun/aktivitas');
}
</script>

<template>
    <Head title="Detail Kebun" />

    <FarmerMobileShell active-key="garden">
        <div class="space-y-6 px-5 pt-8 pb-6">
            <header class="flex items-center gap-3">
                <button
                    type="button"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-[#d9d9d9] text-[#1c5e21] transition-colors hover:bg-[#d9d9d9] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#68bb6c]"
                    aria-label="Kembali ke daftar kebun"
                    @click="goBack"
                >
                    <ArrowLeft
                        :size="20"
                        :stroke-width="1.8"
                        aria-hidden="true"
                    />
                </button>
                <div>
                    <p class="text-sm font-medium text-[#93a99f]">Kebun</p>
                    <h1 class="text-2xl leading-8 font-semibold text-[#070807]">
                        {{ detail.name }}
                    </h1>
                </div>
            </header>

            <section class="flex gap-4" aria-labelledby="garden-summary-title">
                <img
                    src="/images/farmer/corn-field.jpeg"
                    alt="Tanaman jagung di kebun"
                    class="h-28 w-28 shrink-0 rounded-xl object-cover"
                />
                <div class="min-w-0 flex-1">
                    <h2 id="garden-summary-title" class="sr-only">
                        Ringkasan kebun
                    </h2>
                    <div class="grid grid-cols-2 gap-x-3 gap-y-3 text-sm">
                        <span
                            class="flex min-w-0 items-center gap-2 text-[#070807]"
                        >
                            <MapPin
                                :size="17"
                                class="shrink-0 text-[#68bb6c]"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{ detail.location }}</span>
                        </span>
                        <span
                            class="flex min-w-0 items-center gap-2 text-[#070807]"
                        >
                            <Sprout
                                :size="17"
                                class="shrink-0 text-[#68bb6c]"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{ detail.crop }}</span>
                        </span>
                        <span
                            class="flex min-w-0 items-center gap-2 text-[#070807]"
                        >
                            <LandPlot
                                :size="17"
                                class="shrink-0 text-[#68bb6c]"
                                aria-hidden="true"
                            />
                            <span class="truncate"
                                >{{ detail.plantedArea }}/{{
                                    detail.totalArea
                                }}</span
                            >
                        </span>
                        <span
                            class="flex min-w-0 items-center gap-2 text-[#1c5e21]"
                        >
                            <CircleCheck
                                :size="17"
                                class="shrink-0"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{
                                detail.harvestStatus
                            }}</span>
                        </span>
                    </div>
                </div>
            </section>

            <section
                class="grid grid-cols-2 gap-3"
                aria-label="Ringkasan keuangan"
            >
                <article
                    class="rounded-2xl border border-[#d9d9d9] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-2 text-[#1c5e21]">
                        <TrendingUp :size="18" aria-hidden="true" />
                        <h2 class="text-sm font-medium">Pendapatan</h2>
                    </div>
                    <p class="mt-3 text-xl font-semibold text-[#1c5e21]">
                        {{ formatRupiah(detail.revenue) }}
                    </p>
                </article>
                <article
                    class="rounded-2xl border border-[#d9d9d9] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-2 text-[#bc0202]">
                        <TrendingDown :size="18" aria-hidden="true" />
                        <h2 class="text-sm font-medium">Pengeluaran</h2>
                    </div>
                    <p class="mt-3 text-xl font-semibold text-[#bc0202]">
                        {{ formatRupiah(detail.expenses) }}
                    </p>
                </article>
            </section>

            <section class="space-y-3" aria-labelledby="plant-progress-title">
                <div class="flex items-center justify-between gap-3">
                    <h2
                        id="plant-progress-title"
                        class="text-lg font-semibold text-[#070807]"
                    >
                        Perkembangan tanaman
                    </h2>
                    <span class="text-sm font-semibold text-[#1c5e21]"
                        >{{ detail.growth }}%</span
                    >
                </div>
                <div class="rounded-2xl bg-[#d9d9d9] p-4">
                    <div
                        class="flex items-center justify-between text-sm text-[#070807]"
                    >
                        <span class="flex items-center gap-2">
                            <Activity
                                :size="18"
                                class="text-[#1c5e21]"
                                aria-hidden="true"
                            />
                            Fase pertumbuhan
                        </span>
                        <span class="font-medium text-[#1c5e21]">{{
                            detail.harvestStatus
                        }}</span>
                    </div>
                    <div
                        class="mt-4 h-3 overflow-hidden rounded-full bg-white"
                        aria-label="Kemajuan pertumbuhan tanaman"
                    >
                        <div
                            class="h-full rounded-full bg-[#68bb6c]"
                            :style="{ width: `${detail.growth}%` }"
                        />
                    </div>
                    <div
                        class="mt-3 flex items-center gap-2 text-xs text-[#93a99f]"
                    >
                        <CalendarDays :size="15" aria-hidden="true" />
                        <span>Proyeksi panen {{ detail.harvestAt }}</span>
                    </div>
                </div>
            </section>

            <section
                class="rounded-2xl border border-[#d9d9d9] bg-white p-4 shadow-sm"
                aria-labelledby="ai-summary-title"
            >
                <div class="flex items-center gap-2">
                    <CircleCheck
                        :size="20"
                        class="text-[#68bb6c]"
                        aria-hidden="true"
                    />
                    <h2
                        id="ai-summary-title"
                        class="text-lg font-semibold text-[#070807]"
                    >
                        Ringkasan AI
                    </h2>
                </div>
                <p class="mt-3 text-sm leading-6 text-[#070807]">
                    Pertumbuhan jagung berjalan baik. Pertahankan penyiraman
                    teratur dan pantau daun selama dua minggu ke depan.
                </p>
            </section>

            <button
                type="button"
                class="flex min-h-12 w-full items-center justify-center rounded-xl bg-[#68bb6c] px-4 text-base font-semibold text-[#070807] transition-colors hover:bg-[#4a943e] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1c5e21]"
                @click="openKebunActivities"
            >
                Aktivitas Kebun
            </button>
        </div>
    </FarmerMobileShell>
</template>
