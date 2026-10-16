<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
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
import { computed, onMounted, ref, watch } from 'vue';
import { getKebunDetail, type MappedKebun } from '@/services/kebunService';

const page = usePage();
const kebunId = computed(
    () =>
        new URL(page.url, 'http://localhost').searchParams.get('id') ??
        'kebun-001',
);
const selectedKebun = ref<MappedKebun>();
const loading = ref(true);
const error = ref('');
let requestId = 0;
const detail = computed(() =>
    selectedKebun.value
        ? {
              ...selectedKebun.value,
              location: selectedKebun.value.city,
              crop: selectedKebun.value.seedType,
              totalArea: `${new Intl.NumberFormat('id-ID').format(selectedKebun.value.availableHectare)} ha`,
              plantedArea: `${new Intl.NumberFormat('id-ID').format(selectedKebun.value.usedHectare)} ha`,
              harvestStatus: selectedKebun.value.healthStatus,
          }
        : undefined,
);

async function loadDetail(): Promise<void> {
    const request = ++requestId;
    loading.value = true;
    error.value = '';
    selectedKebun.value = undefined;
    try {
        const kebun = await getKebunDetail(kebunId.value);
        if (request !== requestId) return;
        selectedKebun.value = kebun;
        if (!kebun) error.value = 'Kebun tidak ditemukan.';
    } catch {
        if (request === requestId)
            error.value = 'Detail kebun belum dapat dimuat. Silakan coba lagi.';
    } finally {
        if (request === requestId) loading.value = false;
    }
}

onMounted(() => void loadDetail());
watch(kebunId, () => void loadDetail());

const formatRupiah = (value: number): string =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);
</script>

<template>
    <Head title="Detail Kebun" />

    <FarmerMobileShell active-key="garden">
        <div
            v-if="loading"
            class="space-y-4 p-5"
            role="status"
            aria-label="Memuat detail kebun"
        >
            <div class="h-12 w-2/3 animate-pulse rounded-xl bg-muted" />
            <div class="h-32 animate-pulse rounded-2xl bg-muted" />
            <div class="h-48 animate-pulse rounded-2xl bg-muted" />
            <span class="sr-only">Memuat detail kebun…</span>
        </div>
        <div
            v-else-if="error || !detail"
            class="space-y-4 px-5 py-12 text-center"
            role="status"
        >
            <p class="text-sm text-muted-foreground">
                {{ error || 'Kebun tidak ditemukan.' }}
            </p>
            <button
                type="button"
                class="rounded-lg bg-primary px-4 py-2 text-sm text-primary-foreground"
                @click="void loadDetail()"
            >
                Coba lagi
            </button>
            <Link
                href="/kebun"
                class="block text-sm font-medium text-secondary underline"
                >Kembali ke daftar kebun</Link
            >
        </div>
        <div v-else class="space-y-6 px-5 pt-8 pb-6">
            <header class="flex items-center gap-3">
                <Link
                    href="/kebun"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-border text-secondary transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    aria-label="Kembali ke daftar kebun"
                >
                    <ArrowLeft
                        :size="20"
                        :stroke-width="1.8"
                        aria-hidden="true"
                    />
                </Link>
                <div>
                    <p class="text-sm font-medium text-muted-foreground">
                        Kebun
                    </p>
                    <h1
                        class="text-2xl leading-8 font-semibold text-card-foreground"
                    >
                        {{ detail.name }}
                    </h1>
                </div>
            </header>

            <section class="flex gap-4" aria-labelledby="garden-summary-title">
                <img
                    :src="detail.image"
                    :alt="`Tanaman ${detail.crop} di ${detail.name}`"
                    class="h-28 w-28 shrink-0 rounded-xl object-cover"
                />
                <div class="min-w-0 flex-1">
                    <h2 id="garden-summary-title" class="sr-only">
                        Ringkasan kebun
                    </h2>
                    <div class="grid grid-cols-2 gap-x-3 gap-y-3 text-sm">
                        <span
                            class="flex min-w-0 items-center gap-2 text-card-foreground"
                        >
                            <MapPin
                                :size="17"
                                class="shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{ detail.location }}</span>
                        </span>
                        <span
                            class="flex min-w-0 items-center gap-2 text-card-foreground"
                        >
                            <Sprout
                                :size="17"
                                class="shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{ detail.crop }}</span>
                        </span>
                        <span
                            class="flex min-w-0 items-center gap-2 text-card-foreground"
                        >
                            <LandPlot
                                :size="17"
                                class="shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <span class="truncate"
                                >{{ detail.plantedArea }}/{{
                                    detail.totalArea
                                }}</span
                            >
                        </span>
                        <span
                            class="flex min-w-0 items-center gap-2 text-secondary"
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
                class="rounded-2xl bg-muted p-4"
                aria-label="Kondisi dan proyeksi kebun"
            >
                <h2 class="text-sm font-semibold text-card-foreground">
                    Kondisi tanaman · {{ detail.healthStatus }}
                </h2>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    {{ detail.plantCondition }}
                </p>
                <p class="mt-4 text-xs text-muted-foreground">
                    Proyeksi pendapatan kebun
                </p>
                <p class="mt-1 text-xl font-semibold text-success">
                    {{ formatRupiah(detail.projectedRevenue) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Estimasi demo · Tanam {{ detail.plantedAt }}
                </p>
            </section>

            <section
                class="grid grid-cols-2 gap-3"
                aria-label="Ringkasan keuangan"
            >
                <article
                    class="rounded-2xl border border-border bg-card p-4 shadow-sm"
                >
                    <div class="flex items-center gap-2 text-secondary">
                        <TrendingUp :size="18" aria-hidden="true" />
                        <h2 class="text-sm font-medium">Pendapatan</h2>
                    </div>
                    <p class="mt-3 text-xl font-semibold text-secondary">
                        {{ formatRupiah(detail.revenue) }}
                    </p>
                </article>
                <article
                    class="rounded-2xl border border-border bg-card p-4 shadow-sm"
                >
                    <div class="flex items-center gap-2 text-error">
                        <TrendingDown :size="18" aria-hidden="true" />
                        <h2 class="text-sm font-medium">Pengeluaran</h2>
                    </div>
                    <p class="mt-3 text-xl font-semibold text-error">
                        {{ formatRupiah(detail.expenses) }}
                    </p>
                </article>
            </section>

            <section class="space-y-3" aria-labelledby="plant-progress-title">
                <div class="flex items-center justify-between gap-3">
                    <h2
                        id="plant-progress-title"
                        class="text-lg font-semibold text-card-foreground"
                    >
                        Perkembangan tanaman
                    </h2>
                    <span class="text-sm font-semibold text-secondary"
                        >{{ detail.growth }}%</span
                    >
                </div>
                <div class="rounded-2xl bg-muted p-4">
                    <div
                        class="flex items-center justify-between text-sm text-card-foreground"
                    >
                        <span class="flex items-center gap-2">
                            <Activity
                                :size="18"
                                class="text-secondary"
                                aria-hidden="true"
                            />
                            Fase pertumbuhan
                        </span>
                        <span class="font-medium text-secondary">{{
                            detail.harvestStatus
                        }}</span>
                    </div>
                    <div
                        class="mt-4 h-3 overflow-hidden rounded-full bg-card"
                        aria-label="Kemajuan pertumbuhan tanaman"
                    >
                        <div
                            class="h-full rounded-full bg-primary"
                            :style="{ width: `${detail.growth}%` }"
                        />
                    </div>
                    <div
                        class="mt-3 flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <CalendarDays :size="15" aria-hidden="true" />
                        <span>Proyeksi panen {{ detail.harvestAt }}</span>
                    </div>
                </div>
            </section>

            <section
                class="rounded-2xl border border-border bg-card p-4 shadow-sm"
                aria-labelledby="ai-summary-title"
            >
                <div class="flex items-center gap-2">
                    <CircleCheck
                        :size="20"
                        class="text-primary"
                        aria-hidden="true"
                    />
                    <h2
                        id="ai-summary-title"
                        class="text-lg font-semibold text-card-foreground"
                    >
                        Ringkasan AI
                    </h2>
                </div>
                <p class="mt-3 text-sm leading-6 text-card-foreground">
                    {{ detail.summary }}
                </p>
            </section>

            <Link
                :href="`/aktivitas?kebun=${encodeURIComponent(detail.id)}`"
                class="flex min-h-12 w-full items-center justify-center rounded-xl bg-primary px-4 text-base font-semibold text-card-foreground transition-colors hover:bg-success focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                Aktivitas Kebun
            </Link>
        </div>
    </FarmerMobileShell>
</template>
