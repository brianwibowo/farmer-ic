import farmDemoData from '@/data/farmDemoData.json';

export type FinanceQuarter = 'Q1' | 'Q2' | 'Q3' | 'Q4';

export type RevenuePoint = {
    label: string;
    revenue: number;
    expense: number;
    net: number;
};

export type QuarterFinance = {
    label: string;
    totalRevenue: number;
    totalExpense: number;
    netTotal: number;
    points: RevenuePoint[];
    analysis: string;
};

export type FinanceCommodity = 'Jagung' | 'Rumput odot' | 'Kedelai';

export type TransactionDate = {
    day: string;
    month: string;
    year: string;
};

export type FinanceTransaction = {
    id: number;
    description: string;
    category: string;
    commodity: FinanceCommodity;
    date: TransactionDate;
    amount: number;
    type: 'income' | 'expense';
};

export type FarmerFinance = {
    totalRevenue: number;
    totalExpense: number;
    totalNet: number;
    quarters: Record<FinanceQuarter, QuarterFinance>;
    transactions: FinanceTransaction[];
};

/** Single finance seed consumed by the demo storage layer. */
export const farmerFinanceMock = farmDemoData.finance as FarmerFinance;
