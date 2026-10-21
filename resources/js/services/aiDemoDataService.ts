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
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(value);
}

function normalize(value: string): string {
    return value.toLocaleLowerCase("id-ID").replace(/\s+/g, " ").trim();
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
        const quarterKey = quarterMatch
            ? (`Q${quarterMatch[1]}` as keyof typeof finance.quarters)
            : null;
        const quarter = quarterKey ? finance.quarters[quarterKey] : null;
        const allGardens =
            /\b(?:semua|seluruh|tiap|setiap)\s+(?:kebun|lahan)\b/i.test(value);
        const latest = finance.transactions.slice(-3).reverse();
        const financeScope = quarter
            ? `Periode: ${quarter.label}`
            : "Periode: seluruh data demo 2026";
        const scopeLine = allGardens
            ? `Cakupan: seluruh kebun (${farmDemoSeedData.kebun.length} kebun)`
            : "Cakupan: data keuangan yang tersedia";
        const totals = quarter
            ? [
                  `**Pendapatan:** ${formatRupiah(quarter.totalRevenue)}`,
                  `**Pengeluaran:** ${formatRupiah(quarter.totalExpense)}`,
                  `**Laba bersih:** ${formatRupiah(quarter.netTotal)}`,
              ].join(" | ")
            : [
                  `**Pendapatan:** ${formatRupiah(finance.totalRevenue)}`,
                  `**Pengeluaran:** ${formatRupiah(finance.totalExpense)}`,
                  `**Laba bersih:** ${formatRupiah(finance.totalNet)}`,
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
                          ...quarter.points.map(
                              (point) =>
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
