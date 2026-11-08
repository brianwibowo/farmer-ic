<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import FinanceNavigation from '@/components/farmer/FinanceNavigation.vue';
import { financeLabels, formatFinanceMoney } from '@/lib/finance';
import type { FinanceRecord, FinanceResource } from '@/types/finance';

const props = defineProps<{
    title: string;
    resource: FinanceResource;
    record: FinanceRecord;
}>();
const labels: Record<string, string> = {
    id: 'ID',
    farm_id: 'ID kebun',
    category_id: 'ID kategori',
    planting_cycle_id: 'ID siklus tanam',
    name: 'Nama',
    description: 'Keterangan',
    transaction_date: 'Tanggal transaksi',
    transaction_type: 'Jenis transaksi',
    amount: 'Nominal',
    period_start: 'Awal periode',
    period_end: 'Akhir periode',
    budget_amount: 'Anggaran',
    projected_income: 'Proyeksi pemasukan',
    projected_expense: 'Proyeksi pengeluaran',
    recorded_by: 'ID pencatat',
    created_by: 'ID pembuat',
    calculated_at: 'Dihitung pada',
    created_at: 'Dibuat pada',
};
const money = [
    'amount',
    'budget_amount',
    'projected_income',
    'projected_expense',
];
const rows = computed(() =>
    Object.entries(props.record)
        .filter(([key]) => labels[key])
        .map(([key, value]) => ({
            label: labels[key],
            value: money.includes(key)
                ? formatFinanceMoney(typeof value === 'string' ? value : null)
                : key === 'transaction_type'
                  ? value === 'income'
                      ? 'Pemasukan'
                      : 'Pengeluaran'
                  : (value ?? 'Tidak diisi'),
        })),
);
</script>

<template>
    <Head :title="title" />
    <FarmerMobileShell active-key="finance"
        ><div class="space-y-5 px-4 py-4">
            <Link
                :href="`/finance/${resource}`"
                class="text-sm text-primary underline"
                >Kembali ke {{ financeLabels[resource].toLowerCase() }}</Link
            >
            <h1 class="text-2xl font-semibold text-foreground">{{ title }}</h1>
            <FinanceNavigation :active="resource" />
            <dl
                class="divide-y divide-border rounded-xl border border-border bg-card px-4"
            >
                <div v-for="row in rows" :key="row.label" class="py-3">
                    <dt class="text-xs text-muted-foreground">
                        {{ row.label }}
                    </dt>
                    <dd class="mt-1 text-sm break-words text-foreground">
                        {{ row.value }}
                    </dd>
                </div>
            </dl>
        </div></FarmerMobileShell
    >
</template>
