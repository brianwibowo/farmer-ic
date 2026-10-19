import { activityLogSeedData } from "@/data/activityLogs";
import { farmerFinanceMock } from "@/data/farmerFinance";
import { isDashboardDataPrompt } from "@/services/aiChatService";
import { listActivityLogs } from "@/services/activityLogService";
import { listKebun } from "@/services/kebunService";
import { farmDemoSeedData } from "@/data/farmDemo";

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

function updatedLabel(): string {
    return new Intl.DateTimeFormat("id-ID", {
        day: "numeric",
        month: "short",
        year: "numeric",
    }).format(new Date());
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
        const asksCurrentMonth =
            /\b(?:bulan ini|bulan sekarang|periode ini|oktober|saat ini)\b/i.test(value);
        const quarterKey = quarterMatch
            ? (`Q${quarterMatch[1]}` as keyof typeof farmerFinanceMock.quarters)
            : asksCurrentMonth
              ? "Q4"
              : null;
        const quarter = quarterKey
            ? farmerFinanceMock.quarters[quarterKey]
            : null;
        const allGardens =
            /\b(?:semua|seluruh|tiap|setiap)\s+(?:kebun|lahan)\b/i.test(value);
        const latest = farmerFinanceMock.transactions.slice(-3).reverse();
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
                  `**Pendapatan:** ${formatRupiah(farmerFinanceMock.totalRevenue)}`,
                  `**Pengeluaran:** ${formatRupiah(farmerFinanceMock.totalExpense)}`,
                  `**Laba bersih:** ${formatRupiah(farmerFinanceMock.totalNet)}`,
              ].join(" | ");
        const currentMonthPoint =
            asksCurrentMonth && quarter
                ? (quarter.points.find((p) => p.label === "Okt") ??
                  quarter.points[0])
                : null;
        return {
            source: `Data demo keuangan · diperbarui ${updatedLabel()}`,
            content: [
                "### 💰 Ringkasan Keuangan Kebun",
                `> 📊 ${totals} | ⏳ **${financeScope}**`,
                `> 🌱 **${scopeLine}** | 📌 **Sumber:** Data demo`,
                ...(currentMonthPoint
                    ? [
                          `> 📅 **Bulan ini (${currentMonthPoint.label}):** pendapatan ${formatRupiah(currentMonthPoint.revenue)} | pengeluaran ${formatRupiah(currentMonthPoint.expense)} | laba ${formatRupiah(currentMonthPoint.net)}`,
                      ]
                    : []),
                "",
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
        const entries = logs.length ? logs : activityLogSeedData.slice(0, 3);
        return {
            source: `Data demo aktivitas · diperbarui ${updatedLabel()}`,
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
        const specificGarden = kebun.find(
            (item) =>
                value.includes(item.name.toLowerCase()) ||
                value.includes(
                    item.name.replace(/^kebun\s+/i, "").toLowerCase(),
                ),
        );

        if (specificGarden) {
            return {
                source: `Data demo kebun (${specificGarden.name}) · diperbarui ${updatedLabel()}`,
                content: [
                    `### 🌱 ${specificGarden.name}`,
                    `> 📍 **Lokasi:** ${specificGarden.city}${specificGarden.district ? ", Kec. " + specificGarden.district : ""} | 🌾 **Komoditas:** ${specificGarden.seedType}${specificGarden.seedTypeDetail ? " (" + specificGarden.seedTypeDetail + ")" : ""}`,
                    `> 📐 **Luas Lahan:** ${specificGarden.usedHectare} ha (dari ${specificGarden.availableHectare} ha) | 🩺 **Kondisi:** ${specificGarden.healthStatus}`,
                    "",
                    `- **Status Kesehatan:** ${specificGarden.healthStatus}`,
                    `- **Alamat Lengkap:** ${specificGarden.address || "-"}`,
                    "",
                    "> 💡 **Catatan:** Anda dapat membuka halaman Detail Kebun untuk melihat analisa fase pertumbuhan dan rekomendasi AI selengkapnya.",
                ].join("\n"),
            };
        }

        const attention = kebun.filter((item) => item.healthStatus !== "Sehat");
        return {
            source: `Data demo kebun · diperbarui ${updatedLabel()}`,
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

/**
 * Selective context extraction (Tier 3 Best Practice):
 * Hanya mengekstrak 1 baris micro-context jika prompt menyebutkan nama kebun spesifik.
 * Biaya: ~15-25 token (hemat 98% dibanding mengirim seluruh database).
 */
export async function extractTargetedKebunContext(
    prompt: string,
): Promise<string | null> {
    const value = normalize(prompt);
    try {
        const kebun = await listKebun();
        const matched = kebun.find(
            (item) =>
                value.includes(item.name.toLowerCase()) ||
                value.includes(
                    item.name.replace(/^kebun\s+/i, "").toLowerCase(),
                ),
        );

        if (matched) {
            return `Kebun ${matched.name} di ${matched.city} (Komoditas: ${matched.seedType}${matched.seedTypeDetail ? " " + matched.seedTypeDetail : ""}, Luas: ${matched.usedHectare}/${matched.availableHectare} ha, Status: ${matched.healthStatus})`;
        }
    } catch {
        return null;
    }

    return null;
}

/**
 * Membangun konteks lengkap seluruh data kebun, keuangan, dan aktivitas petani.
 * Dikirim langsung bersama setiap prompt pengguna sehingga LLM selalu memiliki data faktual terbaru.
 */
export async function buildFullFarmContext(): Promise<string> {
    const lines: string[] = [];

    // 1. Data Kebun
    try {
        const kebunList = await listKebun();
        if (kebunList.length > 0) {
            lines.push(`### Data Kebun Aktif (${kebunList.length} Kebun):`);
            kebunList.forEach((item, index) => {
                const loc = [
                    item.city,
                    item.district ? `Kec. ${item.district}` : "",
                ]
                    .filter(Boolean)
                    .join(", ");
                lines.push(
                    `${index + 1}. **${item.name}** (${loc}): Komoditas ${item.seedType}${item.seedTypeDetail ? " " + item.seedTypeDetail : ""}, Luas lahan ${item.usedHectare}/${item.availableHectare} ha, Status kesehatan: ${item.healthStatus}.`,
                );
            });
        }
    } catch {
        // Fallback
    }

    // 2. Data Keuangan
    try {
        const fin = farmerFinanceMock;
        lines.push("\n### Data Keuangan Kebun Tahun 2026:");
        lines.push(
            `- **Total Pendapatan Kumulatif 2026**: ${formatRupiah(fin.totalRevenue)}`,
        );
        lines.push(
            `- **Total Pengeluaran (OPEX) 2026**: ${formatRupiah(fin.totalExpense)}`,
        );
        lines.push(
            `- **Laba Bersih Kumulatif 2026**: ${formatRupiah(fin.totalNet)}`,
        );

        lines.push("- **Rincian Periode Kuartal**:");
        for (const [key, q] of Object.entries(fin.quarters)) {
            lines.push(
                `  * **${key} (${q.label})**: Pendapatan ${formatRupiah(q.totalRevenue)}, Pengeluaran ${formatRupiah(q.totalExpense)}, Laba Bersih ${formatRupiah(q.netTotal)}.`,
            );
            if (key === "Q4") {
                for (const pt of q.points) {
                    lines.push(
                        `    - Bulan ${pt.label} 2026: Pendapatan ${formatRupiah(pt.revenue)}, Pengeluaran ${formatRupiah(pt.expense)}, Laba ${formatRupiah(pt.net)}`,
                    );
                }
            }
        }

        const latestTx = fin.transactions.slice(-6).reverse();
        lines.push("- **Catatan Transaksi Keuangan Terbaru**:");
        latestTx.forEach((tx) => {
            lines.push(
                `  * [${tx.date.day} ${tx.date.month} ${tx.date.year}] ${tx.description} (${tx.commodity}) - ${tx.type === "expense" ? "Pengeluaran" : "Pemasukan"}: ${formatRupiah(tx.amount)}`,
            );
        });
    } catch {
        // Fallback
    }

    // 3. Data Aktivitas Lapangan Terbaru
    try {
        const logs = await listActivityLogs();
        const recentLogs = (logs.length ? logs : activityLogSeedData).slice(
            0,
            4,
        );
        if (recentLogs.length > 0) {
            lines.push("\n### Data Aktivitas Lapangan Terbaru:");
            recentLogs.forEach((act) => {
                lines.push(
                    `- [${act.timestamp}] **${act.typeLabel}** di ${act.kebunName}`,
                );
            });
        }
    } catch {
        // Fallback
    }

    return lines.join("\n");
}
