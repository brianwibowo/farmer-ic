import { isDashboardDataPrompt } from '@/services/aiChatService';
import { listActivityLogs } from '@/services/activityLogService';
import { listKebun } from '@/services/kebunService';
import { farmDemoSeedData } from '@/data/farmDemo';
import type { FinanceTransaction } from '@/data/farmerFinance';

export type AiDemoDataAnswer = {
    content: string;
    source: string;
};

export type DemoFinancePeriod = {
    requested: boolean;
    available: boolean;
    year: number | null;
    month: string | null;
    monthLabel: string | null;
    income: number;
    expense: number;
    net: number;
    transactions: FinanceTransaction[];
    label: string;
};

const financeMonthAliases: Record<string, string> = {
    januari: 'Jan',
    jan: 'Jan',
    februari: 'Feb',
    feb: 'Feb',
    maret: 'Mar',
    mar: 'Mar',
    april: 'Apr',
    apr: 'Apr',
    mei: 'Mei',
    juni: 'Jun',
    jun: 'Jun',
    juli: 'Jul',
    jul: 'Jul',
    agustus: 'Agu',
    agu: 'Agu',
    agt: 'Agu',
    september: 'Sep',
    sep: 'Sep',
    oktober: 'Okt',
    okt: 'Okt',
    november: 'Nov',
    nov: 'Nov',
    desember: 'Des',
    des: 'Des',
};

const financeMonthLabels: Record<string, string> = {
    Jan: 'Januari',
    Feb: 'Februari',
    Mar: 'Maret',
    Apr: 'April',
    Mei: 'Mei',
    Jun: 'Juni',
    Jul: 'Juli',
    Agu: 'Agustus',
    Sep: 'September',
    Okt: 'Oktober',
    Nov: 'November',
    Des: 'Desember',
};

const financeMonthPattern = new RegExp(
    `\\b(${Object.keys(financeMonthAliases)
        .sort((left, right) => right.length - left.length)
        .join('|')})\\b`,
    'i',
);

function canonicalFinanceMonth(value: string): string | null {
    const normalized = value.toLocaleLowerCase('id-ID').trim();
    return financeMonthAliases[normalized] ?? null;
}

function sumFinanceTransactions(
    transactions: FinanceTransaction[],
    type: FinanceTransaction['type'],
): number {
    return transactions
        .filter((transaction) => transaction.type === type)
        .reduce((total, transaction) => total + transaction.amount, 0);
}

function availableFinanceScopeLabel(): string {
    const years = [
        ...new Set(
            farmDemoSeedData.finance.transactions.map((transaction) =>
                Number(transaction.date.year),
            ),
        ),
    ]
        .filter((year) => Number.isInteger(year))
        .sort((left, right) => left - right);

    if (years.length === 1) return `Januari–Desember ${years[0]}`;
    if (years.length > 1) return years.join(', ');
    return 'belum tersedia';
}

/**
 * Resolve a month/year against the same bundled transactions used by the
 * finance UI. Returning `available: false` is intentional: callers should
 * show the missing-data state instead of asking a model to estimate values.
 */
export function resolveDemoFinancePeriod(prompt: string): DemoFinancePeriod {
    const value = normalize(prompt);
    const finance = farmDemoSeedData.finance;
    const transactions = finance.transactions;
    const availableYears = [
        ...new Set(
            transactions.map((transaction) => Number(transaction.date.year)),
        ),
    ].filter((year) => Number.isInteger(year));
    const yearMatch = value.match(/\b(20\d{2})\b/);
    const monthMatch = value.match(financeMonthPattern);
    const month = monthMatch ? canonicalFinanceMonth(monthMatch[1]) : null;
    const requested = Boolean(yearMatch || month);
    const year = yearMatch
        ? Number(yearMatch[1])
        : month && availableYears.length === 1
          ? availableYears[0]
          : null;
    const monthLabel = month ? financeMonthLabels[month] : null;
    const label = monthLabel
        ? `${monthLabel} ${year ?? ''}`.trim()
        : year
          ? `Tahun ${year}`
          : availableFinanceScopeLabel();

    if (
        (year !== null && !availableYears.includes(year)) ||
        (requested && year === null)
    ) {
        return {
            requested,
            available: false,
            year,
            month,
            monthLabel,
            income: 0,
            expense: 0,
            net: 0,
            transactions: [],
            label,
        };
    }

    const scopedTransactions = transactions.filter((transaction) => {
        const transactionYear = Number(transaction.date.year);
        if (year !== null && transactionYear !== year) return false;
        return (
            month === null ||
            canonicalFinanceMonth(transaction.date.month) === month
        );
    });

    if (requested && scopedTransactions.length === 0) {
        return {
            requested,
            available: false,
            year,
            month,
            monthLabel,
            income: 0,
            expense: 0,
            net: 0,
            transactions: [],
            label,
        };
    }

    const income = sumFinanceTransactions(scopedTransactions, 'income');
    const expense = sumFinanceTransactions(scopedTransactions, 'expense');
    return {
        requested,
        available: true,
        year,
        month,
        monthLabel,
        income,
        expense,
        net: income - expense,
        transactions: scopedTransactions,
        label,
    };
}

function wait(milliseconds: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

function formatRupiah(value: number): string {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(value);
}

function normalize(value: string): string {
    return value.toLocaleLowerCase("id-ID").replace(/\s+/g, " ").trim();
}

function unavailableFinanceAnswer(period: DemoFinancePeriod): AiDemoDataAnswer {
    return {
        source: 'Data demo keuangan · farmDemoData origin/dev',
        content: [
            '### 💰 Data Keuangan Tidak Tersedia',
            `Data keuangan untuk **${period.label}** tidak tersedia di **farmDemoData.json**.`,
            'Saya tidak menampilkan angka atau membuat ringkasan agar tidak mengarang data.',
            '',
            `> 📌 **Periode tersedia:** ${availableFinanceScopeLabel()}.`,
        ].join('\n'),
    };
}

export async function queryDemoData(
    prompt: string,
): Promise<AiDemoDataAnswer | null> {
    const value = normalize(prompt);
    if (!isDashboardDataPrompt(value)) return null;

    // Keep these patterns deliberately narrow. Agronomy guidance such as
    // "cara mengendalikan hama" or "berapa dosis pupuk" must stay with the
    // LLM instead of being replaced by a dashboard summary.
    const wantsFinance =
        /\b(?:keuangan|finansial|pendapatan|pemasukan|omzet|transaksi|laba|profit|keuntungan|saldo|biaya|pengeluaran)\b/i.test(
            value,
        ) ||
        /\b(?:total|jumlah|rincian|ringkasan|laporan|data)\s+(?:pendapatan|pemasukan|transaksi|biaya|keuangan)\b/i.test(
            value,
        ) ||
        /\b(?:biaya|pengeluaran)\s+(?:operasional|kebun|terakhir|terbaru|tahun|bulan)\b/i.test(
            value,
        );
    const wantsActivity =
        /\b(?:data|laporan|riwayat)\s+(?:aktivitas|kegiatan)\b/i.test(value) ||
        /\b(?:data|laporan|riwayat)\s+(?:panen|pemupukan|pengairan|pestisida|penanaman)\b/i.test(
            value,
        ) ||
        /\b(?:aktivitas|kegiatan)\s+(?:terakhir|terbaru|hari ini|minggu ini)\b/i.test(
            value,
        ) ||
        /\b(?:total|jumlah)\s+(?:aktivitas|kegiatan)\b/i.test(value);
    const wantsGarden =
        /\b(?:data|laporan|ringkasan|daftar|list|tampilkan|sebutkan)\s+(?:kebun|lahan)\b/i.test(
            value,
        ) ||
        /\b(?:kondisi|status|kesehatan)\s+(?:kebun|lahan)\b/i.test(value) ||
        /\b(?:ada\s+)?(?:total|jumlah|berapa)\s+(?:banyak\s+)?(?:kebun|lahan)\b/i.test(
            value,
        ) ||
        /\bkebun\s+(?:apa\s+saja|yang\s+dimiliki|saya|kami)\b/i.test(value);

    if (wantsFinance) {
        await wait(420);
        const quarterMatch = value.match(/\bq\s*([1-4])\b/i);
        const finance = farmDemoSeedData.finance;
        const period = resolveDemoFinancePeriod(value);
        if (!period.available) return unavailableFinanceAnswer(period);

        const quarterKey = quarterMatch
            ? (`Q${quarterMatch[1]}` as keyof typeof finance.quarters)
            : null;
        const quarter = quarterKey ? finance.quarters[quarterKey] : null;
        const quarterYear = period.year ?? 2026;
        const quarterMonths = quarterMatch
            ? new Set(
                  [0, 1, 2].map(
                      (offset) =>
                          (Number(quarterMatch[1]) - 1) * 3 + offset + 1,
                  ),
              )
            : null;
        const quarterTransactions = quarterMonths
            ? finance.transactions.filter(
                  (transaction) =>
                      Number(transaction.date.year) === quarterYear &&
                      quarterMonths.has(
                          Object.keys(financeMonthLabels).indexOf(
                              canonicalFinanceMonth(transaction.date.month) ?? '',
                          ) + 1,
                      ),
              )
            : [];
        if (quarterMatch && quarterTransactions.length === 0) {
            return unavailableFinanceAnswer({
                ...period,
                requested: true,
                available: false,
                year: quarterYear,
                month: null,
                monthLabel: null,
                label: `Q${quarterMatch[1]} ${quarterYear}`,
            });
        }
        const allGardens =
            /\b(?:semua|seluruh|tiap|setiap)\s+(?:kebun|lahan)\b/i.test(value);
        const scopedTransactions = quarterMatch
            ? quarterTransactions
            : period.transactions;
        const income = quarterMatch
            ? sumFinanceTransactions(scopedTransactions, 'income')
            : period.income;
        const expense = quarterMatch
            ? sumFinanceTransactions(scopedTransactions, 'expense')
            : period.expense;
        const latest = scopedTransactions.slice(-3).reverse();
        const financeScope = quarter
            ? `Periode: ${quarter.label}`
            : `Periode: ${period.label}`;
        const scopeLine = allGardens
            ? `Cakupan: seluruh kebun (${farmDemoSeedData.kebun.length} kebun)`
            : "Cakupan: data keuangan yang tersedia";
        const totals = quarter
            ? [
                  `**Pendapatan:** ${formatRupiah(income)}`,
                  `**Pengeluaran:** ${formatRupiah(expense)}`,
                  `**Laba bersih:** ${formatRupiah(income - expense)}`,
              ].join(" | ")
            : [
                  `**Pendapatan:** ${formatRupiah(income)}`,
                  `**Pengeluaran:** ${formatRupiah(expense)}`,
                  `**Laba bersih:** ${formatRupiah(income - expense)}`,
              ].join(' | ');
        return {
            source: 'Data demo keuangan · farmDemoData origin/dev',
            content: [
                "### 💰 Ringkasan Keuangan Kebun",
                `> 📊 ${totals} | ⏳ **${financeScope}**`,
                `> 🌱 **${scopeLine}** | 📌 **Sumber:** Data demo`,
                '',
                ...(quarter
                    ? [
                          "Rincian bulanan:",
                          ...quarter.points.map((point) =>
                              `- **${point.label}:** pendapatan ${formatRupiah(point.revenue)}, pengeluaran ${formatRupiah(point.expense)}, laba ${formatRupiah(point.net)}`,
                          ),
                      ]
                    : [
                          "Transaksi terbaru:",
                          ...latest.map(
                              (item, index) =>
                                  `${index + 1}. **${item.description}**: ${item.type === "expense" ? "Pengeluaran " : ""}${formatRupiah(item.amount)}`,
                          ),
                      ]),
                "",
                "> 💡 **Tips Petani:** Bandingkan pemasukan dan biaya operasional per komoditas sebelum menentukan target panen berikutnya.",
            ].join("\n"),
        };
    }

    if (wantsActivity) {
        const logs = (await listActivityLogs()).slice(0, 3);
        const entries = logs.slice(0, 3);
        return {
            source: 'Data demo aktivitas · farmDemoData origin/dev',
            content: [
                "### 🧾 Aktivitas Kebun Terbaru",
                `> 📌 **Catatan:** ${entries.length} aktivitas | ⏳ **Status:** Tersedia | 📊 **Sumber:** Data demo`,
                "",
                ...entries.map(
                    (item, index) =>
                        `${index + 1}. **${item.typeLabel}**: ${item.kebunName} · ${item.city}`,
                ),
                "",
                "> 💡 **Tips Petani:** Buka rincian aktivitas untuk memeriksa foto, petugas, dan catatan lapangan sebelum mengambil keputusan.",
            ].join("\n"),
        };
    }

    if (wantsGarden) {
        const kebun = await listKebun();
        const specific = kebun.find((item) => {
            const nameLower = item.name.toLowerCase();
            const simpleName = nameLower.replace(/^kebun\s+/, '');
            return value.includes(nameLower) || value.includes(simpleName);
        });

        if (specific) {
            return {
                source: `Data demo kebun · ${specific.name}`,
                content: [
                    `### 🌱 Kondisi ${specific.name}`,
                    `> 🌾 **Komoditas:** ${specific.seedType} (${specific.seedTypeDetail || 'Varietas Unggul'}) | 🩺 **Status:** ${specific.healthStatus}`,
                    `> 📍 **Lokasi:** ${specific.district ? `${specific.district}, ` : ''}${specific.city} | 📐 **Lahan Terpakai:** ${specific.usedHectare} ha / ${specific.availableHectare} ha`,
                    '',
                    `- **Status Tanaman:** ${specific.healthStatus === 'Sehat' ? '✅ Tanaman dalam kondisi sehat dan pertumbuhannya optimal.' : specific.healthStatus === 'Kritis' ? '🚨 Status kritis, butuh tindakan penanganan dan inspeksi segera.' : '⚠️ Perlu perhatian khusus dan pemantauan berkala.'}`,
                    `- **Alamat:** ${specific.address}`,
                    '',
                    '> 💡 **Catatan:** Anda dapat membuka halaman rincian kebun untuk melihat riwayat pemupukan, catatan lapangan, dan dokumentasi foto.',
                ].join('\n'),
            };
        }

        const attention = kebun.filter((item) => item.healthStatus !== 'Sehat');
        return {
            source: 'Data demo kebun · seed prototipe',
            content: [
                "### 🌱 Kondisi Kebun Petani",
                `> 🌾 **Total kebun:** ${kebun.length} | ✅ **Sehat:** ${kebun.length - attention.length} | ⚠️ **Perlu perhatian/Kritis:** ${attention.length}`,
                "",
                ...kebun.map(
                    (item, index) =>
                        `${index + 1}. **${item.name}**: ${item.seedType}${item.seedTypeDetail ? " (" + item.seedTypeDetail + ")" : ""} · ${item.healthStatus} · ${item.city} (${item.usedHectare}/${item.availableHectare} ha)`,
                ),
                "",
                '> 💡 **Tips:** Sebutkan nama kebun tertentu (misal: *"Kondisi kebun Sumber Makmur"*) untuk melihat rincian lokasi dan varietas benihnya.',
            ].join("\n"),
        };
    }

    return null;
}
