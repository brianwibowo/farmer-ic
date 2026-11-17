<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowUpRight,
    BookOpen,
    LandPlot,
    MapPin,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import LogbookFiltersPopover from '@/components/logbook/LogbookFiltersPopover.vue';
import LogbookPagination from '@/components/logbook/LogbookPagination.vue';
import LogbookStats from '@/components/logbook/LogbookStats.vue';
import { Button } from '@/components/ui/button';
import {
    farmLocation,
    logbookDate,
    type LogbookFarm,
    type LogbookPage,
    type LogbookRow,
    type LogbookSummary,
} from '@/types/logbook';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    farm: LogbookFarm;
    logs: LogbookPage<LogbookRow>;
    summary: LogbookSummary;
    filters: {
        from_date?: string;
        to_date?: string;
        recorded_by?: string;
        per_page?: number;
    };
    recorders: { id: string; name: string }[];
}>();

const showFilters = ref(false);

const filters = useForm({
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
    recorded_by: props.filters.recorded_by ?? '',
    per_page: Number(props.filters.per_page ?? 15),
});

const appliedFilters = computed(() => {
    const chips: {
        key: 'from_date' | 'to_date' | 'recorded_by';
        label: string;
    }[] = [];

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

    if (props.filters.recorded_by) {
        const name =
            props.recorders.find(
                (person) => person.id === props.filters.recorded_by,
            )?.name ?? 'Pelapor tidak tersedia';
        chips.push({
            key: 'recorded_by',
            label: `Pelapor: ${name}`,
        });
    }

    return chips;
});

const hasFilters = computed(() => appliedFilters.value.length > 0);
const filterCount = computed(() => appliedFilters.value.length);

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
    filters.get('/admin/logbook/' + props.farm.id, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            showFilters.value = false;
        },
        onError: (errors) => {
            if (errors.from_date || errors.to_date || errors.recorded_by) {
                showFilters.value = true;
            }
        },
    });
}

function clearFilter(key: 'from_date' | 'to_date' | 'recorded_by'): void {
    filters[key] = '';
    apply();
}

function reset(): void {
    filters.from_date = '';
    filters.to_date = '';
    filters.recorded_by = '';
    filters.per_page = 15;
    filters.clearErrors();
    apply();
}
</script>

<template>
    <Head :title="'Logbook - ' + farm.name" />
    <div class="space-y-[18px]" :aria-busy="filters.processing">
        <Link
            href="/admin/logbook"
            class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary"
        >
            <ArrowLeft class="size-4" />Kembali ke logbook
        </Link>

        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-start gap-4">
                <div
                    class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-accent text-primary"
                >
                    <LandPlot class="size-7" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <h1
                        class="admin-page-title tracking-tight break-words text-primary"
                    >
                        {{ farm.name }}
                    </h1>
                    <p
                        class="mt-1.5 flex items-start gap-1.5 text-sm leading-6 text-muted-foreground"
                    >
                        <MapPin
                            class="mt-1 size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <span>{{ farmLocation(farm) }}</span>
                    </p>
                </div>
            </div>
        </header>

        <LogbookStats :summary="summary" />

        <section
            class="user-directory rounded-xl bg-card"
            aria-labelledby="logbook-history-title"
        >
            <header
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-4 sm:px-6"
            >
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h2
                            id="logbook-history-title"
                            class="admin-section-title"
                        >
                            Riwayat logbook
                        </h2>
                        <span
                            class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-semibold tabular-nums text-accent-foreground"
                        >
                            {{ logs.total }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Waktu pencatatan mengikuti waktu kerja WIB
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <LogbookFiltersPopover
                        v-model:open="showFilters"
                        v-model:from-date="filters.from_date"
                        v-model:to-date="filters.to_date"
                        v-model:recorded-by="filters.recorded_by"
                        :recorders="recorders"
                        :processing="filters.processing"
                        :count="filterCount"
                        :errors="filters.errors"
                        @apply="apply"
                        @reset="reset"
                    />
                </div>
            </header>

            <div
                v-if="hasFilters"
                class="user-directory-inset flex flex-wrap items-center gap-2 pt-4"
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
                    @click="reset"
                >
                    Reset semua
                </Button>
            </div>

            <p
                v-if="Object.keys(filters.errors).length"
                role="alert"
                class="user-directory-inset pt-3 text-sm text-error"
            >
                {{ Object.values(filters.errors)[0] }}
            </p>

            <div
                v-if="filters.processing"
                class="user-directory-inset space-y-3 py-5"
                role="status"
                aria-label="Memuat riwayat logbook"
            >
                <div
                    v-for="row in 3"
                    :key="row"
                    class="h-[72px] animate-pulse rounded-xl bg-muted motion-reduce:animate-none"
                />
                <p class="text-sm text-muted-foreground">
                    Memuat riwayat logbook…
                </p>
            </div>

            <div v-else-if="!logs.data.length" class="px-5 py-16 text-center">
                <div
                    class="mx-auto flex size-14 items-center justify-center rounded-full bg-accent text-primary"
                >
                    <BookOpen class="size-6" />
                </div>
                <h3 class="mt-4 text-base font-semibold">
                    {{
                        hasFilters
                            ? 'Logbook tidak ditemukan'
                            : 'Belum ada logbook'
                    }}
                </h3>
                <p
                    class="mx-auto mt-2 max-w-sm text-sm leading-6 text-muted-foreground"
                >
                    {{
                        hasFilters
                            ? 'Coba rentang tanggal atau pelapor yang berbeda.'
                            : 'Laporan harian yang tersimpan akan ditampilkan di sini.'
                    }}
                </p>
                <Button
                    v-if="hasFilters"
                    variant="outline"
                    class="mt-5"
                    @click="reset"
                >
                    Reset filter
                </Button>
            </div>

            <template v-else>
                <div
                    class="user-directory-inset hidden overflow-x-auto pt-3 md:block"
                    role="region"
                    aria-label="Tabel riwayat logbook"
                    tabindex="0"
                >
                    <table
                        class="user-directory-table w-full min-w-[760px] text-left text-sm"
                    >
                        <thead>
                            <tr>
                                <th scope="col">Tanggal kerja</th>
                                <th scope="col">Pelapor</th>
                                <th scope="col">Waktu kerja</th>
                                <th scope="col">Kegiatan</th>
                                <th scope="col">Kendala tercatat</th>
                                <th scope="col" class="text-right">
                                    <span class="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in logs.data" :key="log.id">
                                <td>
                                    <Link
                                        :href="
                                            '/admin/logbook/' +
                                            farm.id +
                                            '/' +
                                            log.id
                                        "
                                        class="font-semibold whitespace-nowrap hover:text-primary focus-visible:outline-2 focus-visible:outline-ring"
                                    >
                                        {{ logbookDate(log.work_date) }}
                                    </Link>
                                </td>
                                <td class="font-medium">
                                    {{
                                        log.recorder_name ??
                                        'Pelapor tidak tersedia'
                                    }}
                                </td>
                                <td
                                    class="text-xs whitespace-nowrap text-muted-foreground"
                                >
                                    {{ log.start_time }}–{{
                                        log.end_time ?? 'Belum diisi'
                                    }}
                                    WIB
                                </td>
                                <td class="font-semibold tabular-nums">
                                    {{ log.activities_count }}
                                </td>
                                <td>
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap"
                                        :class="
                                            log.obstacle_count
                                                ? 'bg-warning/15 text-warning'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        {{ log.obstacle_count }} kendala
                                    </span>
                                </td>
                                <td class="text-right">
                                    <Button
                                        as-child
                                        variant="ghost"
                                        size="icon"
                                        class="rounded-xl"
                                    >
                                        <Link
                                            :href="
                                                '/admin/logbook/' +
                                                farm.id +
                                                '/' +
                                                log.id
                                            "
                                            :aria-label="
                                                'Detail logbook ' +
                                                logbookDate(log.work_date)
                                            "
                                            ><ArrowUpRight
                                        /></Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <ul
                    class="user-directory-inset space-y-3 py-4 md:hidden"
                    aria-label="Riwayat logbook"
                >
                    <li
                        v-for="log in logs.data"
                        :key="log.id"
                        class="space-y-3 rounded-xl bg-background p-4"
                    >
                        <Link
                            :href="'/admin/logbook/' + farm.id + '/' + log.id"
                            class="flex items-center justify-between gap-2 font-semibold hover:text-primary"
                        >
                            <span>{{ logbookDate(log.work_date) }}</span>
                            <ArrowUpRight class="size-4 shrink-0 text-primary" />
                        </Link>
                        <p class="text-xs text-muted-foreground">
                            {{
                                log.recorder_name ?? 'Pelapor tidak tersedia'
                            }}
                            · {{ log.start_time }}–{{
                                log.end_time ?? 'Belum diisi'
                            }}
                            WIB
                        </p>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span
                                class="rounded-full bg-accent px-2.5 py-1 font-medium text-accent-foreground"
                            >
                                {{ log.activities_count }} kegiatan
                            </span>
                            <span
                                class="rounded-full px-2.5 py-1 font-semibold"
                                :class="
                                    log.obstacle_count
                                        ? 'bg-warning/15 text-warning'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{ log.obstacle_count }} kendala tercatat
                            </span>
                        </div>
                    </li>
                </ul>
            </template>

            <LogbookPagination
                v-model:size="filters.per_page"
                :page="logs"
                label="logbook"
                :loading="filters.processing"
                @change="apply"
            />
        </section>
    </div>
</template>
