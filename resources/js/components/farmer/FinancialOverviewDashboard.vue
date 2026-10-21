<script setup lang="ts">
import { onMounted, ref } from 'vue';
import StatCard from '@/components/StatCardDashboard.vue';
import {
    getDashboardFinancialSummary,
    type DashboardFinancialSummary,
} from '@/services/farmDemoService';

const financialSummary = ref<DashboardFinancialSummary | null>(null);
const loading = ref(true);

onMounted(async () => {
    try {
        financialSummary.value = await getDashboardFinancialSummary();
    } catch {
        financialSummary.value = null;
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
        <div v-else-if="financialSummary" class="grid grid-cols-2 gap-3">
            <StatCard
                title="Pendapatan Kebun"
                :value="formatRupiah(financialSummary.income)"
                projection-title="Proyeksi Pendapatan"
                :projection-value="
                    formatRupiah(financialSummary.projectedIncome)
                "
                type="income"
                :information="financialSummary.information.income"
                :projection-information="
                    financialSummary.information.projectedIncome
                "
            />
            <StatCard
                title="Pengeluaran Kebun"
                :value="formatRupiah(financialSummary.expenses)"
                projection-title="Proyeksi Pengeluaran"
                :projection-value="
                    formatRupiah(financialSummary.projectedExpenses)
                "
                type="expense"
                :information="financialSummary.information.expenses"
                :projection-information="
                    financialSummary.information.projectedExpenses
                "
            />
        </div>
        <p v-else role="alert" class="text-sm text-muted-foreground">
            Ringkasan keuangan belum dapat dimuat. Muat ulang halaman untuk
            mencoba kembali.
        </p>
    </section>
</template>
