<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    CheckCircle2,
    Clock,
    LoaderCircle,
    Search,
    ShieldAlert,
    Siren,
    SlidersHorizontal,
    X,
} from '@lucide/vue';
import {
    PopoverClose,
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { computed, reactive, ref, watch } from 'vue';
import FarmField from '@/components/farms/FarmField.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import SummaryStatCard from '@/components/SummaryStatCard.vue';
import { Button } from '@/components/ui/button';
import {
    formatIncidentDate,
    incidentSeverityClasses,
    incidentStatusClasses,
    incidentViewOptions,
    type IncidentSeverityOption,
    type IncidentTicketFilters,
    type IncidentTicketSummary,
    type IncidentTicketsPage,
    type IncidentView,
} from '@/types/incidents';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    tickets: IncidentTicketsPage;
    summary: IncidentTicketSummary;
    filters: IncidentTicketFilters;
    farms: { id: string; name: string }[];
    severities: IncidentSeverityOption[];
}>();

const currentView = computed<IncidentView>(
    () => props.filters.view || 'unresolved',
);
const loading = ref(false);
const popoverOpen = ref(false);

const state = reactive({
    search: props.filters.search ?? '',
    farm_id: props.filters.farm_id ?? '',
    severity: props.filters.severity ?? '',
    view: currentView.value as IncidentView,
    per_page: String(props.filters.per_page ?? 15),
});

watch(
    () => props.filters,
    (next) => {
        state.search = next.search ?? '';
        state.farm_id = next.farm_id ?? '';
        state.severity = next.severity ?? '';
        state.view = (next.view || 'unresolved') as IncidentView;
        state.per_page = String(next.per_page ?? 15);
    },
);

const activeFilterCount = computed(
    () => [state.farm_id, state.severity].filter(Boolean).length,
);

const hasAnyFilter = computed(
    () =>
        Boolean(state.search || activeFilterCount.value) ||
        state.view !== 'unresolved',
);

const farmName = computed(
    () => props.farms.find((f) => f.id === state.farm_id)?.name ?? '',
);
const severityName = computed(
    () => props.severities.find((s) => s.value === state.severity)?.label ?? '',
);

function viewCount(key: IncidentView): number {
    switch (key) {
        case 'unresolved':
            return props.summary.open + props.summary.in_progress;
        case 'open':
            return props.summary.open;
        case 'in_progress':
            return props.summary.in_progress;
        case 'resolved':
            return props.summary.resolved;
        case 'all':
            return props.summary.total;
    }
}

function queryParams(): Record<string, string> {
    const params: Record<string, string> = {};
    if (state.search.trim()) params.search = state.search.trim();
    if (state.farm_id) params.farm_id = state.farm_id;
    if (state.severity) params.severity = state.severity;
    if (state.view && state.view !== 'unresolved') params.view = state.view;
    if (state.per_page && state.per_page !== '15')
        params.per_page = state.per_page;
    return params;
}

function visit(): void {
    popoverOpen.value = false;
    router.get('/admin/incidents', queryParams(), {
        preserveState: true,
        preserveScroll: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

function selectView(view: IncidentView): void {
    state.view = view;
    visit();
}

function resetFilters(): void {
    state.search = '';
    state.farm_id = '';
    state.severity = '';
    state.view = 'unresolved';
    state.per_page = '15';
    visit();
}

function clearFilter(key: 'farm_id' | 'severity'): void {
    state[key] = '';
    visit();
}
</script>

<template>
    <Head title="Tiket Insiden" />
    <div class="space-y-[18px]" :aria-busy="loading">
        <header>
            <h1 class="admin-page-title">Tiket Insiden</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Pantau laporan insiden kebun, beri arahan penanganan, dan pantau
                tindak lanjut hingga selesai.
            </p>
        </header>

        <section
            class="grid gap-[18px] sm:grid-cols-2 xl:grid-cols-4"
            aria-label="Ringkasan tiket insiden"
        >
            <SummaryStatCard
                label="Perlu Penanganan"
                :value="summary.open"
                :icon="ShieldAlert"
            />
            <SummaryStatCard
                label="Sedang Ditangani"
                :value="summary.in_progress"
                :icon="Clock"
            />
            <SummaryStatCard
                label="Darurat Aktif"
                :value="summary.emergency_open"
                :icon="Siren"
            />
            <SummaryStatCard
                label="Selesai"
                :value="summary.resolved"
                :icon="CheckCircle2"
            />
        </section>

        <section class="admin-panel overflow-hidden p-0!">
            <div class="user-directory-inset">
                <div class="user-directory-tabs" role="tablist">
                    <button
                        v-for="[key, label] in incidentViewOptions"
                        :key="key"
                        type="button"
                        role="tab"
                        :aria-selected="state.view === key"
                        class="user-directory-tab"
                        :class="{
                            'user-directory-tab-active': state.view === key,
                        }"
                        @click="selectView(key)"
                    >
                        <span>{{ label }}</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs tabular-nums"
                            :class="
                                state.view === key
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            {{ viewCount(key) }}
                        </span>
                    </button>
                </div>
            </div>

            <div class="user-directory-inset">
                <div class="user-directory-toolbar">
                    <form
                        class="relative min-w-0 flex-1"
                        @submit.prevent="visit"
                    >
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <input
                            v-model="state.search"
                            type="search"
                            placeholder="Cari kategori, deskripsi, kebun, atau pelapor..."
                            aria-label="Cari tiket insiden"
                            class="admin-field pl-10!"
                        />
                    </form>

                    <div class="flex shrink-0 items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10"
                            :disabled="loading"
                            @click="visit"
                        >
                            <LoaderCircle
                                v-if="loading"
                                class="size-4 animate-spin"
                            />
                            <Search v-else class="size-4" />
                            <span class="hidden sm:inline">Cari</span>
                        </Button>

                        <PopoverRoot v-model:open="popoverOpen">
                            <PopoverTrigger as-child>
                                <Button
                                    :variant="
                                        activeFilterCount
                                            ? 'secondary'
                                            : 'outline'
                                    "
                                    type="button"
                                    class="h-10 px-2.5! sm:px-4!"
                                    :disabled="loading"
                                >
                                    <SlidersHorizontal aria-hidden="true" />
                                    Filter
                                    <span
                                        v-if="activeFilterCount"
                                        class="rounded-full bg-accent px-1.5 text-xs text-accent-foreground"
                                        >{{ activeFilterCount }}</span
                                    >
                                </Button>
                            </PopoverTrigger>
                            <PopoverPortal>
                                <PopoverContent
                                    side="bottom"
                                    align="end"
                                    :side-offset="10"
                                    :collision-padding="16"
                                    class="admin-dashboard-theme z-50 flex w-[340px] max-w-[calc(100vw-32px)] flex-col overflow-hidden rounded-xl border border-border bg-card text-foreground shadow-lg outline-none"
                                >
                                    <header
                                        class="flex items-center justify-between gap-3 border-b border-border px-5 py-4"
                                    >
                                        <h2
                                            class="flex items-center gap-2 text-base font-semibold"
                                        >
                                            <SlidersHorizontal
                                                class="size-4 text-primary"
                                            />
                                            Filter tiket insiden
                                        </h2>
                                        <PopoverClose as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label="Tutup filter"
                                            >
                                                <X />
                                            </Button>
                                        </PopoverClose>
                                    </header>
                                    <form
                                        class="space-y-4 p-5"
                                        @submit.prevent="visit"
                                    >
                                        <FarmField
                                            id="incident-filter-farm"
                                            label="Kebun"
                                            v-slot="field"
                                        >
                                            <AppSelect
                                                :id="field.id"
                                                v-model="state.farm_id"
                                                aria-label="Kebun"
                                            >
                                                <AppSelectOption value=""
                                                    >Semua
                                                    kebun</AppSelectOption
                                                >
                                                <AppSelectOption
                                                    v-for="farm in farms"
                                                    :key="farm.id"
                                                    :value="farm.id"
                                                >
                                                    {{ farm.name }}
                                                </AppSelectOption>
                                            </AppSelect>
                                        </FarmField>

                                        <FarmField
                                            id="incident-filter-severity"
                                            label="Tingkat kedaruratan"
                                            v-slot="field"
                                        >
                                            <AppSelect
                                                :id="field.id"
                                                v-model="state.severity"
                                                aria-label="Tingkat kedaruratan"
                                            >
                                                <AppSelectOption value=""
                                                    >Semua
                                                    tingkat</AppSelectOption
                                                >
                                                <AppSelectOption
                                                    v-for="option in severities"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </AppSelectOption>
                                            </AppSelect>
                                        </FarmField>

                                        <div
                                            class="flex items-center justify-between gap-2 pt-2"
                                        >
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                @click="resetFilters"
                                            >
                                                Reset
                                            </Button>
                                            <Button type="submit"
                                                >Terapkan</Button
                                            >
                                        </div>
                                    </form>
                                </PopoverContent>
                            </PopoverPortal>
                        </PopoverRoot>
                    </div>
                </div>
            </div>

            <div
                v-if="hasAnyFilter"
                class="user-directory-inset mb-4 flex flex-wrap items-center gap-2"
            >
                <span
                    v-if="farmName"
                    class="inline-flex items-center gap-1.5 rounded-full bg-muted px-3 py-1 text-xs font-medium"
                >
                    Kebun: {{ farmName }}
                    <button
                        type="button"
                        aria-label="Hapus filter kebun"
                        class="text-muted-foreground hover:text-foreground"
                        @click="clearFilter('farm_id')"
                    >
                        <X class="size-3.5" />
                    </button>
                </span>
                <span
                    v-if="severityName"
                    class="inline-flex items-center gap-1.5 rounded-full bg-muted px-3 py-1 text-xs font-medium"
                >
                    Kedaruratan: {{ severityName }}
                    <button
                        type="button"
                        aria-label="Hapus filter kedaruratan"
                        class="text-muted-foreground hover:text-foreground"
                        @click="clearFilter('severity')"
                    >
                        <X class="size-3.5" />
                    </button>
                </span>
                <button
                    type="button"
                    class="text-xs font-semibold text-primary underline"
                    @click="resetFilters"
                >
                    Reset filter
                </button>
            </div>

            <div
                v-if="!tickets.data.length"
                class="user-directory-inset py-12 text-center"
            >
                <ShieldAlert
                    class="mx-auto size-10 text-muted-foreground"
                    aria-hidden="true"
                />
                <h2 class="mt-3 text-base font-semibold">
                    Tidak ada tiket insiden
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Belum ada tiket yang cocok dengan filter saat ini.
                </p>
            </div>

            <template v-else>
                <div
                    class="user-directory-inset hidden overflow-x-auto md:block"
                >
                    <table
                        class="user-directory-table w-full min-w-[820px] text-left text-sm"
                    >
                        <thead>
                            <tr>
                                <th>Insiden</th>
                                <th>Lokasi</th>
                                <th>Kedaruratan</th>
                                <th>Status</th>
                                <th>Pelapor</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ticket in tickets.data" :key="ticket.id">
                                <td class="max-w-[280px]">
                                    <Link
                                        :href="`/admin/incidents/${ticket.id}`"
                                        class="block font-semibold text-foreground hover:text-primary"
                                    >
                                        {{
                                            ticket.incident_category ||
                                            'Insiden lahan'
                                        }}
                                    </Link>
                                    <p
                                        v-if="ticket.description"
                                        class="mt-0.5 line-clamp-2 text-xs text-muted-foreground"
                                    >
                                        {{ ticket.description }}
                                    </p>
                                </td>
                                <td>
                                    <p class="font-medium text-foreground">
                                        {{ ticket.farm_name ?? '-' }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            [ticket.block_name, ticket.subblok]
                                                .filter(Boolean)
                                                .join(' · ') || 'Area kebun'
                                        }}
                                    </p>
                                </td>
                                <td>
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="
                                            incidentSeverityClasses[
                                                ticket.severity
                                            ]
                                        "
                                    >
                                        {{ ticket.severity_label }}
                                    </span>
                                </td>
                                <td>
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="
                                            incidentStatusClasses[ticket.status]
                                        "
                                    >
                                        {{ ticket.status_label }}
                                    </span>
                                    <p
                                        v-if="ticket.resolved_activity_name"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Tindak lanjut:
                                        {{ ticket.resolved_activity_name }}
                                    </p>
                                </td>
                                <td>
                                    <p class="font-medium text-foreground">
                                        {{ ticket.reporter_name ?? '-' }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            formatIncidentDate(
                                                ticket.work_date ||
                                                    ticket.reported_at,
                                            )
                                        }}
                                    </p>
                                </td>
                                <td class="text-right">
                                    <Link
                                        :href="`/admin/incidents/${ticket.id}`"
                                        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-primary hover:bg-primary/10"
                                    >
                                        Detail
                                        <ArrowUpRight class="size-3.5" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="user-directory-inset space-y-3 pb-4 md:hidden">
                    <Link
                        v-for="ticket in tickets.data"
                        :key="ticket.id"
                        :href="`/admin/incidents/${ticket.id}`"
                        class="block rounded-xl border border-border bg-card p-4 transition-colors hover:border-primary/40"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="
                                    incidentSeverityClasses[ticket.severity]
                                "
                            >
                                {{ ticket.severity_label }}
                            </span>
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="incidentStatusClasses[ticket.status]"
                            >
                                {{ ticket.status_label }}
                            </span>
                        </div>
                        <p class="mt-2 font-semibold text-foreground">
                            {{ ticket.incident_category || 'Insiden lahan' }}
                        </p>
                        <p
                            v-if="ticket.description"
                            class="mt-1 line-clamp-2 text-xs text-muted-foreground"
                        >
                            {{ ticket.description }}
                        </p>
                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ ticket.location_label }} ·
                            {{
                                formatIncidentDate(
                                    ticket.work_date || ticket.reported_at,
                                )
                            }}
                        </p>
                    </Link>
                </div>

                <footer class="user-directory-footer">
                    <div
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <span
                            >Menampilkan {{ tickets.from ?? 0 }}–{{
                                tickets.to ?? 0
                            }}
                            dari {{ tickets.total }} tiket</span
                        >
                        <AppSelect
                            v-model="state.per_page"
                            aria-label="Baris per halaman"
                            class="w-20"
                            @change="visit"
                        >
                            <AppSelectOption value="15">15</AppSelectOption>
                            <AppSelectOption value="30">30</AppSelectOption>
                            <AppSelectOption value="50">50</AppSelectOption>
                        </AppSelect>
                    </div>
                    <InertiaPagination
                        :paginator="tickets"
                        label="Navigasi halaman tiket insiden"
                    />
                </footer>
            </template>
        </section>
    </div>
</template>
