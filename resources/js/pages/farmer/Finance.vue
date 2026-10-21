<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ChevronDown, Filter, Search, X } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import RevenueChart from '@/components/farmer/RevenueChart.vue';
import { useFarmerNavigation } from '@/composables/useFarmerNavigation';
import {
    type FinanceCommodity,
    type FinancePeriod,
    type FinanceQuarter,
    type FinanceTransaction,
    type QuarterFinance,
    type RevenuePoint,
} from '@/data/farmerFinance';
import { type Kebun } from '@/data/kebun';
import { getFarmFinance } from '@/services/farmDemoService';
import { listKebun } from '@/services/kebunService';

type FinanceTab = 'chart' | 'transactions';

const { navigate } = useFarmerNavigation();
const activeTab = ref<FinanceTab>('chart');
const selectedPeriod = ref<FinancePeriod>('Q1');
const selectedKebunId = ref<string>('all');
const kebunList = ref<Kebun[]>([]);
const showAnalysis = ref(false);
const searchQuery = ref('');
const selectedCommodity = ref<FinanceCommodity | 'all'>('all');
const selectedType = ref<FinanceTransaction['type'] | 'all'>('all');
const isFilterOpen = ref(false);
const isLoading = ref(true);

type FinanceData = Awaited<ReturnType<typeof getFarmFinance>>;
const financeData = ref<FinanceData>();

const kebunMap = computed(
    () => new Map(kebunList.value.map((kebun) => [kebun.id, kebun])),
);

const periodMonthsMap: Record<FinancePeriod, string[]> = {
    Q1: ['Jan', 'Feb', 'Mar'],
    Q2: ['Apr', 'Mei', 'Jun'],
    Q3: ['Jul', 'Agu', 'Sep'],
    Q4: ['Okt', 'Nov', 'Des'],
    YTD: [
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
    ],
};

const periodLabels: Record<FinancePeriod, string> = {
    Q1: 'Januari–Maret 2026',
    Q2: 'April–Juni 2026',
    Q3: 'Juli–September 2026',
    Q4: 'Oktober–Desember 2026',
    YTD: 'Januari–Desember 2026 (YTD)',
};

const transactionKebunFallback: Record<number, string> = {
    1: 'kebun-002',
    2: 'kebun-002',
    3: 'kebun-003',
    4: 'kebun-003',
    5: 'kebun-001',
    6: 'kebun-001',
    7: 'kebun-005',
    8: 'kebun-005',
    9: 'kebun-003',
    10: 'kebun-003',
    11: 'kebun-004',
    12: 'kebun-004',
    13: 'kebun-002',
    14: 'kebun-002',
    15: 'kebun-003',
    16: 'kebun-003',
    17: 'kebun-001',
    18: 'kebun-001',
    19: 'kebun-005',
    20: 'kebun-005',
    21: 'kebun-003',
    22: 'kebun-003',
    23: 'kebun-004',
    24: 'kebun-004',
};

function getTransactionKebunId(tx: FinanceTransaction): string {
    return tx.kebunId ?? transactionKebunFallback[tx.id] ?? '';
}

function getKebunName(kebunId?: string): string {
    if (!kebunId) return '';
    return kebunMap.value.get(kebunId)?.name ?? '';
}

const emptyPoint: RevenuePoint = {
    label: '-',
    revenue: 0,
    expense: 0,
    net: 0,
};

const emptyQuarter: QuarterFinance = {
    label: 'Memuat data keuangan',
    totalRevenue: 0,
    totalExpense: 0,
    netTotal: 0,
    points: [emptyPoint],
    analysis: '',
};

const selectedData = computed<QuarterFinance>(() => {
    if (!financeData.value) return emptyQuarter;

    const months = periodMonthsMap[selectedPeriod.value];
    const label = periodLabels[selectedPeriod.value];
    const selectedKebun = kebunMap.value.get(selectedKebunId.value);

    // Filter transactions by kebun
    const baseTransactions = financeData.value.transactions.filter((tx) => {
        if (selectedKebunId.value === 'all') return true;
        return getTransactionKebunId(tx) === selectedKebunId.value;
    });

    const points: RevenuePoint[] = months.map((month) => {
        const monthTxs = baseTransactions.filter((tx) => tx.date.month === month);
        const revenue = monthTxs
            .filter((tx) => tx.type === 'income')
            .reduce((sum, tx) => sum + tx.amount, 0);
        const expense = monthTxs
            .filter((tx) => tx.type === 'expense')
            .reduce((sum, tx) => sum + tx.amount, 0);
        const net = revenue - expense;

        return { label: month, revenue, expense, net };
    });

    const totalRevenue = points.reduce((sum, p) => sum + p.revenue, 0);
    const totalExpense = points.reduce((sum, p) => sum + p.expense, 0);
    const netTotal = totalRevenue - totalExpense;

    let analysis = '';
    if (selectedKebunId.value === 'all') {
        if (selectedPeriod.value === 'YTD') {
            analysis =
                'Secara kumulatif tahun berjalan (YTD 2026), performa keuangan seluruh kebun menunjukkan tren yang sangat positif dengan total pendapatan bersih mencapai ' +
                formatRupiah(netTotal) +
                '. Kuartal 3 mencatatkan margin tertinggi didorong oleh optimalisasi panen jagung dan kontrak rumput odot, sementara pengeluaran operasional dan pemeliharaan terkendali di bawah 36% dari total penerimaan kotor.';
        } else {
            const quarterKey = selectedPeriod.value as FinanceQuarter;
            analysis =
                financeData.value.quarters[quarterKey]?.analysis ??
                'Laporan kuartal konsolidasi seluruh kebun berjalan stabil sesuai target perencanaan tahunan.';
        }
    } else if (selectedKebun) {
        if (selectedPeriod.value === 'YTD') {
            analysis = `Laporan tahun berjalan (YTD 2026) untuk ${selectedKebun.name} (${selectedKebun.city}) mencatatkan total pendapatan kotor ${formatRupiah(totalRevenue)} dan pengeluaran ${formatRupiah(totalExpense)}, menghasilkan laba bersih ${formatRupiah(netTotal)} dari komoditas ${selectedKebun.seedType}.`;
        } else if (totalRevenue > 0) {
            analysis = `Pada periode ${label}, ${selectedKebun.name} membukukan pendapatan kotor ${formatRupiah(totalRevenue)} dan beban biaya ${formatRupiah(totalExpense)}, menghasilkan laba bersih ${formatRupiah(netTotal)} dari komoditas ${selectedKebun.seedType}.`;
        } else {
            analysis = `Tidak ada transaksi penjualan panen maupun operasional tercatat untuk ${selectedKebun.name} pada ${label}. Siklus panen aktif untuk kebun ini terjadwal pada kuartal lain tahun berjalan.`;
        }
    }

    return {
        label,
        totalRevenue,
        totalExpense,
        netTotal,
        points,
        analysis,
    };
});

const selectedPoint = ref<RevenuePoint>(emptyPoint);

function updateSelectedPoint(): void {
    const points = selectedData.value.points;
    const activePoint = points.find((p) => p.revenue > 0 || p.expense > 0);
    selectedPoint.value = activePoint ?? points[0] ?? emptyPoint;
}

onMounted(async () => {
    try {
        const [finance, kebun] = await Promise.all([
            getFarmFinance(),
            listKebun(),
        ]);
        financeData.value = finance;
        kebunList.value = kebun;

        const urlParams = new URLSearchParams(window.location.search);
        const kebunParam = urlParams.get('kebun');
        if (
            kebunParam &&
            (kebun.some((k) => k.id === kebunParam) || kebunParam === 'all')
        ) {
            selectedKebunId.value = kebunParam;
        }

        const periodParam = urlParams.get('period') as FinancePeriod | null;
        if (
            periodParam &&
            ['Q1', 'Q2', 'Q3', 'Q4', 'YTD'].includes(periodParam)
        ) {
            selectedPeriod.value = periodParam;
        }

        updateSelectedPoint();
    } finally {
        isLoading.value = false;
    }
});

function selectPeriod(period: FinancePeriod): void {
    selectedPeriod.value = period;
    updateSelectedPoint();
}

function selectPoint(point: RevenuePoint): void {
    selectedPoint.value = point;
}

watch(selectedKebunId, () => {
    updateSelectedPoint();
});

const hasActiveFilter = computed(
    () =>
        selectedKebunId.value !== 'all' ||
        selectedCommodity.value !== 'all' ||
        selectedType.value !== 'all' ||
        selectedPeriod.value !== 'YTD',
);

const filteredTransactions = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return (financeData.value?.transactions ?? []).filter((transaction) => {
        const kebunId = getTransactionKebunId(transaction);
        const kebunName = getKebunName(kebunId);

        const matchesQuery =
            !query ||
            [
                transaction.description,
                transaction.category,
                transaction.commodity,
                kebunName,
            ].some((value) => value.toLowerCase().includes(query));

        const matchesKebun =
            selectedKebunId.value === 'all' ||
            kebunId === selectedKebunId.value;

        const matchesCommodity =
            selectedCommodity.value === 'all' ||
            transaction.commodity === selectedCommodity.value;

        const matchesType =
            selectedType.value === 'all' ||
            transaction.type === selectedType.value;

        const matchesPeriod =
            selectedPeriod.value === 'YTD' ||
            periodMonthsMap[selectedPeriod.value].includes(
                transaction.date.month,
            );

        return (
            matchesQuery &&
            matchesKebun &&
            matchesCommodity &&
            matchesType &&
            matchesPeriod
        );
    });
});

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

const formatRupiah = (value: number): string =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);
</script>

<template>
    <Head title="Keuangan" />

    <FarmerMobileShell active-key="finance" @select="navigate">
        <div data-finance-page class="space-y-5 px-4 pt-4 pb-6">
            <!-- Header with Kebun Filter -->
            <header class="space-y-3">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-[#1c5e21] dark:text-[#8fe697]">
                            Keuangan
                        </p>
                        <h1 class="mt-0.5 text-2xl font-semibold tracking-tight text-foreground">
                            Financial Cashflow
                        </h1>
                    </div>
                </div>

                <!-- Kebun Filter Selector -->
                <div class="relative">
                    <label for="kebun-filter-select" class="sr-only">
                        Filter Kebun
                    </label>
                    <select
                        id="kebun-filter-select"
                        v-model="selectedKebunId"
                        class="h-10.5 w-full appearance-none rounded-xl border border-border bg-card px-3.5 pr-10 text-xs font-medium text-foreground shadow-xs outline-none transition-colors focus:border-primary focus:ring-1 focus:ring-primary"
                    >
                        <option value="all">Semua Kebun (Konsolidasi)</option>
                        <option
                            v-for="item in kebunList"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }} · {{ item.city }} ({{ item.seedType }})
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
                    <p class="mt-1 text-3xl font-semibold tracking-tight text-foreground">
                        {{ formatRupiah(selectedData.netTotal) }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                        <span class="font-medium text-[#1c5e21] dark:text-[#8fe697]">
                            Pendapatan kotor
                            {{ formatRupiah(selectedData.totalRevenue) }}
                        </span>
                        <span class="font-medium text-[#bc0202] dark:text-[#ff8585]">
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
                                Pendapatan bersih {{ selectedPoint.label }}
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ selectedData.label }}
                            </p>
                        </div>
                        <!-- Badge Revenue Value -->
                        <span
                            class="text-md px-2.5 py-1 font-semibold text-[#1c5e21] dark:text-[#8fe697]"
                        >
                            {{ formatRupiah(selectedPoint.net) }}
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
                                ? 'border-[#1c5e21] bg-[#1c5e21] text-white shadow-xs'
                                : 'border-[#4a943e] text-[#1c5e21] hover:bg-[#d9d9d9]/60 dark:border-[#2d4538] dark:text-[#8fe697] dark:hover:bg-muted',
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
                            class="rounded-full border border-[#4a943e] px-3 py-1.5 text-xs font-semibold text-[#1c5e21] transition-colors hover:bg-[#d9d9d9]/60 dark:border-[#2d4538] dark:text-[#8fe697] dark:hover:bg-muted"
                            :aria-expanded="showAnalysis"
                            @click="showAnalysis = !showAnalysis"
                        >
                            Analisis AI
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
                            {{
                                selectedPeriod === 'YTD'
                                    ? 'Semua transaksi tahun 2026 (YTD)'
                                    : `Transaksi periode ${selectedPeriod} (2026)`
                            }}
                            {{
                                selectedKebunId !== 'all'
                                    ? `· ${getKebunName(selectedKebunId)}`
                                    : ''
                            }}
                        </p>
                    </div>
                    <span class="text-xs font-medium text-[#1c5e21] dark:text-[#8fe697]">
                        {{ filteredTransactions.length }} transaksi
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
                                <option value="YTD">YTD (Tahun Berjalan)</option>
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
                                <option value="Jagung">Jagung</option>
                                <option value="Rumput odot">Rumput odot</option>
                                <option value="Kedelai">Kedelai</option>
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
                    Menampilkan {{ filteredTransactions.length }} transaksi
                </p>

                <!-- Transactions List -->
                <ul
                    class="mt-1 divide-y divide-border border-y border-border"
                >
                    <li
                        v-for="transaction in filteredTransactions"
                        :key="transaction.id"
                        class="grid grid-cols-[60px_minmax(0,1fr)] gap-3 py-4"
                    >
                        <time
                            class="text-center text-muted-foreground"
                            :datetime="`${transaction.date.year}-${transaction.date.month}-${transaction.date.day}`"
                        >
                            <span class="block text-lg leading-5 font-medium text-foreground">
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
                            <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                <span
                                    v-if="getKebunName(getTransactionKebunId(transaction))"
                                    class="inline-flex items-center rounded bg-[#1c5e21]/10 px-1.5 py-0.5 text-[11px] font-medium text-[#1c5e21] dark:bg-[#1c5e21]/20 dark:text-[#8fe697]"
                                >
                                    {{ getKebunName(getTransactionKebunId(transaction)) }}
                                </span>
                                <span>{{ transaction.commodity }}</span>
                                <span>·</span>
                                <span>{{ transaction.category }}</span>
                            </div>
                            <p
                                :class="[
                                    'mt-1 text-xl leading-6 font-semibold',
                                    transaction.type === 'income'
                                        ? 'text-[#1c5e21] dark:text-[#8fe697]'
                                        : 'text-[#bc0202] dark:text-[#ff8585]',
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
