<script setup lang="ts">
import { onMounted, ref } from 'vue';
import StatCard from '@/components/StatCardDashboard.vue';
import {
    getDashboardFinancialSummary,
    type DashboardFinancialSummary,
} from '@/services/farmDemoService';

const financialSummary = ref<DashboardFinancialSummary>({
    income: 0,
    projectedIncome: 0,
    expenses: 0,
    projectedExpenses: 0,
});
const loading = ref(true);

onMounted(async () => {
    try {
        financialSummary.value = await getDashboardFinancialSummary();
    } finally {
        loading.value = false;
    }
});

const formatRupiah = (value: number): string =>
    `Rp ${new Intl.NumberFormat('id-ID').format(value)}`;
</script>

<template>
    <section aria-label="Pendapatan dan pengeluaran kebun">
        <div
            v-if="loading"
            class="grid grid-cols-2 gap-3"
            role="status"
            aria-label="Memuat ringkasan keuangan"
        >
            <div class="h-32 animate-pulse rounded-2xl bg-muted" />
            <div class="h-32 animate-pulse rounded-2xl bg-muted" />
        </div>
        <div v-else class="grid grid-cols-2 gap-3">
            <StatCard
                title="Pendapatan Kebun"
                :value="formatRupiah(financialSummary.income)"
                projection-title="Proyeksi Pendapatan"
                :projection-value="
                    formatRupiah(financialSummary.projectedIncome)
                "
                type="income"
            />
            <StatCard
                title="Pengeluaran Kebun"
                :value="formatRupiah(financialSummary.expenses)"
                projection-title="Proyeksi Pengeluaran"
                :projection-value="
                    formatRupiah(financialSummary.projectedExpenses)
                "
                type="expense"
            />
        </div>
    </section>
</template>
