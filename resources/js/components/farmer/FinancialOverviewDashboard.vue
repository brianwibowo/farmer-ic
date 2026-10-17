<script setup lang="ts">
import StatCard from '@/components/StatCardDashboard.vue';
import financialSummary from '@/data/dashboardFinancialSummary.json';

const formatRupiah = (value: number): string =>
    `Rp ${new Intl.NumberFormat('id-ID').format(value)}`;
</script>

<template>
    <section
        class="grid grid-cols-2 gap-3"
        aria-label="Pendapatan dan pengeluaran kebun"
    >
        <StatCard
            title="Pendapatan Kebun"
            :value="formatRupiah(financialSummary.income)"
            projection-title="Proyeksi Pendapatan"
            :projection-value="formatRupiah(financialSummary.projectedIncome)"
            type="income"
        />
        <StatCard
            title="Pengeluaran Kebun"
            :value="formatRupiah(financialSummary.expenses)"
            projection-title="Proyeksi Pengeluaran"
            :projection-value="formatRupiah(financialSummary.projectedExpenses)"
            type="expense"
        />
    </section>
</template>
