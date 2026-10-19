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
            $facts .= "\n\n" . $demoFacts;
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
     * Membaca data kebun langsung dari resources/js/data/kebun.json
     * dan merangkumnya menjadi teks fakta ringkas untuk prompt LLM.
     */
    protected function loadFarmDemoFacts(): string
    {
        $kebunPath = resource_path('js/data/kebun.json');
        if (! is_file($kebunPath) || ! is_readable($kebunPath)) {
            return '';
        }

        $kebunList = json_decode((string) file_get_contents($kebunPath), true);
        if (! is_array($kebunList) || empty($kebunList)) {
            return '';
        }

        $insightsPath = resource_path('js/data/kebunInsights.json');
        $insights = is_file($insightsPath) ? json_decode((string) file_get_contents($insightsPath), true) : [];
        if (! is_array($insights)) {
            $insights = [];
        }

        $lines = ["## Data Kebun Petani (Sumber: resources/js/data/kebun.json)\n"];
        $lines[] = 'Petani saat ini memiliki ' . count($kebunList) . ' kebun aktif yang terdaftar di aplikasi:';

        foreach ($kebunList as $index => $item) {
            $id = $item['id'] ?? '';
            $name = $item['name'] ?? 'Kebun';
            $city = $item['city'] ?? '';
            $district = ! empty($item['district']) ? ', Kec. ' . $item['district'] : '';
            $crop = $item['seedType'] ?? '';
            $variety = ! empty($item['seedTypeDetail']) ? ' (' . $item['seedTypeDetail'] . ')' : '';
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

        $lines[] = "\n## Data Keuangan Petani Tahun 2026 (Periode Berjalan: Oktober 2026)";
        $lines[] = "- Total Pendapatan Kumulatif 2026: Rp 170.000.000";
        $lines[] = "- Total Pengeluaran (OPEX) 2026: Rp 60.000.000";
        $lines[] = "- Laba Bersih Kumulatif 2026: Rp 110.000.000";
        $lines[] = "- Kuartal 4 (Oktober–Desember 2026): Total Pendapatan Rp 39.000.000, Pengeluaran Rp 14.000.000, Laba Bersih Rp 25.000.000.";
        $lines[] = "  * Bulan ini (Oktober 2026): Pendapatan Rp 12.000.000, Pengeluaran Rp 4.000.000, Laba Bersih Rp 8.000.000.";
        $lines[] = "  * November 2026 (Proyeksi): Pendapatan Rp 13.000.000, Pengeluaran Rp 5.000.000, Laba Bersih Rp 8.000.000.";
        $lines[] = "  * Desember 2026 (Proyeksi): Pendapatan Rp 14.000.000, Pengeluaran Rp 5.000.000, Laba Bersih Rp 9.000.000.";
        $lines[] = "- Kuartal 3 (Juli–September 2026): Pendapatan Rp 51.000.000, Pengeluaran Rp 17.000.000, Laba Rp 34.000.000 (Kuartal terbaik berkat panen jagung & rumput odot).";
        $lines[] = "- Kuartal 2 (April–Juni 2026): Pendapatan Rp 38.000.000, Pengeluaran Rp 14.000.000, Laba Rp 24.000.000.";
        $lines[] = "- Kuartal 1 (Januari–Maret 2026): Pendapatan Rp 42.000.000, Pengeluaran Rp 15.000.000, Laba Rp 27.000.000.";

        return implode("\n", $lines);
    }
}
