export type FinanceResource =
    | 'transactions'
    | 'categories'
    | 'budgets'
    | 'projections';
export type FinanceRecord = {
    id: string;
    name?: string;
    description?: string | null;
    farm_id?: string | null;
    category_id?: string | null;
    planting_cycle_id?: string | null;
    transaction_date?: string;
    transaction_type?: 'income' | 'expense';
    amount?: string;
    period_start?: string;
    period_end?: string;
    budget_amount?: string;
    projected_income?: string | null;
    projected_expense?: string | null;
    calculated_at?: string;
    created_at?: string | null;
    recorded_by?: string;
    created_by?: string;
    can?: { update: boolean; delete: boolean };
};
export type FinanceOptions = {
    farms: Array<{ id: string; name: string }>;
    categories: Array<{
        id: string;
        name: string;
        transaction_type: 'income' | 'expense';
    }>;
    cycles: Array<{ id: string; farm_id: string; label: string }>;
};
export type FinancePeriod = 'Q1' | 'Q2' | 'Q3' | 'Q4' | 'YTD';
export type FinanceSummary = {
    source: 'database';
    currency: 'IDR';
    income: string;
    expense: string;
    net: string;
    transaction_count: number;
    monthly: Array<{
        month: string;
        income: string;
        expense: string;
        net: string;
    }>;
};
export type FinancePaginator<T> = {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
