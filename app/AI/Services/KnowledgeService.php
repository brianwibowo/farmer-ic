<?php

namespace App\AI\Services;

use App\Models\AiKnowledgeDocument;
use App\Models\Team;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class KnowledgeService
{
    /** @return array{facts: string, policy: string, adaptive: string, hashes: array{facts: string, policy: string, adaptive: array<int, string>}} */
    public function snapshot(?Team $team = null): array
    {
        $facts = $this->read('facts_path');
        $demoFacts = $this->loadFarmDemoFacts();
        if ($demoFacts !== '') {
            $facts .= "\n\n".$demoFacts;
        }
        $policy = $this->read('policy_path');
        $published = $team?->aiKnowledgeDocuments()
            ->where('status', AiKnowledgeDocument::STATUS_PUBLISHED)
            ->orderBy('id')
            ->get(['id', 'title', 'content', 'content_hash']) ?? collect();
        $adaptive = $published
            ->map(fn (AiKnowledgeDocument $document): string => "## {$document->title}\n{$document->content}")
            ->implode("\n\n");

        return [
            'facts' => $facts,
            'policy' => $policy,
            'adaptive' => $adaptive,
            'hashes' => [
                'facts' => hash('sha256', $facts),
                'policy' => hash('sha256', $policy),
                'adaptive' => $published->pluck('content_hash')->values()->all(),
            ],
        ];
    }

    protected function read(string $key): string
    {
        $path = config("ai.knowledge.{$key}");

        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Knowledge base file [{$key}] is unavailable.");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Knowledge base file [{$key}] could not be read.");
        }

        return trim($contents);
    }

    /**
     * Membaca snapshot demo terpadu dan merangkumnya menjadi fakta kaya
     * (kebun, wawasan, keuangan lengkap, laporan operasional, dan 50 log aktivitas terbaru)
     * yang di-cache secara otomatis berdasarkan hash isi file.
     */
    protected function loadFarmDemoFacts(): string
    {
        $dataPath = resource_path('js/data/farmDemoData.json');
        if (! is_file($dataPath) || ! is_readable($dataPath)) {
            return '';
        }

        $fileHash = md5_file($dataPath);
        if ($fileHash === false) {
            return '';
        }

        $cacheKey = 'farm_demo_facts_v2_'.$fileHash;

        return Cache::rememberForever($cacheKey, fn (): string => $this->compileFarmDemoKnowledge($dataPath));
    }

    /**
     * Mengompilasi seluruh dataset farmDemoData.json menjadi representasi Markdown
     * yang padat, terstruktur, dan sangat hemat token bagi LLM.
     */
    protected function compileFarmDemoKnowledge(string $dataPath): string
    {
        $data = json_decode((string) file_get_contents($dataPath), true);
        if (! is_array($data)) {
            return '';
        }

        $lines = [];
        $formatRupiah = static fn (int|float $value): string => 'Rp '.number_format($value, 0, ',', '.');

        // 1. Data Kebun & Wawasan (Insights)
        $kebunList = $data['kebun'] ?? [];
        if (is_array($kebunList) && ! empty($kebunList)) {
            $insights = is_array($data['kebunInsights'] ?? null) ? $data['kebunInsights'] : [];
            $lines[] = "## Data Kebun Petani (Sumber: resources/js/data/farmDemoData.json)\n";
            $lines[] = 'Petani saat ini memiliki '.count($kebunList).' kebun aktif yang terdaftar di aplikasi:';

            foreach ($kebunList as $index => $item) {
                $id = $item['id'] ?? '';
                $name = $item['name'] ?? 'Kebun';
                $city = $item['city'] ?? '';
                $district = ! empty($item['district']) ? ', Kec. '.$item['district'] : '';
                $crop = $item['seedType'] ?? '';
                $variety = ! empty($item['seedTypeDetail']) ? ' ('.$item['seedTypeDetail'].')' : '';
                $used = $item['usedHectare'] ?? 0;
                $total = $item['availableHectare'] ?? 0;
                $health = $item['healthStatus'] ?? 'Sehat';

                $extraList = [];
                if (isset($insights[$id]) && is_array($insights[$id])) {
                    $ins = $insights[$id];
                    if (! empty($ins['growth'])) {
                        $extraList[] = "Pertumbuhan: {$ins['growth']}%";
                    }
                    if (! empty($ins['harvestAt'])) {
                        $extraList[] = "Est. Panen: {$ins['harvestAt']}";
                    }
                    if (! empty($ins['currentPhase'])) {
                        $extraList[] = "Fase: {$ins['currentPhase']}";
                    }
                    if (! empty($ins['soilMoisture'])) {
                        $extraList[] = "Kelembaban Tanah: {$ins['soilMoisture']}";
                    }
                }

                $extraStr = ! empty($extraList) ? ' | '.implode(', ', $extraList) : '';
                $num = $index + 1;
                $lines[] = "{$num}. **{$name}**: {$city}{$district}. Komoditas: {$crop}{$variety}. Luas: {$used}/{$total} ha. Status: {$health}{$extraStr}.";
            }
        }

        // 2. Data Keuangan 2026 (Ringkasan, Kuartal, dan Seluruh Transaksi)
        $finance = $data['finance'] ?? [];
        if (is_array($finance) && ! empty($finance)) {
            $lines[] = "\n## Data Keuangan Petani Tahun 2026";
            $lines[] = '- Total Pendapatan Kumulatif 2026: '.$formatRupiah((float) ($finance['totalRevenue'] ?? 0));
            $lines[] = '- Total Pengeluaran (OPEX) 2026: '.$formatRupiah((float) ($finance['totalExpense'] ?? 0));
            $lines[] = '- Laba Bersih Kumulatif 2026: '.$formatRupiah((float) ($finance['totalNet'] ?? 0));

            if (! empty($finance['quarters']) && is_array($finance['quarters'])) {
                $lines[] = '- Ringkasan Kinerja per Kuartal:';
                foreach ($finance['quarters'] as $quarter => $summary) {
                    if (! is_array($summary)) {
                        continue;
                    }

                    $analysis = ! empty($summary['analysis']) ? ' Analisis: '.$summary['analysis'] : '';
                    $lines[] = sprintf(
                        '  * **%s (%s)**: Pendapatan %s, Pengeluaran %s, Laba Bersih %s.%s',
                        $quarter,
                        $summary['label'] ?? '',
                        $formatRupiah((float) ($summary['totalRevenue'] ?? 0)),
                        $formatRupiah((float) ($summary['totalExpense'] ?? 0)),
                        $formatRupiah((float) ($summary['netTotal'] ?? 0)),
                        $analysis,
                    );
                }
            }

            $transactions = $finance['transactions'] ?? [];
            if (is_array($transactions) && ! empty($transactions)) {
                $lines[] = '- Daftar Seluruh Transaksi Keuangan 2026 ('.count($transactions).' Transaksi):';
                foreach ($transactions as $tx) {
                    $d = $tx['date'] ?? [];
                    $dateStr = trim(($d['day'] ?? '').' '.($d['month'] ?? '').' '.($d['year'] ?? ''));
                    $typeStr = ($tx['type'] ?? '') === 'income' ? 'Pemasukan' : 'Pengeluaran';
                    $commodity = ! empty($tx['commodity']) ? ", {$tx['commodity']}" : '';
                    $category = ! empty($tx['category']) ? ", {$tx['category']}" : '';

                    $lines[] = sprintf(
                        '  * [%s] %s (%s%s) - %s: %s',
                        $dateStr,
                        $tx['description'] ?? 'Transaksi',
                        ltrim($commodity, ', '),
                        $category,
                        $typeStr,
                        $formatRupiah((float) ($tx['amount'] ?? 0)),
                    );
                }
            }
        }

        // 3. Laporan Operasional & Target Lapangan Terkini
        $reportData = $data['report']['data'] ?? [];
        if (is_array($reportData) && ! empty($reportData)) {
            $lines[] = "\n## Laporan Operasional & Target Lapangan";
            $cards = $reportData['summary_cards'] ?? [];

            if (isset($cards['total_area']['total_hectares'])) {
                $lines[] = sprintf(
                    '- Total Lahan Operasional: %s Ha (%s Komoditas)',
                    $cards['total_area']['total_hectares'],
                    $cards['total_area']['total_commodities'] ?? 0,
                );
            }
            if (isset($cards['weekly_opex']['current_week_opex_idr'])) {
                $lines[] = sprintf(
                    '- OPEX Mingguan Berjalan: %s (Plafon Bulanan: %s)',
                    $formatRupiah((float) $cards['weekly_opex']['current_week_opex_idr']),
                    $formatRupiah((float) ($cards['weekly_opex']['monthly_budget_idr'] ?? 0)),
                );
            }
            if (isset($cards['estimated_harvest']['total_estimated_revenue_idr'])) {
                $lines[] = '- Estimasi Nilai Panen Mendatang: '.$formatRupiah((float) $cards['estimated_harvest']['total_estimated_revenue_idr']);
            }
            if (isset($cards['budget_efficiency']['efficiency_percentage'])) {
                $lines[] = sprintf(
                    '- Efisiensi Anggaran: %s%% (%s)',
                    $cards['budget_efficiency']['efficiency_percentage'],
                    $cards['budget_efficiency']['ceiling_compliance_status'] ?? '',
                );
            }

            $targets = $reportData['operational_targets'] ?? [];
            if (is_array($targets) && ! empty($targets)) {
                $lines[] = '- Target Lapangan Terkini:';
                foreach ($targets as $target) {
                    $comm = $target['commodity_name'] ?? '';
                    $farm = $target['farm_name'] ?? '';
                    $area = $target['area_hectares'] ?? 0;
                    $status = $target['status_description'] ?? '';
                    $faseCode = $target['fase']['code'] ?? '';
                    $faseDesc = $target['fase']['description'] ?? '';
                    $taskName = $target['operational_task']['task_name'] ?? '';
                    $progress = $target['operational_task']['progress_percentage'] ?? 0;

                    $lines[] = "  * **{$comm}** ({$farm}, {$area} ha): {$status}. Fase {$faseCode} ({$faseDesc}). Tugas: {$taskName} ({$progress}% selesai).";
                }
            }
        }

        // 4. Log Aktivitas Lapangan (50 Data Terbaru)
        $allLogs = $data['activityLogs'] ?? [];
        if (is_array($allLogs) && ! empty($allLogs)) {
            $latestLogs = array_slice($allLogs, 0, 50);
            $totalCount = count($allLogs);
            $shownCount = count($latestLogs);

            $lines[] = "\n## {$shownCount} Log Aktivitas Lapangan Terbaru (Total tercatat di sistem: {$totalCount})";

            foreach ($latestLogs as $index => $act) {
                $num = $index + 1;
                $timestamp = $act['timestamp'] ?? '-';
                $label = $act['typeLabel'] ?? ($act['type'] ?? 'Aktivitas');
                $kebunName = $act['kebunName'] ?? 'Kebun';
                $officer = $act['officerName'] ?? 'Petugas Lapangan';
                $seed = $act['seedType'] ?? '';
                $hst = isset($act['daysAfterPlanting']) ? ", {$act['daysAfterPlanting']} HST" : '';

                $details = [];
                $rep = $act['report'] ?? [];
                if (! empty($rep['fertilizerName'])) {
                    $dosage = ! empty($rep['dosage']) ? " ({$rep['dosage']})" : '';
                    $details[] = "Pupuk: {$rep['fertilizerName']}{$dosage}";
                }
                if (! empty($rep['waterVolume'])) {
                    $details[] = "Volume Air: {$rep['waterVolume']}";
                }
                if (! empty($rep['pesticideName'])) {
                    $targetPest = ! empty($rep['targetPest']) ? " ({$rep['targetPest']})" : '';
                    $details[] = "Pestisida: {$rep['pesticideName']}{$targetPest}";
                }
                if (! empty($rep['harvestedArea'])) {
                    $weight = ! empty($rep['grossWeight']) ? "{$rep['grossWeight']} dari " : '';
                    $details[] = "Panen: {$weight}{$rep['harvestedArea']}";
                }
                if (! empty($rep['seedName'])) {
                    $details[] = "Benih: {$rep['seedName']}";
                }
                if (! empty($rep['issueType'])) {
                    $severity = ! empty($rep['severity']) ? " ({$rep['severity']})" : '';
                    $details[] = "Insiden: {$rep['issueType']}{$severity}";
                }

                $detailStr = ! empty($details) ? ' - Rincian: '.implode(', ', $details) : '';
                $cropInfo = $seed !== '' ? " ({$seed}{$hst})" : '';

                $lines[] = "{$num}. [{$timestamp}] **{$label}** di {$kebunName}{$cropInfo} - Petugas: {$officer}{$detailStr}.";
            }
        }

        return implode("\n", $lines);
    }
}
