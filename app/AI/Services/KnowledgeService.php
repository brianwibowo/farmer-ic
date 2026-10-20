<?php

namespace App\AI\Services;

use App\Models\AiKnowledgeDocument;
use App\Models\Team;
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
     * Membaca snapshot demo terpadu dan merangkumnya menjadi fakta ringkas
     * untuk prompt LLM.
     */
    protected function loadFarmDemoFacts(): string
    {
        $dataPath = resource_path('js/data/farmDemoData.json');
        if (! is_file($dataPath) || ! is_readable($dataPath)) {
            return '';
        }

        $data = json_decode((string) file_get_contents($dataPath), true);
        if (! is_array($data)) {
            return '';
        }

        $kebunList = $data['kebun'] ?? [];
        if (! is_array($kebunList) || empty($kebunList)) {
            return '';
        }

        $insights = $data['kebunInsights'] ?? [];
        if (! is_array($insights)) {
            $insights = [];
        }

        $lines = ["## Data Kebun Petani (Sumber: resources/js/data/farmDemoData.json)\n"];
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

            $extra = '';
            if (isset($insights[$id]) && is_array($insights[$id])) {
                $growth = $insights[$id]['growth'] ?? null;
                $harvestAt = $insights[$id]['harvestAt'] ?? null;
                if ($growth !== null) {
                    $extra .= ", Pertumbuhan: {$growth}%";
                }
                if ($harvestAt !== null) {
                    $extra .= ", Estimasi panen: {$harvestAt}";
                }
            }

            $num = $index + 1;
            $lines[] = "{$num}. **{$name}**: {$city}{$district}. Komoditas: {$crop}{$variety}. Luas: {$used}/{$total} ha. Status: {$health}{$extra}.";
        }

        $finance = $data['finance'] ?? [];
        if (is_array($finance)) {
            $formatRupiah = static fn (int|float $value): string => 'Rp '.number_format($value, 0, ',', '.');
            $lines[] = "\n## Data Keuangan Petani Tahun 2026 (Sumber: farmDemoData.json)";
            $lines[] = '- Total Pendapatan Kumulatif 2026: '.$formatRupiah((float) ($finance['totalRevenue'] ?? 0));
            $lines[] = '- Total Pengeluaran (OPEX) 2026: '.$formatRupiah((float) ($finance['totalExpense'] ?? 0));
            $lines[] = '- Laba Bersih Kumulatif 2026: '.$formatRupiah((float) ($finance['totalNet'] ?? 0));

            foreach (($finance['quarters'] ?? []) as $quarter => $summary) {
                if (! is_array($summary)) {
                    continue;
                }

                $lines[] = sprintf(
                    '- %s (%s): Pendapatan %s, Pengeluaran %s, Laba Bersih %s.',
                    $quarter,
                    $summary['label'] ?? '',
                    $formatRupiah((float) ($summary['totalRevenue'] ?? 0)),
                    $formatRupiah((float) ($summary['totalExpense'] ?? 0)),
                    $formatRupiah((float) ($summary['netTotal'] ?? 0)),
                );
            }
        }

        return implode("\n", $lines);
    }
}
