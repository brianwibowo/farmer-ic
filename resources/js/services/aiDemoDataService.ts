import { activityLogSeedData } from '@/data/activityLogs';
import { farmerFinanceMock } from '@/mocks/farmerFinance';
import { listActivityLogs } from '@/services/activityLogService';
import { listKebun } from '@/services/kebunService';

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

export async function queryDemoData(prompt: string): Promise<AiDemoDataAnswer | null> {
    const value = normalize(prompt);
    const wantsFinance = /keuangan|pendapatan|pemasukan|transaksi|biaya|omzet|revenue|harga|penjualan|jual/.test(value);
    const wantsActivity = /aktivitas|kegiatan|riwayat|pemupukan|pengairan|hama|panen|laporan/.test(value);
    const wantsGarden = /kebun|lahan|tanaman|kondisi|status|kesehatan|komoditas|petak/.test(value);

    if (wantsFinance) {
        await wait(420);
        const latest = farmerFinanceMock.transactions.slice(-3).reverse();
        return {
            source: 'Data demo keuangan · diperbarui 18 Okt 2026',
            content: [
                '### 💰 Ringkasan Keuangan Kebun',
                `> 📊 **Pendapatan:** ${formatRupiah(farmerFinanceMock.totalRevenue)} | ⏳ **Periode:** 2026 | 📌 **Sumber:** Data demo`,
                '',
                'Transaksi terbaru:',
                ...latest.map((item, index) => `${index + 1}. **${item.description}**: ${formatRupiah(item.amount)}`),
                '',
                '> 💡 **Tips Petani:** Bandingkan pemasukan dan biaya operasional per komoditas sebelum menentukan target panen berikutnya.',
            ].join('\n'),
        };
    }

    if (wantsActivity) {
        const logs = (await listActivityLogs()).slice(0, 3);
        const entries = logs.length ? logs : activityLogSeedData.slice(0, 3);
        return {
            source: 'Data demo aktivitas · diperbarui 18 Okt 2026',
            content: [
                '### 🧾 Aktivitas Kebun Terbaru',
                `> 📌 **Catatan:** ${entries.length} aktivitas | ⏳ **Status:** Tersedia | 📊 **Sumber:** Data demo`,
                '',
                ...entries.map((item, index) => `${index + 1}. **${item.typeLabel}**: ${item.kebunName} · ${item.city}`),
                '',
                '> 💡 **Tips Petani:** Buka rincian aktivitas untuk memeriksa foto, petugas, dan catatan lapangan sebelum mengambil keputusan.',
            ].join('\n'),
        };
    }

    if (wantsGarden) {
        const kebun = await listKebun();
        const attention = kebun.filter((item) => item.healthStatus !== 'Sehat');
        return {
            source: 'Data demo kebun · diperbarui 18 Okt 2026',
            content: [
                '### 🌱 Kondisi Kebun',
                `> 🌾 **Total kebun:** ${kebun.length} | ✅ **Sehat:** ${kebun.length - attention.length} | ⚠️ **Perlu perhatian:** ${attention.length}`,
                '',
                ...kebun.slice(0, 4).map((item, index) => `${index + 1}. **${item.name}**: ${item.seedType} · ${item.healthStatus} · ${item.city}`),
                '',
                '> 💡 **Tips Petani:** Prioritaskan pemeriksaan Kebun Harapan Jaya dan kebun dengan status Perlu Perhatian.',
            ].join('\n'),
        };
    }

    return null;
}
