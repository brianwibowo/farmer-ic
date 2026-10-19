import { activityLogSeedData } from '@/data/activityLogs';
import { farmerFinanceMock } from '@/data/farmerFinance';
import { isDashboardDataPrompt } from '@/services/aiChatService';
import { listActivityLogs } from '@/services/activityLogService';
import { listKebun } from '@/services/kebunService';
import { farmDemoSeedData } from '@/data/farmDemo';

export type AiDemoDataAnswer = {
    content: string;
    source: string;
};

function wait(milliseconds: number): Promise<void> {
    return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
}

function formatRupiah(value: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);
}

function normalize(value: string): string {
    return value.toLocaleLowerCase('id-ID').replace(/\s+/g, ' ').trim();
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
        /\b(?:keuangan|pendapatan|pemasukan|omzet|transaksi)\b/i.test(value) ||
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
        /\b(?:data|laporan|ringkasan)\s+(?:kebun|lahan)\b/i.test(value) ||
        /\b(?:kondisi|status|kesehatan)\s+(?:kebun|lahan)\b/i.test(value) ||
        /\b(?:total|jumlah)\s+(?:kebun|lahan)\b/i.test(value);

    if (wantsFinance) {
        await wait(420);
        const quarterMatch = value.match(/\bq\s*([1-4])\b/i);
        const quarterKey = quarterMatch
            ? (`Q${quarterMatch[1]}` as keyof typeof farmerFinanceMock.quarters)
            : null;
        const quarter = quarterKey
            ? farmerFinanceMock.quarters[quarterKey]
            : null;
        const allGardens =
            /\b(?:semua|seluruh|tiap|setiap)\s+(?:kebun|lahan)\b/i.test(value);
        const latest = farmerFinanceMock.transactions.slice(-3).reverse();
        const financeScope = quarter
            ? `Periode: ${quarter.label}`
            : 'Periode: seluruh data demo 2026';
        const scopeLine = allGardens
            ? `Cakupan: seluruh kebun (${farmDemoSeedData.kebun.length} kebun)`
            : 'Cakupan: data keuangan yang tersedia';
        const totals = quarter
            ? [
                  `**Pendapatan:** ${formatRupiah(quarter.totalRevenue)}`,
                  `**Pengeluaran:** ${formatRupiah(quarter.totalExpense)}`,
                  `**Laba bersih:** ${formatRupiah(quarter.netTotal)}`,
              ].join(' | ')
            : [
                  `**Pendapatan:** ${formatRupiah(farmerFinanceMock.totalRevenue)}`,
                  `**Pengeluaran:** ${formatRupiah(farmerFinanceMock.totalExpense)}`,
                  `**Laba bersih:** ${formatRupiah(farmerFinanceMock.totalNet)}`,
              ].join(' | ');
        return {
            source: 'Data demo keuangan · seed prototipe',
            content: [
                '### 💰 Ringkasan Keuangan Kebun',
                `> 📊 ${totals} | ⏳ **${financeScope}**`,
                `> 🌱 **${scopeLine}** | 📌 **Sumber:** Data demo`,
                '',
                ...(quarter
                    ? [
                          'Rincian bulanan:',
                          ...quarter.points.map(
                              (point) =>
                                  `- **${point.label}:** pendapatan ${formatRupiah(point.revenue)}, pengeluaran ${formatRupiah(point.expense)}, laba ${formatRupiah(point.net)}`,
                          ),
                      ]
                    : [
                          'Transaksi terbaru:',
                          ...latest.map(
                              (item, index) =>
                                  `${index + 1}. **${item.description}**: ${item.type === 'expense' ? 'Pengeluaran ' : ''}${formatRupiah(item.amount)}`,
                          ),
                      ]),
                '',
                '> 💡 **Tips Petani:** Bandingkan pemasukan dan biaya operasional per komoditas sebelum menentukan target panen berikutnya.',
            ].join('\n'),
        };
    }

    if (wantsActivity) {
        const logs = (await listActivityLogs()).slice(0, 3);
        const entries = logs.length ? logs : activityLogSeedData.slice(0, 3);
        return {
            source: 'Data demo aktivitas · seed prototipe',
            content: [
                '### 🧾 Aktivitas Kebun Terbaru',
                `> 📌 **Catatan:** ${entries.length} aktivitas | ⏳ **Status:** Tersedia | 📊 **Sumber:** Data demo`,
                '',
                ...entries.map(
                    (item, index) =>
                        `${index + 1}. **${item.typeLabel}**: ${item.kebunName} · ${item.city}`,
                ),
                '',
                '> 💡 **Tips Petani:** Buka rincian aktivitas untuk memeriksa foto, petugas, dan catatan lapangan sebelum mengambil keputusan.',
            ].join('\n'),
        };
    }

    if (wantsGarden) {
        const kebun = await listKebun();
        const attention = kebun.filter((item) => item.healthStatus !== 'Sehat');
        return {
            source: 'Data demo kebun · seed prototipe',
            content: [
                '### 🌱 Kondisi Kebun',
                `> 🌾 **Total kebun:** ${kebun.length} | ✅ **Sehat:** ${kebun.length - attention.length} | ⚠️ **Perlu perhatian:** ${attention.length}`,
                '',
                ...kebun.map(
                    (item, index) =>
                        `${index + 1}. **${item.name}**: ${item.seedType} · ${item.healthStatus} · ${item.city}`,
                ),
                '',
                '> 💡 **Catatan:** Status Perlu Perhatian atau Kritis sebaiknya diverifikasi melalui rincian kebun dan catatan lapangan terbaru.',
            ].join('\n'),
        };
    }

    return null;
}
