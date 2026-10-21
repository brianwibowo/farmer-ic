<script setup lang="ts">
import { Activity, AlertTriangle, Bot, CalendarClock, CalendarDays, CheckCircle2, CircleDollarSign, Sprout, TrendingDown, TrendingUp, Wheat } from '@lucide/vue';
import type { FarmReport } from '@/services/farmDemoService';

const props = defineProps<{ report: FarmReport; formatMoney: (value: number) => string }>();

// Palet warna persis mengambil dari style scoped (.status-healthy, .metric-blue, .metric-purple, .status-attention, .status-critical)
const stageColors = ['#9a59b5', '#e84c3d', '#f1c40f', '#2dcc70', '#3598db'];

const getDonutGradient = () => {
    const total = props.report.growthTimeline?.observedKebunCount || props.report.summary.totalKebun || 1;
    let accumulatedPercent = 0;

    const stops = props.report.growthStages.map((stage, idx) => {
        const percent = (stage.count / total) * 100;
        const color = stageColors[idx % stageColors.length];
        const start = accumulatedPercent;
        accumulatedPercent += percent;
        return `${color} ${start}% ${accumulatedPercent}%`;
    });

    return `conic-gradient(${stops.join(', ')})`;
};
</script>

<template>
    <Teleport to="body">
        <article class="report-print-document" aria-label="Dokumen laporan kebun">
            <header class="print-header">
                <div>
                    <div class="print-identity">
                        <img class="print-header-logo" src="/images/logo-dashboard.svg" alt="Farmer IC" style="margin-right:0pt;"/>
                        <p class="print-kicker" style="padding-top: 0,5pt;">-  Laporan resmi</p>
                    </div>
                    <h1>Laporan Kebun</h1>
                    <p>{{ report.periodLabel }} · {{ report.scopeLabel }}</p>
                </div>
                <div class="print-meta">
                    <span class="print-meta-label"><CalendarDays :size="13" /> Dibuat</span>
                    <strong>{{ report.generatedAt }}</strong>
                </div>
            </header>

            <section class="print-section">
                <h2 class="print-section-title"><Sprout :size="17" /> Ringkasan kebun</h2>
                <div class="print-metrics">
                    <div class="metric-green"><span><Sprout :size="14" /> Total kebun</span><strong>{{ report.summary.totalKebun }}</strong></div>
                    <div class="metric-blue"><span><CircleDollarSign :size="14" /> Total luas</span><strong>{{ report.summary.totalArea }} Ha</strong></div>
                    <div class="metric-purple"><span><TrendingUp :size="14" /> Pertumbuhan</span><strong>{{ report.summary.growth }}%</strong></div>
                    <div class="metric-orange"><span><Wheat :size="14" /> Panen tercatat</span><strong>{{ report.harvest.totalWeight }} Ton</strong></div>
                </div>
                <div class="print-status-grid">
                    <span class="status-healthy"><CheckCircle2 :size="14" /> Sehat <strong>{{ report.summary.healthy }}</strong></span>
                    <span class="status-attention"><AlertTriangle :size="14" /> Perlu perhatian <strong>{{ report.summary.attention }}</strong></span>
                    <span class="status-critical"><AlertTriangle :size="14" /> Kritis <strong>{{ report.summary.critical }}</strong></span>
                </div>
            </section>

            <section class="print-section">
                <h2 class="print-section-title"><CircleDollarSign :size="17" /> Keuangan dan panen</h2>
                <div class="print-finance-grid">
                    <div class="metric-green"><span><TrendingUp :size="14" /> Pendapatan</span><strong>{{ formatMoney(report.finance.revenue) }}</strong></div>
                    <div class="metric-red"><span><TrendingDown :size="14" /> <p>Pengeluaran</p></span><strong>{{ formatMoney(report.finance.expenses) }}</strong></div>
                    <div class="metric-blue"><span><CircleDollarSign :size="14" /> Pendapatan bersih</span><strong>{{ formatMoney(report.finance.net) }}</strong></div>
                    <div class="metric-purple"><span><TrendingUp :size="14" /> Margin</span><strong>{{ report.finance.margin }}%</strong></div>
                </div>
                <p class="print-callout harvest-callout">
                    <CalendarClock :size="18" /> 
                    <span>Panen terdekat<br /><strong>{{ report.harvest.nextKebun }}</strong> <em>{{ report.harvest.nextDate }}</em></span>
                </p>
                <table>
                    <thead>
                        <tr><th>Kebun</th><th>Komoditas · fase</th><th>Pendapatan</th><th>OPEX</th><th>ROI</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="farm in report.finance.farms" :key="farm.id">
                            <td>{{ farm.name }}</td><td>{{ farm.crop }} · {{ farm.phase }}</td><td>{{ formatMoney(farm.revenue) }}</td><td>{{ formatMoney(farm.expenses) }}</td><td>{{ farm.roi }}%</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Perkembangan Tanaman Layout 2 Kolom dengan Donut Center Kiri & Legenda Kanan -->
            <section class="print-section">
        <h2 class="print-section-title"><TrendingUp :size="17" /> Perkembangan tanaman</h2>
        
        <div class="donut-two-column">
            <!-- Kolom 1: Donut Chart Besar -->
            <div class="donut-chart-col">
                <div class="donut-chart" :style="{ background: getDonutGradient() }">
                    <div class="donut-hole">
                        <strong>{{ report.growthTimeline?.observedKebunCount || report.summary.totalKebun }}</strong>
                        <span>Kebun</span>
                    </div>
                </div>
            </div>

            <!-- Kolom 2: Legenda Detail Kanan -->
            <div class="donut-legend-col">
                <div v-for="(stage, idx) in report.growthStages" :key="stage.label" class="legend-row">
                    <span class="legend-indicator" :style="{ backgroundColor: stageColors[idx % stageColors.length] }" />
                    <div class="legend-info">
                        <span class="legend-title">{{ stage.label }}</span>
                        <strong>{{ stage.count }} kebun </strong>
                    </div>
                </div>
            </div>
        </div>
    </section>

            <section class="print-section print-two-column">
                <div>
                    <h2 class="print-section-title"><AlertTriangle :size="17" /> Risiko dan perhatian</h2>
                    <ul v-if="report.risks.length"><li v-for="risk in report.risks" :key="risk.id"><strong>{{ risk.name }}</strong> · {{ risk.message }}</li></ul>
                    <p v-else class="print-empty">Tidak ada risiko pada cakupan ini.</p>
                </div>
                <div>
                    <h2 class="print-section-title"><Bot :size="17" /> Catatan AI</h2>
                    <p>{{ report.ai.farm }}</p>
                    <p>{{ report.ai.finance }}</p>
                </div>
            </section>

            <section class="print-section">
                <h2 class="print-section-title"><Activity :size="17" /> Aktivitas terbaru</h2>
                <div v-if="report.activities.length" class="print-activity-list">
                    <div v-for="activity in report.activities" :key="activity.id" class="print-activity-item">
                        <span class="print-activity-dot" aria-hidden="true" />
                        <div><strong>{{ activity.kebunName }} · {{ activity.typeLabel }}</strong><span>{{ activity.timestamp }} · {{ activity.officerName }}</span></div>
                    </div>
                </div>
                <p v-else class="print-empty">Belum ada aktivitas pada cakupan ini.</p>
            </section>

            <footer class="print-footer">Farmer IC · Dokumen dibuat dari data laporan {{ report.periodLabel }}</footer>
        </article>
    </Teleport>
</template>

<style scoped>
.report-print-document { display: none; }
@media print {
 @page { size: A4 portrait; margin: 18mm 14mm 14mm 14mm; }
 .report-print-document { display:block !important; position:static; width:100%; color:#17221a; font-family:"Times New Roman", Times, serif; font-size:11pt; line-height:1.5; background:white; print-color-adjust:exact; -webkit-print-color-adjust:exact; }
 .print-header { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #1c5e21; padding:4px 0 14px; margin-bottom:12px; }
 .print-identity { display:flex; align-items:center; gap:8px; margin:0 0 8px 5px; } .print-header-logo { display:block; width:66px; height:25px; object-fit:contain; object-position:left center; } .print-kicker { margin:0 !important; color:#1c5e21; font-size:9.5pt; font-weight:500; letter-spacing:.02em; line-height:1.2; } .print-header p { margin:10px 5px; font-size:12pt; } h1 { font-size:25pt; font-weight:800; line-height:1.15; margin:0 0 6px; } .print-meta { text-align:right; line-height:1.3; } .print-meta-label,.print-section span,.print-note { color:#526158; } .print-meta-label,.print-section-title,.print-callout,.print-metrics span,.print-finance-grid span,.print-status-grid span { display:flex; align-items:center; gap:6px; } .print-meta strong { display:block; margin-top:3px; font-size:11pt; font-weight:800; }
 .print-section { break-inside:avoid; page-break-inside:avoid; border-bottom:1px solid #d9d9d9; padding:14px 0; } .print-section-title { color:#1c5e21; font-size:15pt; font-weight:800; margin:0 0 10px; } .print-metrics,.print-finance-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; } .print-metrics div,.print-finance-grid div { border:1px solid #d9d9d9; border-radius:8px; padding:10px; } .print-metrics span,.print-finance-grid span { font-size:9pt; font-weight:700; } .print-metrics strong,.print-finance-grid strong { display:block; font-size:15pt; font-weight:800; margin-top:5px; } .print-highlight,.status-healthy strong { color:#1c5e21; } .status-attention strong { color:#a06b00; } .status-critical strong { color:#a52323; } .print-status-grid { display:flex; gap:18px; margin-top:12px; font-weight:700; }
 table { width:100%; border-collapse:collapse; margin-top:14px; } thead { display:table-header-group; } tr { break-inside:avoid; page-break-inside:avoid; } th,td { border-bottom:1px solid #d9d9d9; padding:8px 6px; text-align:left; } thead tr { border-top:2px solid #1c5e21; border-bottom:2px solid #93a99f; } th { color:#1c5e21; font-weight:800; }

 @media print {
 /* Layout 2 Kolom Terpusat (Center Center) */
 .donut-two-column { 
    display: grid; 
    grid-template-columns: 220px auto; /* Lebar kolom chart diperbesar */
    gap: 32px; 
    align-items: center; 
    justify-content: center; /* Memastikan seluruh grid berada di tengah halaman secara horizontal */
    width: fit-content; 
    margin: 16px auto; /* Center alignment layaknya Ctrl + E */
 }

 .donut-chart-col { 
    display: flex; 
    justify-content: center; 
    align-items: center; 
 }

 /* Ukuran Donut Chart Diperbesar */
 .donut-chart { 
    position: relative; 
    width: 170px; /* Ukuran chart lebih besar */
    height: 170px; 
    border-radius: 50%; 
    flex-shrink: 0; 
    print-color-adjust: exact; 
    -webkit-print-color-adjust: exact; 
 }

 .donut-hole { 
    position: absolute; 
    top: 50%; 
    left: 50%; 
    transform: translate(-50%, -50%); 
    width: 100px; /* Hole menyesuaikan skala chart */
    height: 100px; 
    background: white !important; 
    border-radius: 50%; 
    display: flex; 
    flex-direction: column; 
    align-items: center; 
    justify-content: center; 
    text-align: center; 
 }

 .donut-hole strong { 
    font-size: 20pt; 
    font-weight: 800; 
    color: #1c5e21; 
    line-height: 1; 
 }

 .donut-hole span { 
    font-size: 9pt; 
    color: #526158; 
    margin-top: 3px; 
    font-weight: 600; 
 }

 .donut-legend-col { 
    display: grid; 
    gap: 10px; 
 }

 .legend-row { 
    display: flex; 
    align-items: center; 
    gap: 10px; 
    font-size: 10pt; 
 }

 .legend-indicator { 
    width: 14px; 
    height: 14px; 
    border-radius: 3px; 
    flex-shrink: 0; 
    print-color-adjust: exact; 
    -webkit-print-color-adjust: exact; 
 }

 .legend-info { 
    display: flex; 
    align-items: center; 
    gap: 8px; 
    line-height: 1.2; 
 }

 .legend-title { 
    color: #526158; 
    font-size: 10pt; 
 }

 .legend-info strong { 
    color: #17221a; 
    font-weight: 800; 
    font-size: 10.5pt; 
 }

 .legend-info em { 
    font-style: normal; 
    font-weight: 700; 
    color: #27833d; 
 }
}

 .harvest-callout { display:flex; align-items:center; gap:9px; margin:14px 0 4px; padding:11px 13px; border:2px solid #68bb6c; border-left:6px solid #1c5e21; border-radius:10px; background:#ebf3ed !important; color:#1c5e21 !important; font-size:11pt; font-weight:700; } .harvest-callout strong { display:inline-block; margin-right:8px; font-size:13pt; font-weight:800; } .harvest-callout em { font-style:normal; font-weight:800; color:#a06b00; }
 .metric-green { color:#1c5e21; border-color:#68bb6c !important; background:#f3faf4 !important; } .metric-blue { color:#245c91; border-color:#74a9d8 !important; background:#f1f7fd !important; } .metric-purple { color:#6a4c93; border-color:#b69bd8 !important; background:#fff3f4 !important; } .metric-red svg{color:#c91414;} .metric-red p{color:#c91414;} .metric-red{ color:#c91414; border-color:#c91414 !important; background:#ffebed !important; } .metric-orange { color:#dfb912; border-color:#dfb912 !important; background:#fffceb !important; } .metric-green span,.metric-blue span,.metric-purple span,.metric-orange span { color:inherit; } .status-healthy { color:#27833d !important; } .status-attention { color:#b27a00 !important; } .status-critical { color:#bc0202 !important; } .status-healthy svg,.status-healthy strong { color:#27833d; } .status-attention svg,.status-attention strong { color:#b27a00; } .status-critical svg,.status-critical strong { color:#bc0202; }
 .print-two-column { display:grid; grid-template-columns:1fr 1fr; gap:16px; border-bottom:0; } .print-two-column > div { min-width:0; padding:14px; border-radius:10px; } .print-two-column > div:first-child { border:2px solid #bc0202; background:#fff3f3 !important; color:#7b1c1c; } .print-two-column > div:first-child .print-section-title { color:#bc0202; } .print-two-column > div:last-child { border:2px solid #4c78b8; background:#eef5ff !important; color:#245486; } .print-two-column > div:last-child .print-section-title { color:#1d5da8; } .print-two-column > div:last-child p { color:#245486; font-weight:500; line-height:1.55; } ul { margin:0; padding-left:18px; } li { margin-bottom:7px; }
 .print-activity-list { display:grid; gap:10px; } .print-activity-item { display:flex; gap:9px; align-items:flex-start; padding:10px 12px; border:1px solid #93a99f; border-left:5px solid #68bb6c; border-radius:9px; background:#f3f8f4 !important; } .print-activity-dot { width:8px; height:8px; margin-top:6px; flex:0 0 auto; border-radius:50%; background:#1c5e21 !important; } .print-activity-item strong,.print-activity-item span { display:block; } .print-activity-item strong { font-weight:800; } .print-activity-item span { margin-top:3px; font-size:9pt; } .print-empty { color:#526158; font-style:italic; } .print-footer { border-top:1px solid #d9d9d9; color:#526158; font-size:9pt; margin-top:18px; padding-top:8px; }
 .report-print-document strong { font-weight:600; } .report-print-document h1 { font-weight:700; } .print-kicker { font-weight:500; } .print-section-title { font-weight:700; } .print-metrics strong,.print-finance-grid strong { font-weight:650; } .print-activity-item strong { font-weight:650; }
}
</style>