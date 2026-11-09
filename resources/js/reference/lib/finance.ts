// Historical source: 73f2555d91d0fb2d6acb7bc70813921edd479ea4 / resources/js/lib/finance.ts
import type { FinanceRecord, FinanceResource } from '@/reference/types/finance';

export const financeLabels: Record<FinanceResource, string> = {
    transactions: 'Transaksi',
    categories: 'Kategori',
    budgets: 'Anggaran',
    projections: 'Proyeksi',
};

/** Keep database decimal strings exact when rendering values above Number.MAX_SAFE_INTEGER. */
export function formatFinanceMoney(value: string | null | undefined): string {
    if (value === null || value === undefined) return 'Belum diisi';
    const negative = value.startsWith('-');
    const [whole, fraction = '00'] = value.replace(/^-/, '').split('.');
    return `${negative ? '-' : ''}Rp${new Intl.NumberFormat('id-ID').format(BigInt(whole || '0'))},${fraction.padEnd(2, '0')}`;
}

export function financeRecordTitle(
    record: FinanceRecord,
    resource: FinanceResource,
): string {
    if (resource === 'categories') return record.name ?? 'Kategori';
    if (resource === 'transactions')
        return record.description || 'Transaksi keuangan';
    return `${financeLabels[resource]} ${record.period_start} – ${record.period_end}`;
}
