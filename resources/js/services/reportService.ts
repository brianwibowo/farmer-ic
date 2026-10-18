import {
    readDemoStorage,
    writeDemoStorage,
} from '@/composables/useDemoStorage';
import {
    reportSeedData,
    type ReportCommodity,
    type ReportData,
    type ReportPeriod,
} from '@/data/report';

const storageKey = 'report_v1';

function wait(milliseconds: number): Promise<void> {
    return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
}

export async function getReportData(
    _period: ReportPeriod = 'minggu',
    commodity: ReportCommodity = 'Semua',
): Promise<ReportData> {
    await wait(460);

    const stored = readDemoStorage<ReportData>(storageKey, reportSeedData);

    // Tambahkan bagian data baru ketika browser masih menyimpan demo report
    // dari versi sebelum tampilan bulanan tersedia.
    if (!Array.isArray(stored.data.monthly_commodity_reports)) {
        stored.data.monthly_commodity_reports = structuredClone(
            reportSeedData.data.monthly_commodity_reports,
        );
        writeDemoStorage(storageKey, stored);
    }
    if (!stored.data.quarterly_report) {
        stored.data.quarterly_report = structuredClone(
            reportSeedData.data.quarterly_report,
        );
        writeDemoStorage(storageKey, stored);
    }

    try {
        if (!window.localStorage.getItem(`demo_v1_${storageKey}`)) {
            writeDemoStorage(storageKey, reportSeedData);
        }
    } catch {
        // Seed data tetap dipakai jika browser storage tidak tersedia.
    }

    if (commodity === 'Semua') return structuredClone(stored);

    const filtered = structuredClone(stored);
    filtered.data.land_and_cash_proportion.donut_chart_data =
        filtered.data.land_and_cash_proportion.donut_chart_data.filter(
            (item) => item.commodity_name === commodity,
        );
    filtered.data.operational_targets =
        filtered.data.operational_targets.filter(
            (item) => item.commodity_name === commodity,
        );
    filtered.data.monthly_commodity_reports =
        filtered.data.monthly_commodity_reports.filter((item) =>
            item.commodity_name.replace('Tanaman ', '') === commodity,
        );
    filtered.data.quarterly_report.farms = filtered.data.quarterly_report.farms.filter(
        (item) => item.commodity === commodity,
    );
    filtered.data.quarterly_report.farm_recap = filtered.data.quarterly_report.farm_recap.filter(
        (item) => item.commodity_summary.includes(commodity.replace('Rumput Pakan', 'RUMPUT').toUpperCase()),
    );

    return filtered;
}
