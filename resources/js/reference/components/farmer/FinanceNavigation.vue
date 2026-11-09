<!-- Historical source: 73f2555d91d0fb2d6acb7bc70813921edd479ea4 / resources/js/components/farmer/FinanceNavigation.vue -->
<script setup lang="ts">
import { Link } from '@/reference/inertia';
import type { FinanceResource } from '@/reference/types/finance';
defineProps<{ active?: FinanceResource | 'overview' }>();
</script>
<template><nav aria-label="Menu keuangan" class="flex flex-wrap gap-2"><Link href="/referensi/finance" class="rounded-xl border border-border bg-primary px-3 py-2 text-xs font-medium text-primary-foreground" aria-current="page">Ringkasan</Link><p class="self-center text-xs text-muted-foreground">Transaksi fixture · tanpa perubahan data</p></nav></template>
