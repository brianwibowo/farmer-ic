<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownUp,
    Camera,
    ChevronRight,
    Clock3,
    Leaf,
    MapPin,
    Search,
    SlidersHorizontal,
    SquareActivity,
    X,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import ActivityDateRangeFilter from '@/components/farmer/ActivityDateRangeFilter.vue';
import ActivityTypeFilterDropdown from '@/components/farmer/ActivityTypeFilterDropdown.vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import { clearDemoStorage } from '@/composables/useDemoStorage';
import {
    parseIndonesianDateToIso,
    type ActivityLog,
    type ActivityType,
} from '@/data/activityLogs';
import { listActivityLogs } from '@/services/activityLogService';

const activities = ref<ActivityLog[]>([]);
const searchQuery = ref('');
const selectedType = ref<'' | 'all' | ActivityType>('');
const startDate = ref('');
const endDate = ref('');
const sortNewestFirst = ref(true);
const isLoading = ref(true);
const errorMessage = ref('');
const resetMessage = ref('');

const filteredActivities = computed(() => {
    const query = searchQuery.value.trim().toLocaleLowerCase();
    const filtered = activities.value.filter((item) => {
        const searchable = [
            item.typeLabel,
            item.timestamp,
            item.kebunName,
            item.city,
            item.officerName,
            item.seedType,
        ]
            .join(' ')
            .toLocaleLowerCase();

        const matchesSearch = !query || searchable.includes(query);
        const matchesType =
            selectedType.value === '' ||
            selectedType.value === 'all' ||
            item.type === selectedType.value;

        let matchesDate = true;
        if (startDate.value || endDate.value) {
            const itemIso = parseIndonesianDateToIso(item.timestamp);
            if (!itemIso) {
                matchesDate = false;
            } else {
                const effectiveStart =
                    startDate.value &&
                    endDate.value &&
                    startDate.value > endDate.value
                        ? endDate.value
                        : startDate.value;
                const effectiveEnd =
                    startDate.value &&
                    endDate.value &&
                    startDate.value > endDate.value
                        ? startDate.value
                        : endDate.value;

                if (effectiveStart && itemIso < effectiveStart)
                    matchesDate = false;
                if (effectiveEnd && itemIso > effectiveEnd) matchesDate = false;
            }
        }

        return matchesSearch && matchesType && matchesDate;
    });

    return [...filtered].sort((a, b) => {
        const aDate = parseIndonesianDateToIso(a.timestamp) ?? '';
        const bDate = parseIndonesianDateToIso(b.timestamp) ?? '';
        return sortNewestFirst.value
            ? bDate.localeCompare(aDate)
            : aDate.localeCompare(bDate);
    });
});

const activeFilterCount = computed(
    () =>
        Number(Boolean(searchQuery.value.trim())) +
        Number(Boolean(selectedType.value)) +
        Number(Boolean(startDate.value || endDate.value)),
);

const urgentCount = computed(
    () =>
        filteredActivities.value.filter(
            (item) =>
                item.report.kind === 'pelaporan-insiden' &&
                item.report.severity === 'Darurat',
        ).length,
);

const gardenCount = computed(
    () => new Set(filteredActivities.value.map((item) => item.kebunId)).size,
);

function thumbnailFor(item: ActivityLog): string {
    return item.photos[0]?.image ?? '/images/kebun-placeholder.svg';
}

function clearFilters(): void {
    searchQuery.value = '';
    selectedType.value = '';
    startDate.value = '';
    endDate.value = '';
}

function reportSummary(item: ActivityLog): string {
    switch (item.report.kind) {
        case 'penanaman-benih':
            return `${item.report.quantityKg} kg benih · ${item.report.areaSize}`;
        case 'pemupukan':
            return `${item.report.usageAmount} · ${item.report.subsidyStatus}`;
        case 'pengairan':
            return `${item.report.durationHours} jam · ${item.report.irrigationMethod}`;
        case 'pengecekan-pertumbuhan':
            return `${item.report.growthPhase} · tinggi ${item.report.averageHeightCm} cm`;
        case 'pestisida':
            return `${item.report.pesticideCategory} · ${item.report.tankCount} tangki`;
        case 'pelaporan-insiden':
            return `${item.report.affectedAreaHa} Ha terdampak · ${item.report.severity}`;
        case 'panen':
            return `${item.report.harvestedArea} · ${item.report.grossWeight}`;
    }
}

function isUrgent(item: ActivityLog): boolean {
    return (
        item.report.kind === 'pelaporan-insiden' &&
        item.report.severity === 'Darurat'
    );
}

async function loadActivities(): Promise<void> {
    isLoading.value = true;
    errorMessage.value = '';
    try {
        activities.value = await listActivityLogs();
    } catch {
        errorMessage.value = 'Data aktivitas belum dapat dimuat. Coba lagi.';
    } finally {
        isLoading.value = false;
    }
}

async function resetDemoData(): Promise<void> {
    clearDemoStorage();
    clearFilters();
    resetMessage.value = 'Data demo aktivitas telah direset.';
    await loadActivities();
}

onMounted(() => void loadActivities());
</script>

<template>
    <Head title="Aktivitas" />

    <FarmerMobileShell active-key="activity">
        <div data-aktivitas-list class="px-4 pt-5 pb-5">
            <header class="flex items-start justify-between gap-3">
                <div>
                    <p
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Catatan lapangan
                    </p>
                    <h1
                        class="mt-1 text-[24px] leading-8 font-semibold text-foreground"
                    >
                        Aktivitas
                    </h1>
                </div>
                <button
                    type="button"
                    class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-success hover:text-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    @click="void resetDemoData()"
                >
                    Reset demo
                </button>
            </header>

            <section
                class="mt-5 grid grid-cols-3 gap-2"
                aria-label="Ringkasan aktivitas"
            >
                <div
                    class="rounded-xl border border-border bg-muted/60 px-3 py-3"
                >
                    <p class="text-[11px] text-muted-foreground">Aktivitas</p>
                    <p
                        class="mt-1 text-lg leading-5 font-semibold text-foreground"
                    >
                        {{ filteredActivities.length }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-border bg-muted/60 px-3 py-3"
                >
                    <p class="text-[11px] text-muted-foreground">Kebun</p>
                    <p
                        class="mt-1 text-lg leading-5 font-semibold text-foreground"
                    >
                        {{ gardenCount }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-warning/40 bg-warning/10 px-3 py-3"
                >
                    <p class="text-[11px] text-muted-foreground">Perlu cek</p>
                    <p
                        class="mt-1 flex items-center gap-1 text-lg leading-5 font-semibold text-foreground"
                    >
                        <AlertTriangle
                            v-if="urgentCount"
                            :size="16"
                            class="text-warning"
                            aria-hidden="true"
                        />
                        {{ urgentCount }}
                    </p>
                </div>
            </section>

            <div class="mt-5 flex items-center gap-2">
                <label class="relative min-w-0 flex-1">
                    <span class="sr-only">Cari aktivitas</span>
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Cari kebun, kegiatan, petugas..."
                        class="h-11 w-full rounded-xl border border-primary bg-card px-3 pr-10 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-primary/20"
                    />
                    <Search
                        :size="19"
                        :stroke-width="1.8"
                        class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-primary"
                        aria-hidden="true"
                    />
                </label>
                <ActivityDateRangeFilter
                    v-model:start-date="startDate"
                    v-model:end-date="endDate"
                />
            </div>

            <div class="mt-2.5 flex items-center gap-2">
                <ActivityTypeFilterDropdown v-model="selectedType" />
                <button
                    type="button"
                    class="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 text-sm font-medium text-foreground hover:border-primary hover:text-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    @click="sortNewestFirst = !sortNewestFirst"
                >
                    <ArrowDownUp
                        :size="16"
                        class="text-primary"
                        aria-hidden="true"
                    />
                    {{ sortNewestFirst ? 'Terbaru' : 'Terlama' }}
                </button>
                <span
                    v-if="activeFilterCount"
                    class="inline-flex size-8 items-center justify-center rounded-full bg-surface text-xs font-semibold text-secondary"
                    :title="`${activeFilterCount} filter aktif`"
                >
                    {{ activeFilterCount }}
                </span>
            </div>

            <div
                v-if="activeFilterCount"
                class="mt-3 flex items-center justify-between gap-2 rounded-xl border border-primary/25 bg-surface/40 px-3 py-2"
            >
                <div
                    class="flex min-w-0 items-center gap-2 text-xs text-secondary"
                >
                    <SlidersHorizontal
                        :size="15"
                        class="shrink-0"
                        aria-hidden="true"
                    />
                    <span class="truncate"
                        >Filter aktif menampilkan
                        {{ filteredActivities.length }} aktivitas</span
                    >
                </div>
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-secondary hover:underline focus-visible:outline-2 focus-visible:outline-ring"
                    @click="clearFilters"
                >
                    <X :size="14" aria-hidden="true" />
                    Reset filter
                </button>
            </div>

            <div
                v-if="resetMessage"
                class="mt-3 flex items-center justify-between gap-3 rounded-xl border border-success/25 bg-success/10 px-3 py-2.5 text-xs text-success"
                role="status"
            >
                <span>{{ resetMessage }}</span>
                <button
                    type="button"
                    class="rounded p-1 text-success/80 hover:text-success focus-visible:outline-2 focus-visible:outline-ring"
                    aria-label="Tutup notifikasi"
                    @click="resetMessage = ''"
                >
                    <X :size="15" aria-hidden="true" />
                </button>
            </div>

            <div
                v-if="isLoading"
                class="mt-6 space-y-3"
                aria-live="polite"
                aria-label="Memuat data aktivitas"
            >
                <div
                    v-for="index in 5"
                    :key="index"
                    class="flex gap-3 rounded-2xl border border-border bg-card p-3"
                >
                    <div
                        class="size-[76px] shrink-0 animate-pulse rounded-xl bg-muted"
                    />
                    <div class="min-w-0 flex-1 space-y-3 pt-1">
                        <div class="h-4 w-3/4 animate-pulse rounded bg-muted" />
                        <div class="h-3 w-1/2 animate-pulse rounded bg-muted" />
                        <div class="h-3 w-2/3 animate-pulse rounded bg-muted" />
                    </div>
                </div>
            </div>

            <div
                v-else-if="errorMessage"
                class="mt-5 rounded-2xl border border-error/25 bg-error/10 px-4 py-4 text-sm text-error"
                role="alert"
            >
                {{ errorMessage }}
                <button
                    type="button"
                    class="mt-3 block rounded-lg bg-error px-3 py-2 text-xs font-semibold text-error-foreground"
                    @click="void loadActivities()"
                >
                    Coba lagi
                </button>
            </div>

            <div
                v-else-if="filteredActivities.length === 0"
                class="mt-10 rounded-2xl border border-dashed border-border bg-muted/50 px-5 py-10 text-center"
            >
                <SquareActivity
                    :size="36"
                    class="mx-auto text-primary"
                    aria-hidden="true"
                />
                <h2 class="mt-3 text-base font-semibold text-foreground">
                    Aktivitas tidak ditemukan
                </h2>
                <p class="mt-1 text-sm leading-5 text-muted-foreground">
                    Coba ubah kata kunci atau hapus filter untuk melihat catatan
                    lainnya.
                </p>
                <button
                    type="button"
                    class="mt-4 rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground"
                    @click="clearFilters"
                >
                    Tampilkan semua
                </button>
            </div>

            <ul v-else class="mt-5 space-y-3" aria-label="Daftar aktivitas">
                <li v-for="item in filteredActivities" :key="item.id">
                    <Link
                        :href="`/rincian-aktivitas?id=${item.id}`"
                        class="group flex gap-3 rounded-2xl border border-border bg-card p-3 transition-colors hover:border-primary hover:bg-muted/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        <div
                            class="relative size-[76px] shrink-0 overflow-hidden rounded-xl bg-muted"
                        >
                            <img
                                :src="thumbnailFor(item)"
                                :alt="`Foto ${item.typeLabel} di ${item.kebunName}`"
                                class="h-full w-full object-cover"
                            />
                            <span
                                class="absolute right-1.5 bottom-1.5 inline-flex items-center gap-1 rounded-md bg-foreground/75 px-1.5 py-1 text-[10px] font-semibold text-background"
                            >
                                <Camera :size="11" aria-hidden="true" />
                                {{ item.photos.length }}
                            </span>
                        </div>
                        <div class="min-w-0 flex-1 py-0.5">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <span
                                        class="inline-flex max-w-full truncate rounded-md bg-surface px-2 py-1 text-[10px] leading-3 font-semibold text-secondary"
                                        >{{ item.typeLabel }}</span
                                    >
                                    <h2
                                        class="mt-1 truncate text-[16px] leading-5 font-semibold text-foreground group-hover:text-secondary"
                                    >
                                        {{ item.kebunName }}
                                    </h2>
                                </div>
                                <span
                                    v-if="isUrgent(item)"
                                    class="inline-flex shrink-0 rounded-full bg-error/10 px-2 py-1 text-[10px] font-semibold text-error"
                                    >Darurat</span
                                >
                                <ChevronRight
                                    v-else
                                    :size="18"
                                    class="mt-1 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary"
                                    aria-hidden="true"
                                />
                            </div>
                            <div
                                class="mt-2 grid grid-cols-1 gap-1 text-xs text-muted-foreground"
                            >
                                <p
                                    class="flex min-w-0 items-center gap-1.5 truncate"
                                >
                                    <Clock3
                                        :size="14"
                                        class="shrink-0 text-primary"
                                        aria-hidden="true"
                                    /><span class="truncate">{{
                                        item.timestamp
                                    }}</span>
                                </p>
                                <p
                                    class="flex min-w-0 items-center gap-1.5 truncate"
                                >
                                    <MapPin
                                        :size="14"
                                        class="shrink-0 text-primary"
                                        aria-hidden="true"
                                    /><span class="truncate"
                                        >{{ item.city }} ·
                                        {{ item.officerName }}</span
                                    >
                                </p>
                            </div>
                            <p
                                class="mt-2 flex items-center gap-1.5 truncate text-xs font-medium text-secondary"
                            >
                                <Leaf
                                    :size="13"
                                    class="shrink-0"
                                    aria-hidden="true"
                                />{{ reportSummary(item) }}
                            </p>
                        </div>
                    </Link>
                </li>
            </ul>
        </div>
    </FarmerMobileShell>
</template>

<style>
[data-aktivitas-list] {
    color-scheme: light;
}

main:has(> [data-aktivitas-list]) {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

main:has(> [data-aktivitas-list])::-webkit-scrollbar {
    display: none;
}
</style>
