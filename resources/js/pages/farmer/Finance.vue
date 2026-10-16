<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { Filter, Search, X } from "@lucide/vue";
import { computed, ref } from "vue";
import FarmerMobileShell from "@/components/farmer/FarmerMobileShell.vue";
import RevenueChart from "@/components/farmer/RevenueChart.vue";
import { useFarmerNavigation } from "@/composables/useFarmerNavigation";
import {
    farmerFinanceMock,
    type FinanceCommodity,
    type FinanceQuarter,
    type FinanceTransaction,
    type RevenuePoint,
} from "@/mocks/farmerFinance";

type FinanceTab = "chart" | "transactions";

const { navigate } = useFarmerNavigation();
const activeTab = ref<FinanceTab>("chart");
const selectedQuarter = ref<FinanceQuarter>("Q1");
const showAnalysis = ref(false);
const searchQuery = ref("");
const selectedCommodity = ref<FinanceCommodity | "all">("all");
const selectedType = ref<FinanceTransaction["type"] | "all">("all");
const isFilterOpen = ref(false);

const hasActiveFilter = computed(
    () => selectedCommodity.value !== "all" || selectedType.value !== "all",
);

const selectedData = computed(
    () => farmerFinanceMock.quarters[selectedQuarter.value],
);
const selectedPoint = ref<RevenuePoint>(selectedData.value.points[0]);

function selectQuarter(quarter: FinanceQuarter): void {
    selectedQuarter.value = quarter;
    selectedPoint.value = farmerFinanceMock.quarters[quarter].points[0];
}

function selectPoint(point: RevenuePoint): void {
    selectedPoint.value = point;
}

const filteredTransactions = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return farmerFinanceMock.transactions.filter((transaction) => {
        const matchesQuery =
            !query ||
            [
                transaction.description,
                transaction.category,
                transaction.commodity,
            ].some((value) => value.toLowerCase().includes(query));
        const matchesCommodity =
            selectedCommodity.value === "all" ||
            transaction.commodity === selectedCommodity.value;
        const matchesType =
            selectedType.value === "all" ||
            transaction.type === selectedType.value;

        return matchesQuery && matchesCommodity && matchesType;
    });
});

function toggleFilter(): void {
    isFilterOpen.value = !isFilterOpen.value;
}

function clearTransactionFilters(): void {
    searchQuery.value = "";
    selectedCommodity.value = "all";
    selectedType.value = "all";
}

const formatRupiah = (value: number): string =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(value);
</script>

<template>
    <Head title="Keuangan" />

    <FarmerMobileShell active-key="finance" @select="navigate">
        <div class="space-y-6 px-5 pt-8 pb-6">
            <header>
                <p class="text-sm font-medium text-[#1c5e21]">Keuangan</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Financial Cashflow
                </h1>
            </header>

            <div
                class="grid grid-cols-2 overflow-hidden rounded-xl bg-[#d9d9d9] p-1"
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
                            ? 'bg-[#1c5e21] text-white shadow-sm'
                            : 'text-[#1c5e21] hover:bg-white',
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
                            ? 'bg-[#1c5e21] text-white shadow-sm'
                            : 'text-[#1c5e21] hover:bg-white',
                    ]"
                    @click="activeTab = 'transactions'"
                >
                    Riwayat Transaksi
                </button>
            </div>

            <template v-if="activeTab === 'chart'">
                <section aria-labelledby="total-revenue-label">
                    <p id="total-revenue-label" class="text-sm text-[#070807]">
                        Pendapatan bersih {{ selectedPoint.label }}
                    </p>
                    <p class="mt-1 text-3xl font-semibold tracking-tight">
                        {{ formatRupiah(selectedPoint.net) }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                        <span class="text-[#1c5e21]">
                            Pendapatan {{ formatRupiah(selectedPoint.revenue) }}
                        </span>
                        <span class="text-[#bc0202]">
                            Pengeluaran
                            {{ formatRupiah(selectedPoint.expense) }}
                        </span>
                    </div>
                    <!-- <p class="mt-1 text-xs text-[#93a99f]">
                        {{ selectedData.label }} · Bersih kuartal
                        {{ formatRupiah(selectedData.netTotal) }}
                    </p> -->
                </section>

                <section
                    class="rounded-2xl border border-[#d9d9d9] bg-white p-4 shadow-sm"
                    aria-labelledby="revenue-chart-title"
                >
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <div>
                            <h2 id="revenue-chart-title" class="font-semibold">
                                Revenue {{ selectedQuarter }}
                            </h2>
                            <p class="mt-1 text-xs text-[#93a99f]">
                                {{ selectedData.label }}
                            </p>
                        </div>
                        <!-- Badge Revenue Value -->
                        <span
                            class="px-2.5 py-1 text-md font-semibold text-[#1c5e21]"
                        >
                            {{ formatRupiah(selectedData.netTotal) }}
                        </span>
                    </div>
                    <RevenueChart
                        :points="selectedData.points"
                        @point-selected="selectPoint"
                    />
                </section>

                <div
                    class="grid grid-cols-4 gap-2"
                    role="group"
                    aria-label="Pilih kuartal"
                >
                    <button
                        v-for="quarter in [
                            'Q1',
                            'Q2',
                            'Q3',
                            'Q4',
                        ] as FinanceQuarter[]"
                        :key="quarter"
                        type="button"
                        :aria-pressed="selectedQuarter === quarter"
                        :class="[
                            'rounded-full border px-3 py-2 text-sm font-medium transition-colors',
                            selectedQuarter === quarter
                                ? 'border-[#1c5e21] bg-[#1c5e21] text-white'
                                : 'border-[#4a943e] text-[#1c5e21] hover:bg-[#d9d9d9]',
                        ]"
                        @click="selectQuarter(quarter)"
                    >
                        {{ quarter }}
                    </button>
                </div>

                <section aria-labelledby="analysis-title">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2
                            id="analysis-title"
                            class="text-lg font-medium text-[#93a99f]"
                        >
                            Hasil Analisa
                        </h2>
                        <button
                            type="button"
                            class="rounded-full border border-[#4a943e] px-3 py-1.5 text-xs font-semibold text-[#1c5e21] transition-colors hover:bg-[#d9d9d9]"
                            :aria-expanded="showAnalysis"
                            @click="showAnalysis = !showAnalysis"
                        >
                            Analisis AI
                        </button>
                    </div>
                    <div
                        v-if="showAnalysis"
                        class="rounded-2xl bg-[#d9d9d9] p-4 text-sm leading-6 text-[#070807]"
                    >
                        {{ selectedData.analysis }}
                    </div>
                </section>
            </template>

            <section v-else aria-labelledby="transactions-title">
                <div class="mb-3 flex items-end justify-between">
                    <div>
                        <h2
                            id="transactions-title"
                            class="text-lg font-semibold"
                        >
                            Riwayat Transaksi
                        </h2>
                        <p class="mt-1 text-xs text-[#93a99f]">
                            Transaksi terbaru tahun berjalan
                        </p>
                    </div>
                    <span class="text-xs font-medium text-[#1c5e21]">
                        {{ filteredTransactions.length }} transaksi
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <label
                        class="flex min-w-0 flex-1 items-center gap-2 rounded-lg bg-[#d9d9d9] px-3 text-[#1c5e21]"
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
                            placeholder="Search"
                            class="h-11 min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-[#1c5e21]/80"
                        />
                    </label>
                    <button
                        type="button"
                        :class="[
                            'relative flex h-11 w-11 shrink-0 items-center justify-center rounded-lg transition-colors',
                            isFilterOpen || hasActiveFilter
                                ? 'bg-[#1c5e21] text-white'
                                : 'bg-[#93a99f] text-white hover:bg-[#1c5e21]',
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
                            class="absolute top-2 right-2 h-2 w-2 rounded-full bg-[#abe7b2] ring-2 ring-[#1c5e21]"
                        />
                    </button>
                </div>

                <div
                    v-if="isFilterOpen"
                    class="mt-3 flex items-end gap-2 rounded-xl border border-[#d9d9d9] bg-white p-3 shadow-xs"
                >
                    <div class="grid min-w-0 flex-1 grid-cols-2 gap-2">
                        <label class="min-w-0">
                            <span
                                class="mb-1 block truncate text-xs font-medium text-[#93a99f]"
                            >
                                Komoditas
                            </span>
                            <select
                                v-model="selectedCommodity"
                                class="h-10 w-full rounded-lg border border-[#d9d9d9] bg-white px-2.5 text-xs outline-none focus:border-[#68bb6c]"
                            >
                                <option value="all">Semua komoditas</option>
                                <option value="Jagung">Jagung</option>
                                <option value="Rumput odot">Rumput odot</option>
                                <option value="Kedelai">Kedelai</option>
                            </select>
                        </label>
                        <label class="min-w-0">
                            <span
                                class="mb-1 block truncate text-xs font-medium text-[#93a99f]"
                            >
                                Tipe transaksi
                            </span>
                            <select
                                v-model="selectedType"
                                class="h-10 w-full rounded-lg border border-[#d9d9d9] bg-white px-2.5 text-xs outline-none focus:border-[#68bb6c]"
                            >
                                <option value="all">Semua transaksi</option>
                                <option value="income">Pemasukan</option>
                                <option value="expense">Pengeluaran</option>
                            </select>
                        </label>
                    </div>
                    <button
                        type="button"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-[#d9d9d9] text-[#93a99f] transition-colors hover:bg-[#d9d9d9] hover:text-[#070807]"
                        aria-label="Reset semua filter"
                        title="Reset filter"
                        @click="clearTransactionFilters"
                    >
                        <X :size="17" aria-hidden="true" />
                    </button>
                </div>

                <p class="mt-4 text-xs text-[#93a99f]">
                    Menampilkan {{ filteredTransactions.length }} transaksi
                </p>

                <ul
                    class="mt-1 divide-y divide-[#d9d9d9] border-y border-[#d9d9d9]"
                >
                    <li
                        v-for="transaction in filteredTransactions"
                        :key="transaction.id"
                        class="grid grid-cols-[60px_minmax(0,1fr)] gap-3 py-4"
                    >
                        <time
                            class="text-center text-[#93a99f]"
                            :datetime="`${transaction.date.year}-${transaction.date.month}-${transaction.date.day}`"
                        >
                            <span class="block text-lg leading-5 font-medium">
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
                                class="truncate text-sm font-medium text-[#070807]"
                            >
                                {{ transaction.description }}
                            </p>
                            <p class="mt-1 text-xs text-[#93a99f]">
                                {{ transaction.commodity }} ·
                                {{ transaction.category }}
                            </p>
                            <p
                                :class="[
                                    'mt-1 text-xl leading-6 font-semibold',
                                    transaction.type === 'income'
                                        ? 'text-[#1c5e21]'
                                        : 'text-[#bc0202]',
                                ]"
                            >
                                {{ transaction.type === "income" ? "+" : "-" }}
                                {{ formatRupiah(transaction.amount) }}
                            </p>
                        </div>
                    </li>
                    <li
                        v-if="filteredTransactions.length === 0"
                        class="px-4 py-10 text-center text-sm text-[#93a99f]"
                    >
                        Transaksi tidak ditemukan.
                    </li>
                </ul>
            </section>
        </div>
    </FarmerMobileShell>
</template>
