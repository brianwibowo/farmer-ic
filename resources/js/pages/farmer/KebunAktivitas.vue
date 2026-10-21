<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Clock, Search, SquareActivity, User } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import ActivityPeriodFilter from '@/components/farmer/ActivityPeriodFilter.vue';
import ActivityTypeFilterDropdown from '@/components/farmer/ActivityTypeFilterDropdown.vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import {
    activityTypeOptions,
    collectActivityMonths,
    matchesActivityTimeFilter,
    type ActivityLog,
    type ActivityTimeFilter,
    type ActivityType,
} from '@/data/activityLogs';
import { listActivityLogs } from '@/services/activityLogService';
import { listKebun } from '@/services/kebunService';

const activities = ref<ActivityLog[]>([]);
const searchQuery = ref('');
const selectedType = ref<'' | 'all' | ActivityType>('');
const selectedTimeFilter = ref<ActivityTimeFilter>('all');
const kebunParam = ref('');
const kebunList = ref<Awaited<ReturnType<typeof listKebun>>>([]);

function initialType(value: string | null): '' | 'all' | ActivityType {
    if (value === 'all') return 'all';

    return activityTypeOptions.some((option) => option.value === value)
        ? (value as ActivityType)
        : '';
}

function initialTime(value: string | null): ActivityTimeFilter {
    if (value === 'week' || value === 'month') return value;
    if (value && /^month:\d{4}-\d{2}$/.test(value)) {
        return value as ActivityTimeFilter;
    }

    return 'all';
}

const isLoading = ref(true);
const errorMessage = ref('');

const availableMonths = computed(() => collectActivityMonths(activities.value));

const kebunSubtitle = computed(() => {
    if (kebunParam.value) {
        const match = kebunList.value.find(
            (item) => item.id === kebunParam.value,
        );
        if (match) return match.name;
    }

    return 'Kebon A';
});

const backHref = computed(() => {
    if (kebunParam.value) {
        return `/kebun/detail?id=${encodeURIComponent(kebunParam.value)}`;
    }

    return '/kebun/detail';
});

const filteredActivities = computed(() => {
    const query = searchQuery.value.trim().toLocaleLowerCase();

    return activities.value.filter((item) => {
        if (kebunParam.value && item.kebunId !== kebunParam.value) {
            return false;
        }

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

        const matchesTime = matchesActivityTimeFilter(
            item.timestamp,
            selectedTimeFilter.value,
        );

        return matchesSearch && matchesType && matchesTime;
    });
});

function buildListUrl(): string {
    const params = new URLSearchParams();

    if (kebunParam.value) params.set('kebun', kebunParam.value);
    if (searchQuery.value.trim()) params.set('q', searchQuery.value.trim());
    if (selectedType.value && selectedType.value !== 'all') {
        params.set('type', selectedType.value);
    }
    if (selectedTimeFilter.value !== 'all') {
        params.set('time', selectedTimeFilter.value);
    }

    const query = params.toString();
    return `/kebun/aktivitas${query ? `?${query}` : ''}`;
}

function syncFiltersToUrl(): void {
    const url = buildListUrl();
    const state = window.history.state;

    window.history.replaceState(
        state && typeof state === 'object' ? { ...state, url } : state,
        '',
        url,
    );
}

function detailHrefFor(item: ActivityLog): string {
    const params = new URLSearchParams({
        id: item.id,
        from: 'kebun',
        back: buildListUrl(),
    });

    if (kebunParam.value) {
        params.set('kebun', kebunParam.value);
    }

    return `/rincian-aktivitas?${params.toString()}`;
}

watch([searchQuery, selectedType, selectedTimeFilter], syncFiltersToUrl);

function thumbnailFor(item: ActivityLog): string {
    return item.photos[0]?.image ?? '/images/kebun-placeholder.svg';
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

onMounted(async () => {
    const params = new URLSearchParams(window.location.search);
    kebunParam.value = params.get('kebun') ?? '';
    searchQuery.value = params.get('q') ?? '';
    selectedType.value = initialType(params.get('type'));
    selectedTimeFilter.value = initialTime(params.get('time'));

    [kebunList.value] = await Promise.all([listKebun(), loadActivities()]);
});
</script>

<template>
    <Head :title="`Aktivitas - ${kebunSubtitle}`" />

    <FarmerMobileShell active-key="garden">
        <div data-kebun-aktivitas-list class="px-4 pt-4 pb-5">
            <!-- Header Row: Back Button + Title/Subtitle -->
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-2.5">
                    <Link
                        :href="backHref"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-border bg-card text-secondary transition-colors hover:border-primary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        aria-label="Kembali ke Detail Kebun"
                    >
                        <ArrowLeft :size="18" :stroke-width="1.8" />
                    </Link>
                    <div class="min-w-0">
                        <h1
                            class="truncate text-[20px] leading-6 font-semibold text-foreground"
                        >
                            Aktivitas
                        </h1>
                        <p
                            class="mt-0.5 truncate text-[16px] leading-5 font-semibold text-secondary"
                        >
                            {{ kebunSubtitle }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Search & Activity Type Filter Row -->
            <div class="mt-4 flex gap-2">
                <label class="relative min-w-0 flex-1">
                    <span class="sr-only">Cari aktivitas kebun</span>
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

                <ActivityTypeFilterDropdown v-model="selectedType" />
            </div>

            <ActivityPeriodFilter
                v-model="selectedTimeFilter"
                :months="availableMonths"
                class="mt-3"
            />

            <!-- Loading Skeleton -->
            <div
                v-if="isLoading"
                class="mt-6 space-y-6"
                aria-live="polite"
                aria-label="Memuat data aktivitas kebun"
            >
                <div
                    v-for="index in 5"
                    :key="index"
                    class="flex animate-pulse gap-5"
                >
                    <div
                        class="h-[74px] w-[78px] shrink-0 rounded-xs bg-muted"
                    />
                    <div class="flex-1 space-y-3 pt-1">
                        <div class="h-4 w-40 rounded bg-muted" />
                        <div class="h-3 w-36 rounded bg-muted" />
                        <div class="h-3 w-28 rounded bg-muted" />
                    </div>
                </div>
            </div>

            <!-- Error State -->
            <p
                v-else-if="errorMessage"
                class="mt-4 rounded-lg bg-error/10 px-3 py-2 text-sm text-error"
            >
                {{ errorMessage }}
            </p>

            <!-- Empty State -->
            <div
                v-else-if="filteredActivities.length === 0"
                class="mt-12 rounded-xl bg-muted px-5 py-8 text-center"
            >
                <SquareActivity
                    :size="34"
                    class="mx-auto text-primary"
                    aria-hidden="true"
                />
                <h2 class="mt-3 text-base font-semibold text-foreground">
                    Aktivitas tidak ditemukan
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Coba ubah kata kunci, jenis aktivitas, atau periode waktu.
                </p>
            </div>

            <!-- Activity List -->
            <ul v-else class="mt-6 space-y-8">
                <li v-for="item in filteredActivities" :key="item.id">
                    <Link
                        :href="detailHrefFor(item)"
                        class="group -m-1 flex gap-5 rounded-lg p-1 transition-colors hover:bg-muted/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        <img
                            :src="thumbnailFor(item)"
                            :alt="`Foto ${item.typeLabel} di ${kebunSubtitle}`"
                            class="h-[74px] w-[78px] shrink-0 rounded-xs object-cover"
                        />
                        <div class="min-w-0 flex-1 pt-0.5">
                            <h2
                                class="truncate text-[16px] leading-5 font-medium text-foreground group-hover:text-secondary dark:group-hover:text-primary"
                            >
                                {{ item.typeLabel }}
                            </h2>
                            <div
                                class="mt-1.5 space-y-1 text-[13px] leading-5 text-foreground"
                            >
                                <p
                                    class="flex min-w-0 items-center gap-1.5 truncate"
                                >
                                    <Clock
                                        :size="16"
                                        :stroke-width="1.8"
                                        class="shrink-0 text-primary"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">{{
                                        item.timestamp
                                    }}</span>
                                </p>
                                <p
                                    class="flex min-w-0 items-center gap-1.5 truncate"
                                >
                                    <User
                                        :size="16"
                                        :stroke-width="1.8"
                                        class="shrink-0 text-primary"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">{{
                                        item.officerName
                                    }}</span>
                                </p>
                            </div>
                        </div>
                    </Link>
                </li>
            </ul>
        </div>
    </FarmerMobileShell>
</template>

<style>
[data-kebun-aktivitas-list] {
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

main:has(> [data-kebun-aktivitas-list]) {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

main:has(> [data-kebun-aktivitas-list])::-webkit-scrollbar {
    display: none;
}
</style>
