export type FinanceQuarter = 'Q1' | 'Q2' | 'Q3' | 'Q4';

export type RevenuePoint = {
    label: string;
    amount: number;
};

export type QuarterFinance = {
    label: string;
    total: number;
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

export const farmerFinanceMock: {
    totalRevenue: number;
    quarters: Record<FinanceQuarter, QuarterFinance>;
    transactions: FinanceTransaction[];
} = {
    totalRevenue: 170_000_000,
    quarters: {
        Q1: {
            label: 'Januari–Maret 2026',
            total: 42_000_000,
            points: [
                { label: 'Jan', amount: 12_000_000 },
                { label: 'Feb', amount: 14_000_000 },
                { label: 'Mar', amount: 16_000_000 },
            ],
            analysis:
                'Pendapatan naik stabil sepanjang kuartal. Penjualan Maret menjadi kontributor terbesar berkat panen jagung dan kedelai.',
        },
        Q2: {
            label: 'April–Juni 2026',
            total: 38_000_000,
            points: [
                { label: 'Apr', amount: 11_000_000 },
                { label: 'Mei', amount: 13_000_000 },
                { label: 'Jun', amount: 14_000_000 },
            ],
            analysis:
                'Pendapatan tetap positif meski sedikit melambat. Pengaturan jadwal tanam jagung dan kedelai dapat membantu menjaga volume panen.',
        },
        Q3: {
            label: 'Juli–September 2026',
            total: 51_000_000,
            points: [
                { label: 'Jul', amount: 16_000_000 },
                { label: 'Agu', amount: 17_000_000 },
                { label: 'Sep', amount: 18_000_000 },
            ],
            analysis:
                'Ini kuartal terbaik tahun berjalan. Produktivitas dan harga jual meningkat, terutama dari jagung dan rumput odot.',
        },
        Q4: {
            label: 'Oktober–Desember 2026',
            total: 39_000_000,
            points: [
                { label: 'Okt', amount: 12_000_000 },
                { label: 'Nov', amount: 13_000_000 },
                { label: 'Des', amount: 14_000_000 },
            ],
            analysis:
                'Pendapatan diproyeksikan tumbuh bertahap. Fokus pada stok rumput odot dan kontrak pembelian dapat mengurangi risiko penurunan harga.',
        },
    },
    transactions: [
        {
            id: 1,
            description: 'Penjualan jagung ke koperasi daerah',
            category: 'Penjualan panen',
            commodity: 'Jagung',
            date: { day: '10', month: 'Jan', year: '2026' },
            amount: 20_000_000,
            type: 'income',
        },
        {
            id: 2,
            description: 'Penjualan rumput odot ke peternak',
            category: 'Penjualan panen',
            commodity: 'Rumput odot',
            date: { day: '18', month: 'Jan', year: '2026' },
            amount: 12_500_000,
            type: 'income',
        },
        {
            id: 3,
            description: 'Penjualan kedelai hasil panen',
            category: 'Penjualan panen',
            commodity: 'Kedelai',
            date: { day: '02', month: 'Feb', year: '2026' },
            amount: 17_500_000,
            type: 'income',
        },
        {
            id: 4,
            description: 'Pembelian benih jagung unggul',
            category: 'Operasional',
            commodity: 'Jagung',
            date: { day: '10', month: 'Feb', year: '2026' },
            amount: 2_250_000,
            type: 'expense',
        },
        {
            id: 5,
            description: 'Penjualan rumput odot ke peternak',
            category: 'Penjualan panen',
            commodity: 'Rumput odot',
            date: { day: '21', month: 'Feb', year: '2026' },
            amount: 15_000_000,
            type: 'income',
        },
        {
            id: 6,
            description: 'Penjualan jagung pipil kering',
            category: 'Penjualan panen',
            commodity: 'Jagung',
            date: { day: '12', month: 'Mar', year: '2026' },
            amount: 22_000_000,
            type: 'income',
        },
        {
            id: 7,
            description: 'Perawatan lahan kedelai',
            category: 'Pemeliharaan',
            commodity: 'Kedelai',
            date: { day: '20', month: 'Mar', year: '2026' },
            amount: 1_750_000,
            type: 'expense',
        },
        {
            id: 8,
            description: 'Penjualan kedelai ke mitra pangan',
            category: 'Penjualan panen',
            commodity: 'Kedelai',
            date: { day: '28', month: 'Mar', year: '2026' },
            amount: 19_000_000,
            type: 'income',
        },
    ],
};
