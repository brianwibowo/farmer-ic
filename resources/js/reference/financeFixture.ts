import data from './fixtureData';
import type { FinancePeriod } from './types/finance';

// Adapt the exact historical fixture to Finance.vue's historical server-prop shape.
// Do not call the current finance endpoint or claim this is database data.
const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const transactions = data.finance.transactions.map((transaction) => ({
    ...transaction,
    id: String(transaction.id),
    farm_id: transaction.kebunId,
    farm_name: data.kebun.find((farm) => farm.id === transaction.kebunId)?.name ?? null,
    transaction_date: `${transaction.date.year}-${String(months.indexOf(transaction.date.month) + 1).padStart(2, '0')}-${transaction.date.day}`,
    amount: String(transaction.amount),
    type: transaction.type as 'income' | 'expense',
}));
const points = Object.values(data.finance.quarters).flatMap((quarter) => quarter.points);

export const referenceFinanceProps = {
    summary: {
        source: 'historical-fixture',
        currency: 'IDR',
        income: String(data.finance.totalRevenue),
        expense: String(data.finance.totalExpense),
        net: String(data.finance.totalNet),
        transaction_count: transactions.length,
        monthly: points.map((point, index) => ({
            month: `2026-${String(index + 1).padStart(2, '0')}`,
            income: String(point.revenue), expense: String(point.expense), net: String(point.net),
        })),
    },
    transactions: { data: transactions, total: transactions.length, current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null },
    farms: data.kebun.map((farm) => ({ id: farm.id, name: farm.name, city: farm.city })),
    commodities: [...new Set(transactions.map((transaction) => transaction.commodity))],
    filters: { year: 2026, period: 'YTD' as FinancePeriod, tab: 'chart' as 'chart' | 'transactions', farm_id: null as string | null, commodity: null as string | null, transaction_type: null as 'income' | 'expense' | null, search: '' },
    periodLabel: 'Januari–Desember 2026 · fixture historis',
    exportUrl: '/referensi/finance',
};
