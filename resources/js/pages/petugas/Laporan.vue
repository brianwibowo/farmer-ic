<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { ClipboardList, Search } from '@lucide/vue';
import { computed } from 'vue';
import NumberPagination from '@/components/NumberPagination.vue';
import ActivityAddButton from '@/components/petugas/ActivityAddButton.vue';
import PetugasListHeader from '@/components/petugas/PetugasListHeader.vue';
import ReportCard from '@/components/petugas/ReportCard.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import {
    usePetugasReportFilters,
    type PetugasReportFilters,
} from '@/composables/usePetugasReportFilters';
import type { PetugasActivityCategory } from '@/types/petugas-activity';
import type { PetugasReportSummary } from '@/types/petugas-report';

defineOptions({ inheritAttrs: false });
const props = defineProps<{
    farm: { id: string; name: string } | null;
    reports: {
        data: PetugasReportSummary[];
        total: number;
        from: number | null;
        to: number | null;
        current_page: number;
        last_page: number;
    } | null;
    stats: { sop: number; mandiri: number };
    filters: PetugasReportFilters;
    types: { code: string; name: string }[];
    categories: PetugasActivityCategory[];
}>();
const page = usePage();
const { filter, loading, error, applyNow, retry, requestPage } =
    usePetugasReportFilters(
        () => props.filters,
        () => !!props.farm,
    );
const emptyDescription = computed(() => {
    if (!props.farm) return 'Hubungi Admin untuk penugasan kebun.';
    return props.categories.length
        ? 'Coba ubah filter atau ketuk tombol + untuk mencatat aktivitas mandiri.'
        : 'Coba ubah filter untuk melihat laporan lain.';
});
const listUrl = computed(() =>
    /^\/petugas\/laporan(?:\?|$)/.test(page.url)
        ? page.url
        : '/petugas/laporan',
);
const reportGroups = computed(() => {
    const groups = new Map<string, PetugasReportSummary[]>();

    for (const report of props.reports?.data ?? []) {
        const reports = groups.get(report.date) ?? [];
        reports.push(report);
        groups.set(report.date, reports);
    }

    return Array.from(groups, ([date, reports]) => ({ date, reports })).sort(
        (a, b) => b.date.localeCompare(a.date),
    );
});
function detailHref(id: string): string {
    return `/petugas/laporan/${id}?${new URLSearchParams({ back: listUrl.value })}`;
}
</script>

<template>
    <Head title="Laporan" />
    <div
        class="min-w-0 space-y-[var(--admin-content-gap)] pb-28 text-foreground"
        :aria-busy="loading"
    >
        <PetugasListHeader
            title="Laporan"
            :show-incidents="!!farm"
            :incidents-back-href="listUrl"
            summary-label="Ringkasan laporan sesuai filter"
            :metrics="[
                {
                    label: 'Aktivitas',
                    value: loading || error ? '—' : (reports?.total ?? 0),
                },
                { label: 'SOP', value: loading || error ? '—' : stats.sop },
                {
                    label: 'Mandiri',
                    value: loading || error ? '—' : stats.mandiri,
                },
            ]"
        />

        <form v-if="farm" class="space-y-3" @submit.prevent="applyNow">
            <label class="relative block">
                <span class="sr-only">Cari laporan</span>
                <input
                    v-model="filter.q"
                    type="search"
                    maxlength="100"
                    placeholder="Cari aktivitas atau subblok"
                    class="petugas-field min-h-11 w-full rounded-xl border border-border px-3 pr-10 text-foreground"
                />
                <Search
                    class="pointer-events-none absolute top-1/2 right-3 size-5 -translate-y-1/2 text-primary"
                    aria-hidden="true"
                />
            </label>
            <div class="grid gap-2 min-[360px]:grid-cols-2">
                <label class="min-w-0 space-y-1 text-xs">
                    <span>Tanggal kerja</span>
                    <input
                        v-model="filter.date"
                        type="date"
                        class="petugas-field min-h-11 w-full min-w-0 rounded-xl border border-border px-2 text-foreground [color-scheme:light] dark:[color-scheme:dark]"
                    />
                </label>
                <div class="min-w-0 space-y-1 text-xs">
                    <label for="report-type">Kategori</label>
                    <AppSelect
                        id="report-type"
                        v-model="filter.type"
                        aria-label="Kategori laporan"
                        class="min-h-11"
                    >
                        <AppSelectOption value=""
                            >Semua kategori</AppSelectOption
                        >
                        <AppSelectOption
                            v-for="type in types"
                            :key="type.code"
                            :value="type.code"
                            >{{ type.name }}</AppSelectOption
                        >
                    </AppSelect>
                </div>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <div class="min-w-36 flex-1 space-y-1 text-xs">
                    <label for="report-source">Sumber laporan</label>
                    <AppSelect
                        id="report-source"
                        v-model="filter.source"
                        aria-label="Asal aktivitas"
                        class="min-h-11"
                    >
                        <AppSelectOption value="">Semua sumber</AppSelectOption>
                        <AppSelectOption value="sop">Dari SOP</AppSelectOption>
                        <AppSelectOption value="mandiri"
                            >Mandiri</AppSelectOption
                        >
                    </AppSelect>
                </div>
                <label class="flex min-h-11 items-center gap-2 text-xs">
                    <input
                        v-model="filter.mine"
                        type="checkbox"
                        class="size-4 accent-primary"
                    />Input saya
                </label>
            </div>
        </form>

        <p v-if="loading" role="status" class="text-sm text-muted-foreground">
            Memuat laporan…
        </p>
        <div
            v-else-if="error"
            role="alert"
            class="petugas-card space-y-2 p-4 text-sm text-error"
        >
            <p>{{ error }}</p>
            <button
                type="button"
                class="min-h-11 rounded-xl font-semibold underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                @click="retry"
            >
                Coba lagi
            </button>
        </div>
        <div
            v-else-if="!reports?.data.length"
            class="petugas-card petugas-empty bg-muted/40 px-5 py-10 text-center"
        >
            <ClipboardList
                class="mx-auto size-9 text-primary"
                aria-hidden="true"
            />
            <h2 class="mt-3 font-semibold">
                {{
                    farm
                        ? 'Belum ada laporan yang ditampilkan'
                        : 'Belum ada penugasan kebun'
                }}
            </h2>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ emptyDescription }}
            </p>
        </div>
        <div
            v-else-if="reportGroups.length"
            class="space-y-5"
            role="group"
            aria-label="Riwayat aktivitas"
        >
            <section
                v-for="group in reportGroups"
                :key="group.date"
                :aria-labelledby="`report-date-${group.date}`"
                class="space-y-2"
            >
                <h2
                    :id="`report-date-${group.date}`"
                    class="text-xs font-semibold text-muted-foreground"
                >
                    <time :datetime="group.date">{{ group.date }}</time>
                </h2>
                <ul class="space-y-3">
                    <li v-for="report in group.reports" :key="report.id">
                        <ReportCard
                            :report="report"
                            :href="detailHref(report.id)"
                        />
                    </li>
                </ul>
            </section>
        </div>
        <footer
            v-if="reports && !error"
            class="petugas-card flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-xs text-muted-foreground"
        >
            <span role="status" aria-live="polite" aria-atomic="true">
                <template v-if="loading">Memuat laporan…</template>
                <template v-else>
                    Menampilkan {{ reports.from ?? 0 }}–{{
                        reports.to ?? 0
                    }}
                    dari {{ reports.total }} aktivitas.
                </template>
            </span>
            <div
                class="ml-auto flex w-full flex-wrap items-center justify-between gap-3"
            >
                <label for="report-per-page" class="flex items-center gap-2">
                    Per halaman
                    <AppSelect
                        id="report-per-page"
                        v-model.number="filter.per_page"
                        class="admin-field w-auto!"
                        :disabled="loading"
                        aria-label="Jumlah aktivitas per halaman"
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
                    :current-page="reports.current_page"
                    :total-pages="reports.last_page"
                    :disabled="loading"
                    label="Pagination laporan"
                    @change="requestPage"
                />
            </div>
        </footer>
        <ActivityAddButton :categories="categories" :back-href="listUrl" />
    </div>
</template>
