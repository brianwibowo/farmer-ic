<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ArrowUpRight,
    Camera,
    ChevronRight,
    CircleAlert,
    Clock3,
    LandPlot,
    Leaf,
    MapPin,
    Search,
    SlidersHorizontal,
    SquareActivity,
    X,
    ArrowLeft,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import ActivityPeriodFilter from '@/components/farmer/ActivityPeriodFilter.vue';
import ActivityTypeFilterDropdown from '@/components/farmer/ActivityTypeFilterDropdown.vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import { clearDemoStorage } from '@/composables/useDemoStorage';
import {
    collectActivityMonths,
    getActivityAttention,
    getActivitySearchText,
    matchesActivityTimeFilter,
    // parseIndonesianDateToIso, — NOTE (merge resolution 2026-10-19): kept for the commented date-range/sort code below.
    type ActivityLog,
    type ActivityStatusFilter,
    type ActivityTimeFilter,
    type ActivityType,
} from '@/data/activityLogs';
import type { Kebun } from '@/data/kebun';
import { listActivityLogs } from '@/services/activityLogService';
import { listKebun } from '@/services/kebunService';

const page = usePage();
const activities = ref<ActivityLog[]>([]);
const kebunList = ref<Kebun[]>([]);
const searchQuery = ref('');
const selectedType = ref<'' | 'all' | ActivityType>('');
const selectedStatus = ref<ActivityStatusFilter>('all');
const statusOptions: Array<{ value: ActivityStatusFilter; label: string }> = [
    { value: 'all', label: 'Semua' },
    { value: 'needs-check', label: 'Perlu cek' },
    { value: 'normal', label: 'Normal' },
];
const selectedTimeFilter = ref<ActivityTimeFilter>('all');
// NOTE (merge resolution 2026-10-19): the date-range inputs and sort toggle were
// removed from the UI (replaced by ActivityPeriodFilter dropdown + newest-first sorting
// in listActivityLogs()). Kept commented per review; delete once confirmed unused.
// const startDate = ref('');
// const endDate = ref('');
// const sortNewestFirst = ref(true);
const isLoading = ref(true);
const errorMessage = ref('');
const resetMessage = ref('');

const availableMonths = computed(() => collectActivityMonths(activities.value));

const kebunParam = computed(() => {
    try {
        const url = new URL(page.url, 'http://localhost');
        return (
            url.searchParams.get('kebun') || url.searchParams.get('id') || ''
        );
    } catch {
        return '';
    }
});

const currentKebun = computed(() => {
    if (!kebunParam.value) return undefined;
    return kebunList.value.find((item) => item.id === kebunParam.value);
});

const currentKebunName = computed(() => {
    if (!kebunParam.value) return '';
    if (currentKebun.value) return currentKebun.value.name;

    const match = activities.value.find(
        (item) => item.kebunId === kebunParam.value,
    );
    return match?.kebunName ?? '';
});

const backHref = computed(() => {
    if (kebunParam.value) {
        return `/kebun/detail?id=${encodeURIComponent(kebunParam.value)}`;
    }

    return '/kebun';
});

const filteredByGeneralFilters = computed(() => {
    const query = searchQuery.value.trim().toLocaleLowerCase();

    const filtered = activities.value.filter((item) => {
        if (kebunParam.value && item.kebunId !== kebunParam.value) {
            return false;
        }

        const searchable = getActivitySearchText(item);

        const matchesSearch = !query || searchable.includes(query);
        const matchesType =
            selectedType.value === '' ||
            selectedType.value === 'all' ||
            item.type === selectedType.value;

        const matchesTime = matchesActivityTimeFilter(
            item.timestamp,
            selectedTimeFilter.value,
        );

        // NOTE (merge resolution 2026-10-19): date-range matching is disabled — the
        // range UI was replaced by ActivityPeriodFilter chips. Kept commented per review.
        // let matchesDate = true;
        // if (startDate.value || endDate.value) {
        //     const itemIso = parseIndonesianDateToIso(item.timestamp);
        //     if (!itemIso) {
        //         matchesDate = false;
        //     } else {
        //         const effectiveStart =
        //             startDate.value &&
        //             endDate.value &&
        //             startDate.value > endDate.value
        //                 ? endDate.value
        //                 : startDate.value;
        //         const effectiveEnd =
        //             startDate.value &&
        //             endDate.value &&
        //             startDate.value > endDate.value
        //                 ? startDate.value
        //                 : endDate.value;
        //
        //         if (effectiveStart && itemIso < effectiveStart)
        //             matchesDate = false;
        //         if (effectiveEnd && itemIso > effectiveEnd) matchesDate = false;
        //     }
        // }

        return matchesSearch && matchesType && matchesTime;
    });

    // NOTE (merge resolution 2026-10-19): the manual newest/oldest sort is disabled —
    // listActivityLogs() already returns newest-first. Kept commented per review.
    return filtered;
});

const filteredActivities = computed(() =>
    filteredByGeneralFilters.value.filter((item) => {
        const status = getActivityAttention(item).status;
        return (
            selectedStatus.value === 'all' ||
            (selectedStatus.value === 'needs-check' &&
                status === 'needs-check') ||
            (selectedStatus.value === 'normal' && status === 'normal')
        );
    }),
);

// NOTE (merge resolution 2026-10-19): the startDate/endDate term is disabled along
// with the date-range UI (see the commented refs above); it is kept commented below.
const activeFilterCount = computed(
    () =>
        Number(Boolean(searchQuery.value.trim())) +
        Number(
            selectedType.value !== '' && selectedType.value !== 'all',
        ) +
        Number(selectedStatus.value !== 'all') +
        Number(selectedTimeFilter.value !== 'all'),
);

const needsCheckCount = computed(
    () =>
        filteredByGeneralFilters.value.filter(
            (item) => getActivityAttention(item).status === 'needs-check',
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
    selectedStatus.value = 'all';
    selectedTimeFilter.value = 'all';
    // NOTE (merge resolution 2026-10-19): date resets disabled with the range UI.
    // startDate.value = '';
    // endDate.value = '';
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

function attentionFor(item: ActivityLog) {
    return getActivityAttention(item);
}

function attentionLabelFor(item: ActivityLog): string {
    return attentionFor(item).reasons.join(' · ');
}

function kebunActivitiesHrefFor(item: ActivityLog): string {
    return `/kebun/aktivitas?kebun=${encodeURIComponent(item.kebunId)}`;
}

async function loadActivities(): Promise<void> {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const [activityData, kebunData] = await Promise.all([
            listActivityLogs(),
            listKebun(),
        ]);
        activities.value = activityData;
        kebunList.value = kebunData;
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
    <Head
        :title="
            currentKebunName ? `Aktivitas - ${currentKebunName}` : 'Aktivitas'
        "
    />

    <FarmerMobileShell :active-key="kebunParam ? 'garden' : 'activity'">
        <div data-aktivitas-list class="px-4 pt-16 pb-5">
            <div
                class="flex justify-between gap-3"
                :class="currentKebunName ? 'items-start' : 'items-center'"
            >
                <div class="flex min-w-0 items-center gap-2.5">
                    <Link
                        v-if="kebunParam"
                        :href="backHref"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-border bg-card text-secondary transition-colors hover:border-primary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        aria-label="Kembali ke Detail Kebun"
                    >
                        <ArrowLeft :size="18" :stroke-width="1.8" />
                    </Link>
                    <div class="min-w-0">
                        <h1
                            class="truncate text-[20px] leading-7 font-semibold text-foreground"
                        >
                            Aktivitas
                        </h1>
                        <p
                            v-if="currentKebunName"
                            class="mt-0.5 truncate text-[15px] leading-5 font-semibold text-secondary"
                        >
                            {{ currentKebunName }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="shrink-0 text-xs font-medium text-success underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    @click="void resetDemoData()"
                >
                    Reset demo
                </button>
            </div>

            <section
                class="mt-5 grid grid-cols-3 gap-2"
                aria-label="Ringkasan aktivitas"
            >
                <div
                    class="flex items-center gap-2 rounded-xl border border-primary/25 bg-primary/10 px-3 py-3"
                >
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/15 text-primary"
                    >
                        <Activity :size="16" aria-hidden="true" />
                    </span>
                    <p class="text-lg leading-5 font-semibold text-primary">
                        {{ filteredActivities.length }}
                    </p>
                    <p
                        class="truncate text-[11px] font-medium text-muted-foreground"
                    >
                        Aktivitas
                    </p>
                </div>
                <div
                    class="flex items-center gap-2 rounded-xl border border-secondary/20 bg-surface/35 px-3 py-3"
                >
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-secondary/10 text-secondary"
                    >
                        <LandPlot :size="16" aria-hidden="true" />
                    </span>
                    <p class="text-lg leading-5 font-semibold text-secondary">
                        {{ gardenCount }}
                    </p>
                    <p
                        class="truncate text-[11px] font-medium text-muted-foreground"
                    >
                        Kebun
                    </p>
                </div>
                <div
                    class="flex items-center gap-2 rounded-xl border border-warning/40 bg-warning/10 px-3 py-3"
                >
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-warning/20 text-warning"
                    >
                        <CircleAlert :size="16" aria-hidden="true" />
                    </span>
                    <p class="text-lg leading-5 font-semibold text-foreground">
                        {{ needsCheckCount }}
                    </p>
                    <p class="truncate text-[11px] font-medium text-foreground">
                        Perlu cek
                    </p>
                </div>
            </section>

            <div class="mt-4">
                <label class="relative min-w-0 flex-1">
                    <span class="sr-only">Cari aktivitas</span>
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Cari kata kunci"
                        class="h-[38px] w-full rounded-lg border border-primary bg-card px-3 pr-10 text-[14px] text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-primary/30"
                    />
                    <Search
                        :size="20"
                        :stroke-width="1.8"
                        class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-primary"
                        aria-hidden="true"
                    />
                </label>

                <div class="mt-2 grid grid-cols-2 gap-2">
                    <ActivityPeriodFilter
                        v-model="selectedTimeFilter"
                        :months="availableMonths"
                        stretch
                    />
                    <ActivityTypeFilterDropdown
                        v-model="selectedType"
                        stretch
                    />
                </div>
            </div>

            <div
                class="mt-3 flex items-center gap-1.5 overflow-x-auto"
                role="group"
                aria-label="Filter status aktivitas"
            >
                <button
                    v-for="option in statusOptions"
                    :key="option.value"
                    type="button"
                    :aria-pressed="selectedStatus === option.value"
                    :class="[
                        'inline-flex h-[34px] shrink-0 items-center justify-center rounded-lg border px-3 text-[13px] font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                        selectedStatus === option.value
                            ? 'border-secondary bg-secondary text-secondary-foreground'
                            : 'border-border bg-card text-foreground hover:border-primary hover:text-secondary',
                    ]"
                    @click="selectedStatus = option.value"
                >
                    {{ option.label }}
                </button>
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
                    <div class="overflow-hidden rounded-2xl border border-border bg-card">
                        <Link
                            :href="
                                kebunParam
                                    ? `/rincian-aktivitas?id=${item.id}&kebun=${encodeURIComponent(kebunParam)}`
                                    : `/rincian-aktivitas?id=${item.id}`
                            "
                            class="group flex gap-3 p-3 transition-colors hover:bg-muted/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                        <div
                            class="relative size-[120px] shrink-0 overflow-hidden rounded-xl bg-muted"
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
                                    v-if="attentionFor(item).status === 'needs-check'"
                                    :class="[
                                        'inline-flex max-w-[130px] shrink-0 truncate rounded-full px-2 py-1 text-[10px] font-semibold',
                                        attentionFor(item).priority === 'urgent'
                                            ? 'bg-error/10 text-error'
                                            : 'bg-warning/15 text-foreground',
                                    ]"
                                    :title="attentionLabelFor(item)"
                                    >{{ attentionLabelFor(item) }}</span
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
                        <!-- <div
                            v-if="attentionFor(item).status === 'needs-check'"
                            class="border-t border-border px-3 py-2"
                        >
                            <Link
                                :href="kebunActivitiesHrefFor(item)"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-secondary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                            >
                                Lihat aktivitas kebun
                                <ArrowUpRight :size="14" aria-hidden="true" />
                            </Link>
                        </div> -->
                    </div>
                </li>
            </ul>
        </div>
    </FarmerMobileShell>
</template>

<style>
[data-aktivitas-list] {
    color-scheme: light;
    --background: #f9faf9;
    --foreground: #070807;
    --surface: #abe7b2;
    --surface-foreground: #070807;
    --card: #ffffff;
    --card-foreground: #070807;
    --popover: #ffffff;
    --popover-foreground: #070807;
    --primary: #68bb6c;
    --primary-foreground: #ffffff;
    --secondary: #1c5e21;
    --secondary-foreground: #ffffff;
    --accent: #93a99f;
    --accent-foreground: #070807;
    --muted: #ebf3ed;
    --muted-foreground: #2a1a0e;
    --text-muted: #2a1a0e;
    --border: #d9d9d9;
    --input: #d9d9d9;
    --ring: #68bb6c;
    --success: #4a943e;
    --success-foreground: #ffffff;
    --warning: #edc92c;
    --warning-foreground: #070807;
    --error: #bc0202;
    --error-foreground: #ffffff;
    --destructive: #bc0202;
    --destructive-foreground: #ffffff;
    --info: #d9d9d9;
    --info-foreground: #070807;
    --disable: #e5e7ea;
    --disable-foreground: #8c9096;
}

main:has(> [data-aktivitas-list]) {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

main:has(> [data-aktivitas-list])::-webkit-scrollbar {
    display: none;
}
</style>
