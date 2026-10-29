<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronDown, FileText, Filter, Search, X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import FinanceNavigation from '@/components/farmer/FinanceNavigation.vue';
import RevenueChart from '@/components/farmer/RevenueChart.vue';
import { formatFinanceMoney as formatRupiah } from '@/lib/finance';
import type { RevenuePoint } from '@/data/farmerFinance';
import type {
    FinancePaginator,
    FinancePeriod,
    FinanceSummary,
} from '@/types/finance';

type FinanceTab = 'chart' | 'transactions';
type Transaction = {
    id: string;
    description: string;
    category: string;
    commodity: string | null;
    farm_id: string;
    farm_name: string | null;
    transaction_date: string;
    date: { day: string; month: string; year: string };
    amount: string;
    type: 'income' | 'expense';
};
type Filters = {
    year: number;
    period: FinancePeriod;
    farm_id?: string | null;
    commodity?: string | null;
    transaction_type?: 'income' | 'expense' | null;
    search?: string;
    tab: FinanceTab;
};
const props = defineProps<{
    summary: FinanceSummary;
    transactions: FinancePaginator<Transaction>;
    farms: Array<{ id: string; name: string; city: string | null }>;
    commodities: string[];
    filters: Filters;
    periodLabel: string;
    exportUrl: string;
}>();
const activeTab = ref<FinanceTab>(props.filters.tab);
const selectedPeriod = ref<FinancePeriod>(props.filters.period);
const selectedYear = ref(props.filters.year);
const selectedKebunId = ref(props.filters.farm_id ?? 'all');
const selectedCommodity = ref(props.filters.commodity ?? 'all');
const selectedType = ref<'income' | 'expense' | 'all'>(
    props.filters.transaction_type ?? 'all',
);
const searchQuery = ref(props.filters.search ?? '');
const isFilterOpen = ref(false);
const showAnalysis = ref(false);
const isLoading = ref(false);
const requestError = ref('');
const kebunList = computed(() => props.farms);
const filteredTransactions = computed(() => props.transactions.data);
const kebunMap = computed(
    () => new Map(props.farms.map((farm) => [farm.id, farm])),
);
let syncingFilters = false;
let searchTimer: ReturnType<typeof setTimeout> | undefined;

function getTransactionKebunId(transaction: Transaction): string {
    return transaction.farm_id;
}
function getKebunName(id?: string): string {
    return id ? (kebunMap.value.get(id)?.name ?? '') : '';
}

const chartMonths = computed(() => {
    const first =
        props.filters.period === 'YTD'
            ? 1
            : (Number(props.filters.period.slice(1)) - 1) * 3 + 1;
    const count = props.filters.period === 'YTD' ? 12 : 3;
    return Array.from({ length: count }, (_, index) => {
        const key = `${props.filters.year}-${String(first + index).padStart(2, '0')}`;
        const row = props.summary.monthly.find((item) => item.month === key);
        return {
            label: new Intl.DateTimeFormat('id-ID', {
                month: 'short',
                timeZone: 'UTC',
            }).format(new Date(`${key}-01T00:00:00Z`)),
            revenue: Number(row?.income ?? '0'),
            expense: Number(row?.expense ?? '0'),
            net: Number(row?.net ?? '0'),
            exactNet: row?.net ?? '0.00',
        };
    });
});
const selectedData = computed(() => ({
    label: props.periodLabel,
    totalRevenue: props.summary.income,
    totalExpense: props.summary.expense,
    netTotal: props.summary.net,
    points: chartMonths.value,
    analysis:
        props.summary.transaction_count === 0
            ? 'Belum ada transaksi pada periode dan filter yang dipilih.'
            : `Tercatat ${props.summary.transaction_count} transaksi dengan pemasukan ${formatRupiah(props.summary.income)}, pengeluaran ${formatRupiah(props.summary.expense)}, dan selisih ${formatRupiah(props.summary.net)}. Ringkasan dihitung dari transaksi database sesuai filter.`,
}));
const selectedPoint = ref<RevenuePoint>({
    label: '-',
    revenue: 0,
    expense: 0,
    net: 0,
});
watch(
    chartMonths,
    (points) => {
        selectedPoint.value =
            points.find(
                (point) => point.revenue !== 0 || point.expense !== 0,
            ) ?? points[0];
    },
    { immediate: true },
);
const selectedPointTitle = computed(() =>
    selectedPoint.value.revenue !== 0 || selectedPoint.value.expense !== 0
        ? `Pendapatan bersih ${selectedPoint.value.label}`
        : `Belum ada transaksi ${selectedPoint.value.label}`,
);
const selectedPointAmount = computed(
    () =>
        chartMonths.value.find(
            (point) => point.label === selectedPoint.value.label,
        )?.exactNet ?? '0.00',
);
function selectPoint(point: RevenuePoint): void {
    selectedPoint.value = point;
}
function selectPeriod(period: FinancePeriod): void {
    selectedPeriod.value = period;
}
const hasActiveFilter = computed(
    () =>
        selectedKebunId.value !== 'all' ||
        selectedCommodity.value !== 'all' ||
        selectedType.value !== 'all' ||
        selectedPeriod.value !== 'YTD' ||
        searchQuery.value.trim() !== '',
);

function visitFilters(page = 1): void {
    requestError.value = '';
    if (
        !Number.isInteger(selectedYear.value) ||
        selectedYear.value < 2000 ||
        selectedYear.value > 2100
    ) {
        requestError.value = 'Tahun harus di antara 2000 dan 2100.';
        return;
    }
    router.get(
        '/finance',
        {
            year: selectedYear.value,
            period: selectedPeriod.value,
            farm_id:
                selectedKebunId.value === 'all'
                    ? undefined
                    : selectedKebunId.value,
            commodity:
                selectedCommodity.value === 'all'
                    ? undefined
                    : selectedCommodity.value,
            transaction_type:
                selectedType.value === 'all' ? undefined : selectedType.value,
            search: searchQuery.value.trim() || undefined,
            tab: activeTab.value,
            page,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                isLoading.value = true;
            },
            onError: () => {
                requestError.value =
                    'Data gagal dimuat. Periksa filter lalu coba lagi.';
            },
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
}
watch(
    [
        selectedPeriod,
        selectedYear,
        selectedKebunId,
        selectedCommodity,
        selectedType,
    ],
    () => {
        if (syncingFilters) return;
        clearTimeout(searchTimer);
        visitFilters();
    },
);
watch(searchQuery, () => {
    if (syncingFilters) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visitFilters(), 300);
});
watch(
    () => props.filters,
    (filters) => {
        syncingFilters = true;
        selectedPeriod.value = filters.period;
        selectedYear.value = filters.year;
        selectedKebunId.value = filters.farm_id ?? 'all';
        selectedCommodity.value = filters.commodity ?? 'all';
        selectedType.value = filters.transaction_type ?? 'all';
        searchQuery.value = filters.search ?? '';
        activeTab.value = filters.tab;
        void nextTick(() => {
            syncingFilters = false;
        });
    },
);
onBeforeUnmount(() => clearTimeout(searchTimer));
function toggleFilter(): void {
    isFilterOpen.value = !isFilterOpen.value;
}
function clearTransactionFilters(): void {
    searchQuery.value = '';
    selectedCommodity.value = 'all';
    selectedType.value = 'all';
    selectedKebunId.value = 'all';
    selectedPeriod.value = 'YTD';
}
</script>

<template>
    <Head title="Keuangan" />

    <FarmerMobileShell active-key="finance">
        <div data-finance-page class="space-y-5 px-4 pt-4 pb-6">
            <FinanceNavigation active="overview" />
            <p
                v-if="requestError"
                role="alert"
                class="rounded-xl border border-error bg-error/10 p-3 text-sm text-error"
            >
                {{ requestError }}
                <button
                    type="button"
                    class="ml-2 underline"
                    @click="visitFilters()"
                >
                    Coba lagi
                </button>
            </p>
            <label class="flex items-center gap-3 text-sm text-muted-foreground"
                >Tahun
                <input
                    v-model.number="selectedYear"
                    type="number"
                    min="2000"
                    max="2100"
                    class="h-9 min-w-0 flex-1 rounded-xl border border-border bg-card px-3 text-foreground"
            /></label>
            <!-- Header with Kebun Filter -->
            <header class="space-y-3">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-success">Keuangan</p>
                        <h1
                            class="mt-0.5 text-2xl font-semibold tracking-tight text-foreground"
                        >
                            Financial Cashflow
                        </h1>
                    </div>
                    <a
                        :href="exportUrl"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground shadow-sm transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        aria-label="Unduh transaksi sesuai filter sebagai CSV"
                    >
                        <FileText :size="15" aria-hidden="true" />
                        <span>Ekspor CSV</span>
                    </a>
                </div>

                <!-- Kebun Filter Selector -->
                <div class="relative">
                    <label for="kebun-filter-select" class="sr-only">
                        Filter Kebun
                    </label>
                    <select
                        id="kebun-filter-select"
                        v-model="selectedKebunId"
                        class="h-10.5 w-full appearance-none rounded-xl border border-border bg-card px-3.5 pr-10 text-xs font-medium text-foreground shadow-xs transition-colors outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                    >
                        <option value="all">Semua Kebun (Konsolidasi)</option>
                        <option
                            v-for="item in kebunList"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name
                            }}{{ item.city ? ` · ${item.city}` : '' }}
                        </option>
                    </select>
                    <div
                        class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground"
                    >
                        <ChevronDown :size="16" />
                    </div>
                </div>
            </header>

            <!-- Navigation Tabs -->
            <div
                class="grid grid-cols-2 overflow-hidden rounded-xl border border-border bg-muted p-1"
                role="tablist"
                aria-label="Tampilan keuangan"
            >
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'chart'"
                    :class="[
                        'rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                        activeTab === 'chart'
                            ? 'bg-secondary text-secondary-foreground shadow-sm'
                            : 'text-secondary hover:bg-card',
                    ]"
                    @click="activeTab = 'chart'"
                >
                    Chart Revenue
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'transactions'"
                    :class="[
                        'rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                        activeTab === 'transactions'
                            ? 'bg-secondary text-secondary-foreground shadow-sm'
                            : 'text-secondary hover:bg-card',
                    ]"
                    @click="activeTab = 'transactions'"
                >
                    Riwayat Transaksi
                </button>
            </div>

            <!-- Loading State -->
            <div
                v-if="isLoading"
                class="space-y-4"
                role="status"
                aria-label="Memuat data keuangan"
            >
                <div class="space-y-2">
                    <div class="h-4 w-40 animate-pulse rounded bg-muted" />
                    <div class="h-10 w-56 animate-pulse rounded bg-muted" />
                    <div class="h-3 w-64 animate-pulse rounded bg-muted" />
                </div>
                <div class="h-72 animate-pulse rounded-2xl bg-muted" />
                <div class="grid grid-cols-5 gap-1.5">
                    <div
                        v-for="period in 5"
                        :key="period"
                        class="h-9 animate-pulse rounded-full bg-muted"
                    />
                </div>
                <div class="h-24 animate-pulse rounded-2xl bg-muted" />
            </div>

            <!-- Chart Revenue Tab -->
            <template v-else-if="activeTab === 'chart'">
                <section aria-labelledby="total-revenue-label">
                    <div class="flex items-center justify-between">
                        <p
                            id="total-revenue-label"
                            class="text-sm font-medium text-muted-foreground"
                        >
                            Pendapatan bersih {{ selectedData.label }}
                        </p>
                        <span
                            v-if="selectedKebunId !== 'all'"
                            class="rounded-md bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary"
                        >
                            {{ getKebunName(selectedKebunId) }}
                        </span>
                    </div>
                    <p
                        class="mt-1 text-3xl font-semibold tracking-tight text-foreground"
                    >
                        {{ formatRupiah(selectedData.netTotal) }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                        <span class="font-medium text-success">
                            Pendapatan kotor
                            {{ formatRupiah(selectedData.totalRevenue) }}
                        </span>
                        <span class="font-medium text-error">
                            Pengeluaran
                            {{ formatRupiah(selectedData.totalExpense) }}
                        </span>
                    </div>
                </section>

                <!-- Chart Container -->
                <section
                    class="rounded-2xl border border-border bg-card p-4 shadow-sm"
                    aria-labelledby="revenue-chart-title"
                >
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <div>
                            <h2
                                id="revenue-chart-title"
                                class="font-semibold text-foreground"
                            >
                                {{ selectedPointTitle }}
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ selectedData.label }}
                            </p>
                        </div>
                        <!-- Badge Revenue Value -->
                        <span
                            class="text-md px-2.5 py-1 font-semibold text-success"
                        >
                            {{ formatRupiah(selectedPointAmount) }}
                        </span>
                    </div>
                    <RevenueChart
                        :points="selectedData.points"
                        @point-selected="selectPoint"
                    />
                </section>

                <!-- Period Filter (Q1, Q2, Q3, Q4, YTD) -->
                <div
                    class="grid grid-cols-5 gap-1.5"
                    role="group"
                    aria-label="Pilih periode keuangan"
                >
                    <button
                        v-for="period in [
                            'Q1',
                            'Q2',
                            'Q3',
                            'Q4',
                            'YTD',
                        ] as FinancePeriod[]"
                        :key="period"
                        type="button"
                        :aria-pressed="selectedPeriod === period"
                        :class="[
                            'rounded-full border py-2 text-center text-xs font-semibold transition-colors',
                            selectedPeriod === period
                                ? 'border-primary bg-primary text-primary-foreground shadow-xs'
                                : 'border-primary text-primary hover:bg-muted',
                        ]"
                        @click="selectPeriod(period)"
                    >
                        {{ period }}
                    </button>
                </div>

                <!-- AI Analysis Section -->
                <section aria-labelledby="analysis-title">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2
                            id="analysis-title"
                            class="text-base font-medium text-muted-foreground"
                        >
                            Hasil Analisa
                        </h2>
                        <button
                            type="button"
                            class="rounded-full border border-primary px-3 py-1.5 text-xs font-semibold text-primary transition-colors hover:bg-muted"
                            :aria-expanded="showAnalysis"
                            @click="showAnalysis = !showAnalysis"
                        >
                            Ringkasan
                        </button>
                    </div>
                    <div
                        v-if="showAnalysis"
                        class="rounded-2xl border border-border bg-muted/60 p-4 text-sm leading-6 text-foreground"
                    >
                        {{ selectedData.analysis }}
                    </div>
                </section>
            </template>

            <!-- Transactions Tab -->
            <section
                v-else-if="!isLoading"
                aria-labelledby="transactions-title"
            >
                <div class="mb-3 flex items-end justify-between">
                    <div>
                        <h2
                            id="transactions-title"
                            class="text-lg font-semibold text-foreground"
                        >
                            Riwayat Transaksi
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ `Transaksi ${periodLabel}` }}
                            {{
                                selectedKebunId !== 'all'
                                    ? `· ${getKebunName(selectedKebunId)}`
                                    : ''
                            }}
                        </p>
                    </div>
                    <span class="text-xs font-medium text-success">
                        {{ transactions.total }} transaksi
                    </span>
                </div>

                <!-- Search and Filter Trigger -->
                <div class="flex items-center gap-2">
                    <label
                        class="flex min-w-0 flex-1 items-center gap-2 rounded-xl border border-border bg-card px-3 text-secondary shadow-sm"
                    >
                        <Search
                            :size="18"
                            :stroke-width="1.8"
                            aria-hidden="true"
                        />
                        <span class="sr-only">Cari transaksi</span>
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Cari transaksi, kebun, atau komoditas"
                            class="h-11 min-w-0 flex-1 bg-transparent text-sm text-foreground outline-none placeholder:text-muted-foreground"
                        />
                    </label>
                    <button
                        type="button"
                        :class="[
                            'relative flex h-11 w-11 shrink-0 items-center justify-center rounded-lg transition-colors',
                            isFilterOpen || hasActiveFilter
                                ? 'bg-secondary text-secondary-foreground'
                                : 'border border-border bg-muted text-secondary hover:bg-secondary hover:text-secondary-foreground',
                        ]"
                        :aria-pressed="isFilterOpen"
                        aria-label="Filter transaksi"
                        @click="toggleFilter"
                    >
                        <Filter
                            :size="19"
                            :stroke-width="1.8"
                            aria-hidden="true"
                        />
                        <span
                            v-if="hasActiveFilter"
                            class="absolute top-2 right-2 h-2 w-2 rounded-full bg-primary ring-2 ring-secondary"
                        />
                    </button>
                </div>

                <!-- Filter Popover / Drawer -->
                <div
                    v-if="isFilterOpen"
                    class="mt-3 space-y-2.5 rounded-xl border border-border bg-card p-3 shadow-xs"
                >
                    <div class="grid min-w-0 grid-cols-2 gap-2">
                        <label class="min-w-0">
                            <span
                                class="mb-1 block truncate text-xs font-medium text-muted-foreground"
                            >
                                Kebun
                            </span>
                            <select
                                v-model="selectedKebunId"
                                class="h-9.5 w-full rounded-lg border border-border bg-card px-2 text-xs text-foreground outline-none focus:border-primary"
                            >
                                <option value="all">Semua kebun</option>
                                <option
                                    v-for="item in kebunList"
                                    :key="item.id"
                                    :value="item.id"
                                >
                                    {{ item.name }}
                                </option>
                            </select>
                        </label>
                        <label class="min-w-0">
                            <span
                                class="mb-1 block truncate text-xs font-medium text-muted-foreground"
                            >
                                Periode
                            </span>
                            <select
                                v-model="selectedPeriod"
                                class="h-9.5 w-full rounded-lg border border-border bg-card px-2 text-xs text-foreground outline-none focus:border-primary"
                            >
                                <option value="YTD">
                                    YTD (Tahun Berjalan)
                                </option>
                                <option value="Q1">Q1 (Jan–Mar)</option>
                                <option value="Q2">Q2 (Apr–Jun)</option>
                                <option value="Q3">Q3 (Jul–Sep)</option>
                                <option value="Q4">Q4 (Okt–Des)</option>
                            </select>
                        </label>
                    </div>

                    <div class="grid min-w-0 grid-cols-2 gap-2">
                        <label class="min-w-0">
                            <span
                                class="mb-1 block truncate text-xs font-medium text-muted-foreground"
                            >
                                Komoditas
                            </span>
                            <select
                                v-model="selectedCommodity"
                                class="h-9.5 w-full rounded-lg border border-border bg-card px-2 text-xs text-foreground outline-none focus:border-primary"
                            >
                                <option value="all">Semua komoditas</option>
                                <option
                                    v-for="commodity in commodities"
                                    :key="commodity"
                                    :value="commodity"
                                >
                                    {{ commodity }}
                                </option>
                            </select>
                        </label>
                        <label class="min-w-0">
                            <span
                                class="mb-1 block truncate text-xs font-medium text-muted-foreground"
                            >
                                Tipe transaksi
                            </span>
                            <select
                                v-model="selectedType"
                                class="h-9.5 w-full rounded-lg border border-border bg-card px-2 text-xs text-foreground outline-none focus:border-primary"
                            >
                                <option value="all">Semua transaksi</option>
                                <option value="income">Pemasukan</option>
                                <option value="expense">Pengeluaran</option>
                            </select>
                        </label>
                    </div>

                    <div class="flex justify-end pt-1">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            aria-label="Reset semua filter"
                            @click="clearTransactionFilters"
                        >
                            <X :size="14" aria-hidden="true" />
                            Reset filter
                        </button>
                    </div>
                </div>

                <p class="mt-4 text-xs text-muted-foreground">
                    Menampilkan {{ transactions.total }} transaksi
                </p>

                <!-- Transactions List -->
                <ul class="mt-1 divide-y divide-border border-y border-border">
                    <li
                        v-for="transaction in filteredTransactions"
                        :key="transaction.id"
                        class="grid grid-cols-[60px_minmax(0,1fr)] gap-3 py-4"
                    >
                        <time
                            class="text-center text-muted-foreground"
                            :datetime="transaction.transaction_date"
                        >
                            <span
                                class="block text-lg leading-5 font-medium text-foreground"
                            >
                                {{ transaction.date.day }}
                            </span>
                            <span class="block text-sm leading-5">
                                {{ transaction.date.month }}
                            </span>
                            <span class="block text-sm leading-5">
                                {{ transaction.date.year }}
                            </span>
                        </time>
                        <div class="min-w-0">
                            <p
                                class="truncate text-sm font-medium text-foreground"
                            >
                                {{ transaction.description }}
                            </p>
                            <div
                                class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <span
                                    v-if="
                                        getKebunName(
                                            getTransactionKebunId(transaction),
                                        )
                                    "
                                    class="inline-flex items-center rounded bg-primary/10 px-1.5 py-0.5 text-[11px] font-medium text-primary"
                                >
                                    {{
                                        getKebunName(
                                            getTransactionKebunId(transaction),
                                        )
                                    }}
                                </span>
                                <span>{{
                                    transaction.commodity ?? 'Tanpa komoditas'
                                }}</span>
                                <span>·</span>
                                <span>{{ transaction.category }}</span>
                            </div>
                            <p
                                :class="[
                                    'mt-1 text-xl leading-6 font-semibold',
                                    transaction.type === 'income'
                                        ? 'text-success'
                                        : 'text-error',
                                ]"
                            >
                                {{ transaction.type === 'income' ? '+' : '-' }}
                                {{ formatRupiah(transaction.amount) }}
                            </p>
                        </div>
                    </li>
                    <li
                        v-if="filteredTransactions.length === 0"
                        class="px-4 py-10 text-center text-sm text-muted-foreground"
                    >
                        Transaksi tidak ditemukan untuk filter ini.
                    </li>
                </ul>
                <nav
                    v-if="transactions.last_page > 1"
                    aria-label="Halaman transaksi"
                    class="mt-4 flex items-center justify-between gap-2 text-xs"
                >
                    <button
                        type="button"
                        :disabled="transactions.current_page === 1 || isLoading"
                        class="rounded-xl border border-border px-3 py-2 disabled:opacity-50"
                        @click="visitFilters(transactions.current_page - 1)"
                    >
                        Sebelumnya
                    </button>
                    <span aria-current="page"
                        >Halaman {{ transactions.current_page }} /
                        {{ transactions.last_page }}</span
                    >
                    <button
                        type="button"
                        :disabled="
                            transactions.current_page ===
                                transactions.last_page || isLoading
                        "
                        class="rounded-xl border border-border px-3 py-2 disabled:opacity-50"
                        @click="visitFilters(transactions.current_page + 1)"
                    >
                        Berikutnya
                    </button>
                </nav>
            </section>
        </div>
    </FarmerMobileShell>
</template>

<style>
main:has(> [data-finance-page]) {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

main:has(> [data-finance-page])::-webkit-scrollbar {
    display: none;
}
</style>
