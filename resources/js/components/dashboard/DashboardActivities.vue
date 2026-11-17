<script setup lang="ts">
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { ClipboardList, Search, Users } from '@lucide/vue';
import NumberPagination from '@/components/NumberPagination.vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import type {
    DashboardActivity,
    DashboardActivityFilters,
    DashboardActivityPagination,
    DashboardOfficer,
} from '@/types/dashboard';

const props = defineProps<{
    activities: DashboardActivity[];
    officers: DashboardOfficer[];
    filters: DashboardActivityFilters;
    pagination: DashboardActivityPagination;
    loading: boolean;
    error: string;
}>();
const emit = defineEmits<{
    change: [filters: DashboardActivityFilters, page: number];
}>();
const activitySearch = ref(props.filters.search);
const selectedOfficerId = ref<string | null>(props.filters.officer_id);
const perPage = ref(props.filters.per_page);
let searchTimer: ReturnType<typeof setTimeout> | undefined;
function syncFilters(): void {
    clearTimeout(searchTimer);
    activitySearch.value = props.filters.search;
    selectedOfficerId.value = props.filters.officer_id;
    perPage.value = props.filters.per_page;
}
watch(() => props.filters, syncFilters);
watch(
    () => props.error,
    (error) => {
        if (error) syncFilters();
    },
);
onBeforeUnmount(() => clearTimeout(searchTimer));
const selectedOfficer = computed(() =>
    props.officers.find((officer) => officer.id === selectedOfficerId.value),
);
const hasFilters = computed(() =>
    Boolean(activitySearch.value.trim() || selectedOfficerId.value),
);
const visibleActivities = computed(() => props.activities);
function requestPage(page = 1): void {
    clearTimeout(searchTimer);
    if (props.loading) return;
    emit(
        'change',
        {
            search: activitySearch.value.trim(),
            officer_id: selectedOfficerId.value,
            per_page: perPage.value,
        },
        page,
    );
}
function searchActivities(): void {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => requestPage(), 300);
}
function selectOfficer(id: string | null): void {
    selectedOfficerId.value = id;
    requestPage();
}
function resetFilters(): void {
    activitySearch.value = '';
    selectedOfficerId.value = null;
    requestPage();
}
function activityTone(status: string): string {
    const normalized = status.trim().toLocaleLowerCase('id-ID');
    return ['selesai', 'done', 'completed'].includes(normalized)
        ? 'bg-success/15 text-success'
        : ['proses', 'in_progress', 'in progress', 'progress'].includes(
                normalized,
            )
          ? 'bg-primary/15 text-primary'
          : 'bg-muted text-muted-foreground';
}
function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}
function activityLabel(status: string): string {
    return (
        (
            {
                completed: 'Selesai',
                done: 'Selesai',
                in_progress: 'Dalam proses',
                planned: 'Terjadwal',
                pending: 'Menunggu',
                cancelled: 'Dibatalkan',
            } as Record<string, string>
        )[status] ?? status
    );
}
</script>

<template>
    <section
        class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_300px]"
        :aria-busy="loading"
    >
        <article
            class="admin-card min-w-0 overflow-hidden rounded-xl bg-card"
            aria-labelledby="activities-heading"
        >
            <div
                class="flex flex-col justify-between gap-4 p-5 lg:flex-row lg:items-end"
            >
                <div>
                    <h2
                        id="activities-heading"
                        class="admin-section-title text-foreground"
                    >
                        Aktivitas lapangan
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Aktivitas terbaru yang dicatat oleh petugas pada lahan
                        dalam cakupan Anda.
                    </p>
                </div>
            </div>
            <div
                class="grid gap-3 border-t border-border bg-muted/30 px-5 py-4 sm:grid-cols-2 2xl:grid-cols-1"
            >
                <label class="space-y-2 2xl:hidden">
                    <span class="block text-xs font-medium text-foreground"
                        >Petugas</span
                    >
                    <AppSelect
                        v-model="selectedOfficerId"
                        :disabled="loading"
                        @change="requestPage()"
                        class="admin-field"
                        aria-controls="dashboard-activity-results"
                    >
                        <AppSelectOption :value="null"
                            >Semua petugas</AppSelectOption
                        >
                        <AppSelectOption
                            v-for="officer in officers"
                            :key="officer.id"
                            :value="officer.id"
                        >
                            {{ officer.name }}
                        </AppSelectOption>
                    </AppSelect>
                </label>
                <label class="block space-y-2">
                    <span class="block text-xs font-medium text-foreground"
                        >Cari kegiatan</span
                    >
                    <span class="relative block">
                        <Search
                            :size="17"
                            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <input
                            v-model="activitySearch"
                            type="search"
                            maxlength="200"
                            :disabled="loading"
                            @input="searchActivities"
                            @keydown.enter.prevent="requestPage()"
                            class="admin-field pl-10!"
                            placeholder="Cari aktivitas atau lahan"
                            aria-controls="dashboard-activity-results"
                        />
                    </span>
                </label>
            </div>
            <div
                v-if="hasFilters"
                class="flex flex-wrap items-center justify-between gap-2 border-t border-border px-5 py-3"
            >
                <p class="min-w-0 text-xs break-words text-muted-foreground">
                    Filter aktif<span v-if="selectedOfficer">
                        · {{ selectedOfficer.name }}</span
                    ><span v-if="activitySearch.trim()">
                        · “{{ activitySearch.trim() }}”</span
                    >
                </p>
                <Button
                    v-if="visibleActivities.length"
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="loading"
                    @click="resetFilters"
                    >Reset filter</Button
                >
            </div>
            <p
                v-if="error"
                role="alert"
                class="border-t border-border px-5 py-3 text-sm text-error"
            >
                {{ error }}
                <Button
                    variant="outline"
                    size="sm"
                    class="ml-2"
                    :disabled="loading"
                    @click="requestPage(pagination.current_page)"
                    >Coba lagi</Button
                >
            </p>
            <div
                id="dashboard-activity-results"
                :class="{ 'opacity-60': loading }"
            >
                <div
                    v-if="visibleActivities.length"
                    class="hidden overflow-x-auto focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring md:block"
                    role="region"
                    aria-label="Tabel aktivitas lapangan"
                    tabindex="0"
                >
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <caption class="sr-only">
                            Aktivitas terbaru sesuai pencarian dan petugas yang
                            dipilih.
                        </caption>
                        <thead
                            class="border-y border-border bg-muted/60 text-muted-foreground"
                        >
                            <tr>
                                <th scope="col" class="px-5 py-3">Kegiatan</th>
                                <th scope="col" class="px-5 py-3">Lahan</th>
                                <th scope="col" class="px-5 py-3">Petugas</th>
                                <th scope="col" class="px-5 py-3">Tanggal</th>
                                <th scope="col" class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="activityItem in visibleActivities"
                                :key="activityItem.id"
                                class="transition-colors hover:bg-muted/50 motion-reduce:transition-none"
                            >
                                <td
                                    class="px-5 py-4 font-medium text-foreground"
                                >
                                    <span class="inline-flex items-start gap-2"
                                        ><ClipboardList
                                            :size="18"
                                            class="mt-0.5 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />{{ activityItem.title }}</span
                                    >
                                    <p
                                        v-if="activityItem.description"
                                        class="mt-1 max-w-sm text-xs leading-5 font-normal break-words text-muted-foreground"
                                    >
                                        {{ activityItem.description }}
                                    </p>
                                </td>
                                <td
                                    class="px-5 py-4 text-sm text-muted-foreground"
                                >
                                    {{ activityItem.farm }}
                                </td>
                                <td
                                    class="px-5 py-4 text-sm text-muted-foreground"
                                >
                                    {{ activityItem.officerName }}
                                </td>
                                <td
                                    class="px-5 py-4 text-sm text-muted-foreground"
                                >
                                    {{ activityItem.date }}
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs leading-5 font-semibold"
                                        :class="
                                            activityTone(activityItem.status)
                                        "
                                        >{{
                                            activityLabel(activityItem.status)
                                        }}</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul
                    v-if="visibleActivities.length"
                    class="divide-y divide-border md:hidden"
                    aria-label="Daftar aktivitas lapangan"
                >
                    <li
                        v-for="activityItem in visibleActivities"
                        :key="activityItem.id"
                        class="space-y-3 p-5"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-2"
                        >
                            <h3
                                class="min-w-0 flex-1 font-semibold break-words text-foreground"
                            >
                                {{ activityItem.title }}
                            </h3>
                            <span
                                class="inline-flex max-w-full rounded-full px-2.5 py-1 text-xs leading-5 font-semibold"
                                :class="activityTone(activityItem.status)"
                                >{{ activityLabel(activityItem.status) }}</span
                            >
                        </div>
                        <p
                            v-if="activityItem.description"
                            class="text-sm leading-6 break-words text-muted-foreground"
                        >
                            {{ activityItem.description }}
                        </p>
                        <dl
                            class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 text-xs leading-5"
                        >
                            <dt class="text-muted-foreground">Lahan</dt>
                            <dd class="break-words text-foreground">
                                {{ activityItem.farm }}
                            </dd>
                            <dt class="text-muted-foreground">Petugas</dt>
                            <dd class="break-words text-foreground">
                                {{ activityItem.officerName }}
                            </dd>
                            <dt class="text-muted-foreground">Tanggal</dt>
                            <dd class="text-foreground">
                                {{ activityItem.date }}
                            </dd>
                        </dl>
                    </li>
                </ul>
                <div
                    v-if="visibleActivities.length === 0"
                    class="flex flex-col items-center gap-2 p-10 text-center"
                >
                    <ClipboardList
                        :size="28"
                        class="text-muted-foreground"
                        aria-hidden="true"
                    />
                    <p class="font-medium text-foreground">
                        {{
                            hasFilters
                                ? 'Tidak ada aktivitas yang cocok.'
                                : 'Belum ada aktivitas lapangan.'
                        }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{
                            hasFilters
                                ? 'Pilih petugas lain atau ubah kata kunci pencarian.'
                                : 'Aktivitas yang dicatat pada lahan dalam cakupan Anda akan muncul di sini.'
                        }}
                    </p>
                    <Button
                        v-if="hasFilters"
                        type="button"
                        variant="outline"
                        :disabled="loading"
                        class="mt-2"
                        @click="resetFilters"
                        >Reset filter</Button
                    >
                </div>
            </div>
            <footer
                class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3 text-xs text-muted-foreground"
            >
                <span role="status" aria-live="polite" aria-atomic="true"
                    ><template v-if="loading">Memuat aktivitas…</template>
                    <template v-else
                        >Menampilkan {{ pagination.from ?? 0 }}–{{
                            pagination.to ?? 0
                        }}
                        dari {{ pagination.total }} aktivitas.</template
                    ></span
                >
                <div class="ml-auto flex w-full flex-wrap items-center justify-between gap-3 sm:w-auto sm:justify-end">
                    <label
                        class="flex items-center gap-2"
                        for="dashboard-activity-per-page"
                    >
                        Per halaman
                        <AppSelect
                            id="dashboard-activity-per-page"
                            v-model.number="perPage"
                            class="admin-field w-auto!"
                            :disabled="loading"
                            @change="requestPage()"
                        >
                            <AppSelectOption
                                v-for="size in [10, 20, 50]"
                                :key="size"
                                :value="size"
                                >{{ size }}</AppSelectOption
                            >
                        </AppSelect>
                    </label>
                    <NumberPagination
                        class="ml-auto"
                        :current-page="pagination.current_page"
                        :total-pages="pagination.last_page"
                        :disabled="loading"
                        label="Pagination aktivitas lapangan"
                        @change="requestPage"
                    />
                </div>
            </footer>
        </article>

        <aside
            class="admin-card hidden min-w-0 self-start rounded-xl bg-card p-5 2xl:block"
            aria-labelledby="officers-heading"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2
                        id="officers-heading"
                        class="admin-section-title text-foreground"
                    >
                        Petugas
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Pilih petugas untuk melihat kegiatan yang telah dicatat.
                    </p>
                </div>
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary"
                    ><Users :size="20" aria-hidden="true"
                /></span>
            </div>
            <div class="mt-6 flex flex-col gap-3">
                <Button
                    type="button"
                    :variant="selectedOfficerId === null ? 'default' : 'ghost'"
                    class="dashboard-officer-option w-full justify-start rounded-xl text-left whitespace-normal"
                    :aria-pressed="selectedOfficerId === null"
                    :disabled="loading"
                    @click="selectOfficer(null)"
                    ><span
                        class="grid size-10 shrink-0 place-items-center rounded-full bg-background/20 text-xs font-bold"
                        ><Users :size="18" aria-hidden="true" /></span
                    ><span class="min-w-0 flex-1 space-y-1"
                        ><span class="block text-base leading-6 font-semibold"
                            >Semua petugas</span
                        ><span
                            class="block text-sm leading-5 font-semibold opacity-90"
                            >{{ pagination.scope_total }} aktivitas</span
                        ></span
                    ></Button
                >
                <Button
                    v-for="officer in officers"
                    :key="officer.id"
                    type="button"
                    :variant="
                        selectedOfficerId === officer.id ? 'default' : 'ghost'
                    "
                    class="dashboard-officer-option w-full justify-start rounded-xl text-left whitespace-normal"
                    :aria-pressed="selectedOfficerId === officer.id"
                    :disabled="loading"
                    @click="selectOfficer(officer.id)"
                    ><Avatar class="size-10 shrink-0 rounded-full"
                        ><AvatarImage
                            v-if="officer.avatarUrl"
                            :src="officer.avatarUrl"
                            :alt="officer.name"
                        /><AvatarFallback
                            class="rounded-full bg-muted text-xs font-semibold text-muted-foreground"
                            >{{ initials(officer.name) }}</AvatarFallback
                        ></Avatar
                    ><span class="min-w-0 flex-1 space-y-1"
                        ><span
                            class="block text-base leading-6 font-semibold break-words"
                            >{{ officer.name }}</span
                        ><span
                            class="block text-sm leading-5 font-semibold opacity-90"
                            >{{ officer.activityCount }} aktivitas</span
                        ><span
                            v-if="officer.latestActivityAt"
                            class="block text-sm leading-5 font-semibold opacity-90"
                            >Terakhir {{ officer.latestActivityAt }}</span
                        ></span
                    ></Button
                >
                <div
                    v-if="officers.length === 0"
                    class="rounded-xl border border-dashed border-border p-5 text-center text-sm text-muted-foreground"
                >
                    Belum ada petugas aktif yang dapat ditampilkan untuk cakupan
                    lahan ini.
                </div>
            </div>
        </aside>
    </section>
</template>
