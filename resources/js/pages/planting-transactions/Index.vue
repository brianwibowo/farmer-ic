<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ChevronDown,
    LoaderCircle,
    Search,
    Sprout,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import LogbookPagination from '@/components/logbook/LogbookPagination.vue';
import PlantingReportList from '@/components/planting/PlantingReportList.vue';
import TransactionFiltersPopover from '@/components/transactions/TransactionFiltersPopover.vue';
import TransactionSummaryCards from '@/components/transactions/TransactionSummaryCards.vue';
import { Button } from '@/components/ui/button';
import { useTransactionFilters } from '@/composables/useTransactionFilters';
import {
    type PlantingTransactionBlockOption,
    type PlantingTransactionFarmOption,
    type PlantingTransactionFilters,
    type PlantingTransactionPage,
    type PlantingTransactionRow,
    type PlantingTransactionSummary,
    type PlantingTransactionVarietyOption,
} from '@/types/planting-transactions';

const props = defineProps<{
    transactions: PlantingTransactionPage;
    summary: PlantingTransactionSummary;
    filters: PlantingTransactionFilters;
    farms: PlantingTransactionFarmOption[];
    blocks: PlantingTransactionBlockOption[];
    varieties: PlantingTransactionVarietyOption[];
}>();

const {
    filters,
    showFilters,
    appliedFilters,
    filterCount,
    hasFilters,
    apply,
    clearFilter,
    reset,
} = useTransactionFilters({
    url: '/admin/transaksi-penanaman',
    initialFilters: () => props.filters,
    farms: () => props.farms,
    blocks: () => props.blocks,
    varieties: () => props.varieties,
});

const monthLabels = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
];

function formatDecimal(value: string | null, digits = 2): string {
    if (value === null || value === '') {
        return '0';
    }
    const [whole, fraction] = Number(value).toFixed(digits).split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return fraction ? `${grouped},${fraction}` : grouped;
}

function formatDate(value: string): string {
    const [year, month, day] = value.split('-').map(Number);
    return `${String(day).padStart(2, '0')} ${monthLabels[month - 1] ?? ''} ${year}`;
}

const expandedKeys = ref<string[]>([]);

function isExpanded(key: string): boolean {
    return expandedKeys.value.includes(key);
}

function toggleExpanded(key: string): void {
    expandedKeys.value = isExpanded(key)
        ? expandedKeys.value.filter((item) => item !== key)
        : [...expandedKeys.value, key];
}

function activityDateRange(row: PlantingTransactionRow): string {
    if (!row.first_activity_date) {
        return '-';
    }
    if (
        !row.last_activity_date ||
        row.first_activity_date === row.last_activity_date
    ) {
        return formatDate(row.first_activity_date);
    }

    return `${formatDate(row.first_activity_date)} – ${formatDate(row.last_activity_date)}`;
}

function rowSubblock(row: PlantingTransactionRow): string {
    if (!row.cycle) {
        return 'Subblok belum terhubung';
    }
    if (row.cycle.subblock_label) {
        return `Subblok ${row.cycle.subblock_label}`;
    }
    if (row.cycle.batch_number !== null) {
        return `Batch ${row.cycle.batch_number}`;
    }

    return 'Subblok';
}
</script>

<template>
    <Head title="Transaksi Penanaman" />
    <div class="space-y-[18px]" :aria-busy="filters.processing">
        <TransactionSummaryCards
            mode="planting"
            :subblocks="summary.subblocks"
            :reports="summary.reports"
            :area-ha="summary.area_ha"
            :primary-metric-value="summary.seed_kg"
            :row-areas="transactions.data.map((row) => Number(row.area_ha) || 0)"
        />

        <section class="admin-card overflow-hidden rounded-xl bg-card">
            <div class="border-b border-border px-4 py-4 md:px-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <h2 class="admin-section-title">Daftar subblok</h2>
                        <span
                            class="rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground"
                        >
                            {{ transactions.total }}
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
                                for="planting-transaction-search"
                                class="sr-only"
                                >Cari transaksi penanaman</label
                            >
                            <div class="relative">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <input
                                    id="planting-transaction-search"
                                    v-model="filters.search"
                                    class="admin-field pl-10!"
                                    type="search"
                                    maxlength="100"
                                    placeholder="Kebun, blok, varietas, benih, atau petugas…"
                                    :disabled="filters.processing"
                                    :aria-invalid="!!filters.errors.search"
                                    :aria-describedby="
                                        filters.errors.search
                                            ? 'planting-search-error'
                                            : undefined
                                    "
                                />
                            </div>
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            class="size-10 shrink-0 pointer-coarse:min-h-11 pointer-coarse:min-w-11"
                            aria-label="Cari transaksi penanaman"
                            title="Cari transaksi penanaman"
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
                        <TransactionFiltersPopover
                            id-prefix="planting"
                            v-model:open="showFilters"
                            v-model:farm-id="filters.farm_id"
                            v-model:block-id="filters.block_id"
                            v-model:variety-id="filters.variety_id"
                            v-model:from-date="filters.from_date"
                            v-model:to-date="filters.to_date"
                            :farms="farms"
                            :blocks="blocks"
                            :varieties="varieties"
                            :processing="filters.processing"
                            :count="filterCount"
                            :errors="filters.errors"
                            @apply="apply"
                            @reset="reset"
                        />
                    </div>
                </div>

                <div
                    v-if="appliedFilters.length"
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
                        @click="reset"
                        >Reset semua</Button
                    >
                </div>

                <p
                    v-if="Object.keys(filters.errors).length"
                    id="planting-search-error"
                    role="alert"
                    class="mt-3 text-sm text-error"
                >
                    {{ Object.values(filters.errors)[0] }}
                </p>
            </div>

            <div
                v-if="filters.processing"
                class="space-y-4 p-6"
                role="status"
                aria-label="Memuat transaksi penanaman"
            >
                <div
                    v-for="row in 3"
                    :key="row"
                    class="h-12 animate-pulse rounded-xl bg-muted"
                />
                <p class="text-sm text-muted-foreground">
                    Memuat transaksi penanaman.
                </p>
            </div>

            <div
                v-else-if="!transactions.data.length"
                class="px-5 py-16 text-center"
            >
                <div
                    class="mx-auto flex size-14 items-center justify-center rounded-xl bg-accent text-primary"
                >
                    <Sprout class="size-7" />
                </div>
                <h3 class="mt-4 font-semibold">
                    {{
                        hasFilters
                            ? 'Subblok tidak ditemukan'
                            : 'Belum ada subblok ditanam'
                    }}
                </h3>
                <p
                    class="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground"
                >
                    {{
                        hasFilters
                            ? 'Coba kata kunci, tanggal, atau filter lain.'
                            : 'Subblok akan muncul setelah Petugas mencatat realisasi penanaman.'
                    }}
                </p>
                <Button
                    v-if="hasFilters"
                    variant="outline"
                    class="mt-5 rounded-xl"
                    @click="reset"
                >
                    Reset filter
                </Button>
            </div>

            <template v-else>
                <div
                    class="hidden overflow-x-auto md:block"
                    role="region"
                    aria-label="Tabel subblok penanaman"
                    tabindex="0"
                >
                    <table class="w-full table-fixed text-left text-sm">
                        <thead
                            class="bg-muted/50 text-xs text-muted-foreground"
                        >
                            <tr>
                                <th
                                    scope="col"
                                    class="w-[20%] px-4 py-3 font-semibold"
                                >
                                    Tanam
                                </th>
                                <th
                                    scope="col"
                                    class="w-[24%] px-4 py-3 font-semibold"
                                >
                                    Lokasi
                                </th>
                                <th
                                    scope="col"
                                    class="w-[18%] px-4 py-3 font-semibold"
                                >
                                    Varietas
                                </th>
                                <th
                                    scope="col"
                                    class="w-[20%] px-4 py-3 font-semibold"
                                >
                                    Luas &amp; benih
                                </th>
                                <th
                                    scope="col"
                                    class="w-[18%] px-4 py-3 text-right font-semibold"
                                >
                                    Laporan
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <template
                                v-for="row in transactions.data"
                                :key="row.key"
                            >
                                <tr class="align-top hover:bg-muted/40">
                                    <td class="px-4 py-4">
                                        <p class="font-medium">
                                            {{ activityDateRange(row) }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="font-medium">
                                            {{ row.farm?.name ?? '-' }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ row.block?.code ?? '-' }} ·
                                            {{
                                                row.block?.name ??
                                                'Blok belum diisi'
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ rowSubblock(row) }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="font-medium">
                                            {{ row.variety?.name ?? '-' }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{
                                                row.commodity?.name ??
                                                'Komoditas belum diisi'
                                            }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="font-medium">
                                            {{ formatDecimal(row.area_ha) }} ha
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ formatDecimal(row.seed_kg) }} kg
                                        </p>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <button
                                            type="button"
                                            class="inline-flex min-h-9 items-center gap-1 rounded-xl px-2 text-xs font-medium text-primary hover:bg-primary/10 focus-visible:outline-2 focus-visible:outline-ring"
                                            :aria-expanded="isExpanded(row.key)"
                                            :aria-controls="`planting-reports-${row.key}`"
                                            @click="toggleExpanded(row.key)"
                                        >
                                            {{ row.report_count }} laporan
                                            <ChevronDown
                                                class="size-4 transition-transform motion-reduce:transition-none"
                                                :class="{
                                                    'rotate-180': isExpanded(
                                                        row.key,
                                                    ),
                                                }"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </td>
                                </tr>
                                <tr
                                    v-if="isExpanded(row.key)"
                                    :id="`planting-reports-${row.key}`"
                                    class="bg-muted/30"
                                >
                                    <td colspan="5" class="px-4 py-4">
                                        <div class="mb-3">
                                            <h3 class="text-sm font-semibold">
                                                Laporan penanaman
                                            </h3>
                                            <p
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                {{ rowSubblock(row) }}
                                            </p>
                                        </div>
                                        <PlantingReportList
                                            :reports="row.reports"
                                        />
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-border md:hidden">
                    <article
                        v-for="row in transactions.data"
                        :key="row.key"
                        class="p-4"
                    >
                        <div>
                            <p class="font-semibold">
                                {{ row.farm?.name ?? '-' }} ·
                                {{ row.block?.code ?? '-' }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ rowSubblock(row) }}
                            </p>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Varietas
                                </p>
                                <p class="font-medium">
                                    {{ row.variety?.name ?? '-' }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        row.commodity?.name ??
                                        'Komoditas belum diisi'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Tanam
                                </p>
                                <p class="font-medium">
                                    {{ activityDateRange(row) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Luas
                                </p>
                                <p class="font-medium">
                                    {{ formatDecimal(row.area_ha) }} ha
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Benih
                                </p>
                                <p class="font-medium">
                                    {{ formatDecimal(row.seed_kg) }} kg
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 flex justify-end">
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center gap-1 rounded-xl px-2 text-sm font-medium text-primary hover:bg-primary/10 focus-visible:outline-2 focus-visible:outline-ring"
                                :aria-expanded="isExpanded(row.key)"
                                :aria-controls="`planting-reports-mobile-${row.key}`"
                                @click="toggleExpanded(row.key)"
                            >
                                {{ row.report_count }} laporan
                                <ChevronDown
                                    class="size-4 transition-transform motion-reduce:transition-none"
                                    :class="{
                                        'rotate-180': isExpanded(row.key),
                                    }"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>
                        <div
                            v-if="isExpanded(row.key)"
                            :id="`planting-reports-mobile-${row.key}`"
                            class="mt-3"
                        >
                            <PlantingReportList :reports="row.reports" />
                        </div>
                    </article>
                </div>

                <LogbookPagination
                    v-model:size="filters.per_page"
                    :page="transactions"
                    :loading="filters.processing"
                    label="subblok"
                    @change="apply"
                />
            </template>
        </section>
    </div>
</template>
