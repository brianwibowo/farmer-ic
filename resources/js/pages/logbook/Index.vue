<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowUpRight, BookOpen, LoaderCircle, Search, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import LogbookActivityFiltersPopover from '@/components/logbook/LogbookActivityFiltersPopover.vue';
import LogbookPagination from '@/components/logbook/LogbookPagination.vue';
import LogbookStats from '@/components/logbook/LogbookStats.vue';
import { Button } from '@/components/ui/button';
import {
    logbookDate,
    type LogbookActivityRow,
    type LogbookIndexFilterOptions,
    type LogbookIndexFilters,
    type LogbookPage,
    type LogbookSummary,
} from '@/types/logbook';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    activities: LogbookPage<LogbookActivityRow>;
    filterOptions: LogbookIndexFilterOptions;
    summary: LogbookSummary;
    filters: LogbookIndexFilters;
    team: { id: number; name: string };
}>();

const showFilters = ref(false);

const filters = useForm({
    search: props.filters.search ?? '',
    farm_id: props.filters.farm_id ?? '',
    activity_type: props.filters.activity_type ?? '',
    source: (props.filters.source ?? '') as '' | 'sop' | 'mandiri',
    performed_by: props.filters.performed_by ?? '',
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
    per_page: Number(props.filters.per_page ?? 15),
});

watch(
    () => props.filters,
    (nextFilters) => {
        filters.search = nextFilters.search ?? '';
        filters.farm_id = nextFilters.farm_id ?? '';
        filters.activity_type = nextFilters.activity_type ?? '';
        filters.source = (nextFilters.source ?? '') as '' | 'sop' | 'mandiri';
        filters.performed_by = nextFilters.performed_by ?? '';
        filters.from_date = nextFilters.from_date ?? '';
        filters.to_date = nextFilters.to_date ?? '';
        filters.per_page = Number(nextFilters.per_page ?? 15);
    },
);

const popoverFilterCount = computed(() => {
    let count = 0;
    if (props.filters.farm_id) count++;
    if (props.filters.activity_type) count++;
    if (props.filters.source) count++;
    if (props.filters.performed_by) count++;
    if (props.filters.from_date) count++;
    if (props.filters.to_date) count++;
    return count;
});

type FilterChipKey =
    | 'search'
    | 'farm_id'
    | 'activity_type'
    | 'source'
    | 'performed_by'
    | 'from_date'
    | 'to_date';

const appliedFilters = computed(() => {
    const chips: { key: FilterChipKey; label: string }[] = [];
    const opts = props.filterOptions;

    if (props.filters.search) {
        chips.push({
            key: 'search',
            label: `Pencarian: ${props.filters.search}`,
        });
    }
    if (props.filters.farm_id) {
        const name =
            opts.farms.find((farm) => farm.id === props.filters.farm_id)
                ?.name ?? 'Kebun tidak tersedia';
        chips.push({ key: 'farm_id', label: `Kebun: ${name}` });
    }
    if (props.filters.activity_type) {
        const name =
            opts.activity_types.find(
                (type) => type.code === props.filters.activity_type,
            )?.name ?? props.filters.activity_type;
        chips.push({ key: 'activity_type', label: `Jenis: ${name}` });
    }
    if (props.filters.source) {
        chips.push({
            key: 'source',
            label: `Sumber: ${props.filters.source === 'sop' ? 'Tugas SOP' : 'Mandiri'}`,
        });
    }
    if (props.filters.performed_by) {
        const name =
            opts.performers.find(
                (person) => person.id === props.filters.performed_by,
            )?.name ?? 'Pelaksana tidak tersedia';
        chips.push({ key: 'performed_by', label: `Pelaksana: ${name}` });
    }
    if (props.filters.from_date) {
        chips.push({
            key: 'from_date',
            label: `Dari: ${logbookDate(props.filters.from_date)}`,
        });
    }
    if (props.filters.to_date) {
        chips.push({
            key: 'to_date',
            label: `Sampai: ${logbookDate(props.filters.to_date)}`,
        });
    }

    return chips;
});

const hasActiveFilters = computed(() => appliedFilters.value.length > 0);

function apply(): void {
    if (
        filters.from_date &&
        filters.to_date &&
        filters.to_date < filters.from_date
    ) {
        filters.setError(
            'to_date',
            'Tanggal akhir tidak boleh sebelum tanggal awal.',
        );
        showFilters.value = true;
        return;
    }

    filters.clearErrors();
    filters
        .transform((data) => {
            const query: Record<string, string | number> = {};
            if (data.search.trim()) query.search = data.search.trim();
            if (data.farm_id) query.farm_id = data.farm_id;
            if (data.activity_type) query.activity_type = data.activity_type;
            if (data.source) query.source = data.source;
            if (data.performed_by) query.performed_by = data.performed_by;
            if (data.from_date) query.from_date = data.from_date;
            if (data.to_date) query.to_date = data.to_date;
            if (Number(data.per_page) !== 15) query.per_page = data.per_page;
            return query;
        })
        .get('/admin/logbook', {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: () => {
                showFilters.value = false;
            },
            onError: (errors) => {
                if (
                    errors.farm_id ||
                    errors.activity_type ||
                    errors.source ||
                    errors.performed_by ||
                    errors.from_date ||
                    errors.to_date
                ) {
                    showFilters.value = true;
                }
            },
        });
}

function clearFilter(key: FilterChipKey): void {
    filters[key] = '';
    apply();
}

function resetAll(): void {
    filters.search = '';
    filters.farm_id = '';
    filters.activity_type = '';
    filters.source = '';
    filters.performed_by = '';
    filters.from_date = '';
    filters.to_date = '';
    filters.per_page = 15;
    filters.clearErrors();
    apply();
}

function activityLocation(row: LogbookActivityRow): string {
    return [row.block_name, row.subblok ? `Subblok ${row.subblok}` : null]
        .filter(Boolean)
        .join(' · ');
}

function activitySubtitle(row: LogbookActivityRow): string {
    const parts: string[] = [];
    if (row.notes) {
        parts.push(row.notes);
    }
    if (row.photos_count > 0) {
        parts.push(`${row.photos_count} foto`);
    }
    return parts.join(' · ');
}
</script>

<template>
    <Head title="Logbook" />
    <div class="space-y-[18px]" :aria-busy="filters.processing">
        <LogbookStats :summary="summary" directory />

        <section
            class="admin-card overflow-hidden rounded-xl bg-card"
            aria-labelledby="logbook-activities-title"
        >
            <div class="border-b border-border px-4 py-4 md:px-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <h2
                            id="logbook-activities-title"
                            class="admin-section-title"
                        >
                            Semua aktivitas
                        </h2>
                        <span
                            class="rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground"
                        >
                            {{ activities.total }}
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Tanggal mengikuti waktu kerja WIB
                    </p>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <form
                        class="flex min-w-0 flex-1 basis-64 items-center gap-2"
                        @submit.prevent="apply"
                    >
                        <div class="min-w-0 grow">
                            <label
                                for="logbook-activity-search"
                                class="sr-only"
                            >
                                Cari aktivitas
                            </label>
                            <div class="relative">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <input
                                    id="logbook-activity-search"
                                    v-model="filters.search"
                                    type="search"
                                    class="admin-field pl-10!"
                                    placeholder="Cari aktivitas, kebun, blok, subblok, atau pelaksana…"
                                    maxlength="150"
                                    :disabled="filters.processing"
                                    :aria-invalid="!!filters.errors.search"
                                    :aria-describedby="
                                        filters.errors.search
                                            ? 'logbook-search-error'
                                            : undefined
                                    "
                                />
                            </div>
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            class="size-10 shrink-0 pointer-coarse:min-h-11 pointer-coarse:min-w-11"
                            aria-label="Cari aktivitas"
                            title="Cari aktivitas"
                            :disabled="filters.processing"
                        >
                            <LoaderCircle
                                v-if="filters.processing"
                                class="animate-spin motion-reduce:animate-none"
                            />
                            <Search v-else class="size-4" />
                        </Button>
                    </form>

                    <div class="flex shrink-0 items-center gap-2">
                        <LogbookActivityFiltersPopover
                            v-model:open="showFilters"
                            v-model:farm-id="filters.farm_id"
                            v-model:activity-type="filters.activity_type"
                            v-model:source="filters.source"
                            v-model:performed-by="filters.performed_by"
                            v-model:from-date="filters.from_date"
                            v-model:to-date="filters.to_date"
                            :options="filterOptions"
                            :processing="filters.processing"
                            :count="popoverFilterCount"
                            :errors="filters.errors"
                            @apply="apply"
                            @reset="resetAll"
                        />
                    </div>
                </div>

                <div
                    v-if="hasActiveFilters"
                    class="mt-3 flex flex-wrap items-center gap-2"
                    aria-label="Filter yang diterapkan"
                >
                    <button
                        v-for="item in appliedFilters"
                        :key="item.key"
                        type="button"
                        class="inline-flex max-w-full items-center gap-2 rounded-md bg-accent/60 px-2.5 py-1.5 text-xs text-accent-foreground hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring"
                        :disabled="filters.processing"
                        :aria-label="'Hapus filter ' + item.label"
                        @click="clearFilter(item.key)"
                    >
                        <span class="break-words">{{ item.label }}</span>
                        <X class="size-3.5 shrink-0" />
                    </button>
                    <Button
                        variant="ghost"
                        size="sm"
                        :disabled="filters.processing"
                        @click="resetAll"
                    >
                        Reset semua
                    </Button>
                </div>

                <p
                    v-if="filters.errors.search"
                    id="logbook-search-error"
                    role="alert"
                    class="mt-3 text-sm text-error"
                >
                    {{ filters.errors.search }}
                </p>
            </div>

            <div
                v-if="filters.processing"
                class="space-y-3 p-4 md:p-5"
                role="status"
                aria-label="Memuat logbook"
            >
                <div
                    v-for="row in 4"
                    :key="row"
                    class="h-16 animate-pulse rounded-xl bg-muted motion-reduce:animate-none"
                />
                <p class="text-sm text-muted-foreground">Memuat logbook…</p>
            </div>

            <div
                v-else-if="!activities.data.length"
                class="px-5 py-16 text-center"
            >
                <div
                    class="mx-auto flex size-14 items-center justify-center rounded-full bg-accent text-primary"
                >
                    <BookOpen class="size-6" />
                </div>
                <h3 class="mt-4 text-base font-semibold">
                    {{
                        hasActiveFilters
                            ? 'Aktivitas tidak ditemukan'
                            : 'Belum ada aktivitas tercatat'
                    }}
                </h3>
                <p
                    class="mx-auto mt-2 max-w-sm text-sm leading-6 text-muted-foreground"
                >
                    {{
                        hasActiveFilters
                            ? 'Coba kata kunci atau kombinasi filter yang berbeda.'
                            : 'Aktivitas lapangan yang dicatat oleh petugas akan muncul di sini.'
                    }}
                </p>
                <Button
                    v-if="hasActiveFilters"
                    class="mt-5 rounded-xl"
                    variant="outline"
                    @click="resetAll"
                >
                    Reset filter
                </Button>
            </div>

            <template v-else>
                <div
                    class="hidden overflow-x-auto md:block"
                    role="region"
                    aria-label="Tabel aktivitas logbook"
                    tabindex="0"
                >
                    <table class="w-full table-fixed text-left text-sm">
                        <thead
                            class="bg-muted/50 text-xs text-muted-foreground"
                        >
                            <tr>
                                <th
                                    scope="col"
                                    class="w-[14%] px-4 py-3 font-semibold"
                                >
                                    Tanggal
                                </th>
                                <th
                                    scope="col"
                                    class="w-[24%] px-4 py-3 font-semibold"
                                >
                                    Lokasi
                                </th>
                                <th
                                    scope="col"
                                    class="w-[24%] px-4 py-3 font-semibold"
                                >
                                    Aktivitas
                                </th>
                                <th
                                    scope="col"
                                    class="w-[16%] px-4 py-3 font-semibold"
                                >
                                    Pelaksana
                                </th>
                                <th
                                    scope="col"
                                    class="w-[12%] px-4 py-3 font-semibold"
                                >
                                    Sumber
                                </th>
                                <th
                                    scope="col"
                                    class="w-[10%] px-4 py-3 text-right font-semibold"
                                >
                                    Detail
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="row in activities.data"
                                :key="row.id"
                                class="align-top hover:bg-muted/40"
                            >
                                <td class="px-4 py-4">
                                    <p class="font-medium whitespace-nowrap">
                                        {{ logbookDate(row.work_date) }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-medium">
                                        {{ row.farm_name ?? '-' }}
                                    </p>
                                    <p
                                        v-if="row.block_name"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ row.block_name }}
                                    </p>
                                    <p
                                        v-if="row.subblok"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Subblok {{ row.subblok }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <Link
                                        :href="`/admin/logbook/aktivitas/${row.id}`"
                                        class="font-medium hover:text-primary focus-visible:outline-2 focus-visible:outline-ring"
                                    >
                                        {{ row.name }}
                                    </Link>
                                    <p
                                        v-if="activitySubtitle(row)"
                                        class="mt-1 line-clamp-2 text-xs text-muted-foreground"
                                    >
                                        {{ activitySubtitle(row) }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-medium">
                                        {{
                                            row.performer_name ??
                                            'Petugas tidak tersedia'
                                        }}
                                    </p>
                                    <p
                                        v-if="row.worker_count !== null"
                                        class="mt-1 text-xs text-muted-foreground tabular-nums"
                                    >
                                        {{ row.worker_count }} pekerja
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap gap-1.5">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap"
                                            :class="
                                                row.source === 'sop'
                                                    ? 'bg-accent text-accent-foreground'
                                                    : 'bg-muted text-muted-foreground'
                                            "
                                        >
                                            {{
                                                row.source === 'sop'
                                                    ? 'Tugas SOP'
                                                    : 'Mandiri'
                                            }}
                                        </span>
                                        <span
                                            v-if="row.code === 'incident'"
                                            class="inline-flex rounded-full bg-warning/15 px-2.5 py-0.5 text-xs font-medium whitespace-nowrap text-warning"
                                        >
                                            Insiden
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <Link
                                        :href="`/admin/logbook/aktivitas/${row.id}`"
                                        class="inline-flex min-h-9 items-center gap-1 rounded-xl px-2 text-xs font-medium text-primary hover:bg-primary/10 focus-visible:outline-2 focus-visible:outline-ring"
                                        :aria-label="`Lihat detail ${row.name}`"
                                    >
                                        Detail
                                        <ArrowUpRight class="size-3.5" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-border md:hidden">
                    <article
                        v-for="row in activities.data"
                        :key="row.id"
                        class="space-y-3 p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <Link
                                    :href="`/admin/logbook/aktivitas/${row.id}`"
                                    class="font-semibold hover:text-primary"
                                >
                                    {{ row.name }}
                                </Link>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ row.farm_name ?? '-' }}
                                    <template v-if="activityLocation(row)">
                                        · {{ activityLocation(row) }}</template
                                    >
                                </p>
                            </div>
                            <span
                                class="shrink-0 text-xs font-medium text-muted-foreground"
                            >
                                {{ logbookDate(row.work_date) }}
                            </span>
                        </div>
                        <p
                            v-if="activitySubtitle(row)"
                            class="line-clamp-2 text-xs text-muted-foreground"
                        >
                            {{ activitySubtitle(row) }}
                        </p>
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 pt-1"
                        >
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span
                                    class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="
                                        row.source === 'sop'
                                            ? 'bg-accent text-accent-foreground'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    {{
                                        row.source === 'sop'
                                            ? 'Tugas SOP'
                                            : 'Mandiri'
                                    }}
                                </span>
                                <span
                                    v-if="row.code === 'incident'"
                                    class="rounded-full bg-warning/15 px-2.5 py-0.5 text-xs font-medium text-warning"
                                >
                                    Insiden
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        row.performer_name ??
                                        'Petugas tidak tersedia'
                                    }}
                                </span>
                            </div>
                            <Link
                                :href="`/admin/logbook/aktivitas/${row.id}`"
                                class="inline-flex min-h-9 items-center gap-1 rounded-xl px-2 text-xs font-medium text-primary hover:bg-primary/10"
                            >
                                Detail
                                <ArrowUpRight class="size-3.5" />
                            </Link>
                        </div>
                    </article>
                </div>
            </template>

            <LogbookPagination
                v-model:size="filters.per_page"
                :page="activities"
                label="aktivitas"
                :loading="filters.processing"
                @change="apply"
            />
        </section>
    </div>
</template>
