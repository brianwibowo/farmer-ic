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

export const farmerFinanceMock: {
    totalRevenue: number;
    totalExpense: number;
    totalNet: number;
    quarters: Record<FinanceQuarter, QuarterFinance>;
    transactions: FinanceTransaction[];
} = {
    totalRevenue: 170_000_000,
    totalExpense: 60_000_000,
    totalNet: 110_000_000,
    quarters: {
        Q1: {
            label: 'Januari–Maret 2026',
            totalRevenue: 42_000_000,
            totalExpense: 15_000_000,
            netTotal: 27_000_000,
            points: [
                {
                    label: 'Jan',
                    revenue: 12_000_000,
                    expense: 4_000_000,
                    net: 8_000_000,
                },
                {
                    label: 'Feb',
                    revenue: 14_000_000,
                    expense: 5_000_000,
                    net: 9_000_000,
                },
                {
                    label: 'Mar',
                    revenue: 16_000_000,
                    expense: 6_000_000,
                    net: 10_000_000,
                },
            ],
            analysis:
                'Pendapatan naik stabil sepanjang kuartal. Penjualan Maret menjadi kontributor terbesar berkat panen jagung dan kedelai.',
        },
        Q2: {
            label: 'April–Juni 2026',
            totalRevenue: 38_000_000,
            totalExpense: 14_000_000,
            netTotal: 24_000_000,
            points: [
                {
                    label: 'Apr',
                    revenue: 11_000_000,
                    expense: 4_000_000,
                    net: 7_000_000,
                },
                {
                    label: 'Mei',
                    revenue: 13_000_000,
                    expense: 5_000_000,
                    net: 8_000_000,
                },
                {
                    label: 'Jun',
                    revenue: 14_000_000,
                    expense: 5_000_000,
                    net: 9_000_000,
                },
            ],
            analysis:
                'Pendapatan tetap positif meski sedikit melambat. Pengaturan jadwal tanam jagung dan kedelai dapat membantu menjaga volume panen.',
        },
        Q3: {
            label: 'Juli–September 2026',
            totalRevenue: 51_000_000,
            totalExpense: 17_000_000,
            netTotal: 34_000_000,
            points: [
                {
                    label: 'Jul',
                    revenue: 16_000_000,
                    expense: 5_000_000,
                    net: 11_000_000,
                },
                {
                    label: 'Agu',
                    revenue: 17_000_000,
                    expense: 6_000_000,
                    net: 11_000_000,
                },
                {
                    label: 'Sep',
                    revenue: 18_000_000,
                    expense: 6_000_000,
                    net: 12_000_000,
                },
            ],
            analysis:
                'Ini kuartal terbaik tahun berjalan. Produktivitas dan harga jual meningkat, terutama dari jagung dan rumput odot.',
        },
        Q4: {
            label: 'Oktober–Desember 2026',
            totalRevenue: 39_000_000,
            totalExpense: 14_000_000,
            netTotal: 25_000_000,
            points: [
                {
                    label: 'Okt',
                    revenue: 12_000_000,
                    expense: 4_000_000,
                    net: 8_000_000,
                },
                {
                    label: 'Nov',
                    revenue: 13_000_000,
                    expense: 5_000_000,
                    net: 8_000_000,
                },
                {
                    label: 'Des',
                    revenue: 14_000_000,
                    expense: 5_000_000,
                    net: 9_000_000,
                },
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
            amount: 12_000_000,
            type: 'income',
        },
        {
            id: 2,
            description: 'Pembelian benih dan pupuk jagung',
            category: 'Operasional',
            commodity: 'Jagung',
            date: { day: '12', month: 'Jan', year: '2026' },
            amount: 4_000_000,
            type: 'expense',
        },
        {
            id: 3,
            description: 'Penjualan rumput odot ke peternak',
            category: 'Penjualan panen',
            commodity: 'Rumput odot',
            date: { day: '16', month: 'Feb', year: '2026' },
            amount: 14_000_000,
            type: 'income',
        },
        {
            id: 4,
            description: 'Pemeliharaan dan pengairan lahan',
            category: 'Pemeliharaan',
            commodity: 'Rumput odot',
            date: { day: '18', month: 'Feb', year: '2026' },
            amount: 5_000_000,
            type: 'expense',
        },
        {
            id: 5,
            description: 'Penjualan kedelai hasil panen',
            category: 'Penjualan panen',
            commodity: 'Kedelai',
            date: { day: '14', month: 'Mar', year: '2026' },
            amount: 16_000_000,
            type: 'income',
        },
        {
            id: 6,
            description: 'Perawatan lahan kedelai',
            category: 'Pemeliharaan',
            commodity: 'Kedelai',
            date: { day: '20', month: 'Mar', year: '2026' },
            amount: 6_000_000,
            type: 'expense',
        },
        {
            id: 7,
            description: 'Penjualan jagung pipil kering',
            category: 'Penjualan panen',
            commodity: 'Jagung',
            date: { day: '12', month: 'Apr', year: '2026' },
            amount: 11_000_000,
            type: 'income',
        },
        {
            id: 8,
            description: 'Pembelian pupuk susulan jagung',
            category: 'Operasional',
            commodity: 'Jagung',
            date: { day: '15', month: 'Apr', year: '2026' },
            amount: 4_000_000,
            type: 'expense',
        },
        {
            id: 9,
            description: 'Penjualan rumput odot ke peternak',
            category: 'Penjualan panen',
            commodity: 'Rumput odot',
            date: { day: '19', month: 'Mei', year: '2026' },
            amount: 13_000_000,
            type: 'income',
        },
        {
            id: 10,
            description: 'Perawatan saluran irigasi',
            category: 'Pemeliharaan',
            commodity: 'Rumput odot',
            date: { day: '22', month: 'Mei', year: '2026' },
            amount: 5_000_000,
            type: 'expense',
        },
        {
            id: 11,
            description: 'Penjualan kedelai ke mitra pangan',
            category: 'Penjualan panen',
            commodity: 'Kedelai',
            date: { day: '16', month: 'Jun', year: '2026' },
            amount: 14_000_000,
            type: 'income',
        },
        {
            id: 12,
            description: 'Pengendalian hama kedelai',
            category: 'Pemeliharaan',
            commodity: 'Kedelai',
            date: { day: '20', month: 'Jun', year: '2026' },
            amount: 5_000_000,
            type: 'expense',
        },
        {
            id: 13,
            description: 'Penjualan jagung pipil kering',
            category: 'Penjualan panen',
            commodity: 'Jagung',
            date: { day: '12', month: 'Jul', year: '2026' },
            amount: 16_000_000,
            type: 'income',
        },
        {
            id: 14,
            description: 'Pemupukan lahan jagung',
            category: 'Operasional',
            commodity: 'Jagung',
            date: { day: '18', month: 'Jul', year: '2026' },
            amount: 5_000_000,
            type: 'expense',
        },
        {
            id: 15,
            description: 'Penjualan rumput odot kontrak peternak',
            category: 'Penjualan panen',
            commodity: 'Rumput odot',
            date: { day: '17', month: 'Agu', year: '2026' },
            amount: 17_000_000,
            type: 'income',
        },
        {
            id: 16,
            description: 'Perawatan dan pengairan rumput',
            category: 'Pemeliharaan',
            commodity: 'Rumput odot',
            date: { day: '21', month: 'Agu', year: '2026' },
            amount: 6_000_000,
            type: 'expense',
        },
        {
            id: 17,
            description: 'Penjualan kedelai kualitas premium',
            category: 'Penjualan panen',
            commodity: 'Kedelai',
            date: { day: '18', month: 'Sep', year: '2026' },
            amount: 18_000_000,
            type: 'income',
        },
        {
            id: 18,
            description: 'Penyortiran dan perawatan kedelai',
            category: 'Operasional',
            commodity: 'Kedelai',
            date: { day: '23', month: 'Sep', year: '2026' },
            amount: 6_000_000,
            type: 'expense',
        },
        {
            id: 19,
            description: 'Penjualan jagung ke koperasi daerah',
            category: 'Penjualan panen',
            commodity: 'Jagung',
            date: { day: '11', month: 'Okt', year: '2026' },
            amount: 12_000_000,
            type: 'income',
        },
        {
            id: 20,
            description: 'Pembelian benih musim berikutnya',
            category: 'Operasional',
            commodity: 'Jagung',
            date: { day: '16', month: 'Okt', year: '2026' },
            amount: 4_000_000,
            type: 'expense',
        },
        {
            id: 21,
            description: 'Penjualan rumput odot ke peternak',
            category: 'Penjualan panen',
            commodity: 'Rumput odot',
            date: { day: '15', month: 'Nov', year: '2026' },
            amount: 13_000_000,
            type: 'income',
        },
        {
            id: 22,
            description: 'Perawatan lahan dan irigasi',
            category: 'Pemeliharaan',
            commodity: 'Rumput odot',
            date: { day: '20', month: 'Nov', year: '2026' },
            amount: 5_000_000,
            type: 'expense',
        },
        {
            id: 23,
            description: 'Penjualan kedelai ke mitra pangan',
            category: 'Penjualan panen',
            commodity: 'Kedelai',
            date: { day: '12', month: 'Des', year: '2026' },
            amount: 14_000_000,
            type: 'income',
        },
        {
            id: 24,
            description: 'Persiapan lahan dan pemupukan',
            category: 'Operasional',
            commodity: 'Kedelai',
            date: { day: '18', month: 'Des', year: '2026' },
            amount: 5_000_000,
            type: 'expense',
        },
    ],
};
