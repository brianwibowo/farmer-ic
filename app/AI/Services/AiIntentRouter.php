<?php

namespace App\AI\Services;

use App\AI\Data\AiRouteDecision;

class AiIntentRouter
{
    /** @var array<int, string> */
    private const FINANCE_TERMS = [
        'keuangan', 'finansial', 'pendapatan', 'pemasukan', 'omzet',
        'transaksi', 'laba', 'profit', 'saldo', 'keuntungan', 'pengeluaran', 'biaya',
    ];

    /** @var array<int, string> */
    private const AGRICULTURE_TERMS = [
        'kebun', 'lahan', 'sawah', 'ladang', 'tanaman', 'padi', 'jagung',
        'kedelai', 'cabai', 'tomat', 'bawang', 'pupuk', 'pemupukan', 'irigasi',
        'pengairan', 'penanaman', 'perawatan', 'tanah', 'hama', 'penyakit',
        'pestisida', 'cuaca', 'pertanian', 'petani', 'komoditas', 'gejala',
        'bercak', 'daun', 'pemeriksaan', 'foto', 'scouting', 'drainase',
        'kelembapan', 'kering', 'lembap', 'curah',
    ];

    /** @var array<int, string> */
    private const DATA_TERMS = [
        'data', 'laporan', 'riwayat', 'ringkasan', 'rincian', 'jumlah', 'total',
        'status', 'terakhir', 'terbaru',
    ];

    /** @var array<int, string> */
    private const VISUAL_TERMS = [
        'infografis', 'infographic', 'diagram', 'visual', 'poster', 'bagan',
        'gambar', 'ilustrasi', 'gambarkan', 'skema', 'alur', 'grafik', 'chart', 'peta',
    ];

    /** @var array<int, string> */
    private const OUT_OF_SCOPE_TERMS = [
        'coding', 'kode program', 'buat kode', 'tulis kode', 'buat program',
        'javascript', 'python', 'html', 'css', 'sql', 'politik', 'crypto', 'sepak bola',
    ];

    public function decide(string $value, bool $hasAttachments = false): AiRouteDecision
    {
        $prompt = $this->normalize($value);
        $domain = $this->domain($prompt);
        $period = $this->period($prompt);
        $topic = $this->firstTerm($prompt, [...self::AGRICULTURE_TERMS, ...self::FINANCE_TERMS]);

        if ($this->hasAny($prompt, self::OUT_OF_SCOPE_TERMS) && ! $hasAttachments) {
            return $this->decision('out_of_scope', 'general', $period, null, 0.98, 0.9, false, 'unsupported_topic');
        }

        if ($this->hasAny($prompt, self::VISUAL_TERMS) && $domain !== 'general') {
            return $this->decision('infographic', $domain, $period, $topic, 0.94, 0.75, false, 'explicit_visual_request');
        }

        if ($domain === 'finance') {
            return $this->decision('llm', $domain, $period, $topic, 0.9, 0.7, false, 'finance_analysis');
        }

        if ($this->hasAny($prompt, self::DATA_TERMS) && $domain !== 'general') {
            return $this->decision('data', $domain, $period, $topic, 0.92, 0.7, false, 'explicit_data_lookup');
        }

        if (in_array($domain, ['agronomy', 'garden', 'activity'], true)) {
            return $this->decision('llm', $domain, $period, $topic, 0.82, 0.55, false, 'agriculture_guidance');
        }

        $isGreeting = (bool) preg_match('/^(halo|hai|hei|hi|pagi|siang|sore|malam|makasih|terima kasih)(?:[!.,\s]|$)/i', $prompt);

        return $this->decision(
            'llm',
            'general',
            $period,
            null,
            0.55,
            0.08,
            $prompt !== '' && mb_strlen($prompt) < 14 && ! $isGreeting,
            'general_question',
        );
    }

    private function decision(
        string $intent,
        string $domain,
        string $period,
        ?string $topic,
        float $confidence,
        float $margin,
        bool $needsClarification,
        string $reason,
    ): AiRouteDecision {
        return new AiRouteDecision(
            intent: $intent,
            domain: $domain,
            period: $period,
            topic: $topic,
            confidence: $confidence,
            ambiguityMargin: $margin,
            needsClarification: $needsClarification,
            reason: $reason,
            candidates: [
                ['intent' => $intent, 'score' => $confidence],
            ],
        );
    }

    private function domain(string $value): string
    {
        if ($this->hasAny($value, self::FINANCE_TERMS)) {
            return 'finance';
        }
        if (preg_match('/\b(aktivitas|kegiatan)\b/i', $value)) {
            return 'activity';
        }
        if (preg_match('/\b(kebun|lahan|sawah|ladang)\b/i', $value)) {
            return 'garden';
        }
        if ($this->hasAny($value, self::AGRICULTURE_TERMS)) {
            return 'agronomy';
        }

        return 'general';
    }

    private function period(string $value): string
    {
        if (preg_match('/\b(bulan ini|bulan sekarang|bulan berjalan|periode ini|saat ini|sekarang|kini|hari ini|minggu ini)\b/i', $value)) {
            return 'current';
        }
        if (preg_match('/\bq\s*[1-4]\b/i', $value)) {
            return 'quarter';
        }
        if (preg_match('/\b(tahun lalu|bulan lalu|periode lalu|q[1-4]|quarter)\b/i', $value)) {
            return 'historical';
        }

        return 'unknown';
    }

    private function normalize(string $value): string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($value, 'UTF-8')));

        // STT and mobile typing commonly drop a repeated vowel or the last
        // consonant. Keep this list deliberately small and auditable instead
        // of applying fuzzy matching to every word in a user prompt.
        return trim((string) preg_replace_callback(
            '/\b(jagng|inii|kedelaii|pupukk|hamaa)\b/u',
            static fn (array $match): string => match ($match[1]) {
                'jagng' => 'jagung',
                'inii' => 'ini',
                'kedelaii' => 'kedelai',
                'pupukk' => 'pupuk',
                'hamaa' => 'hama',
            },
            $normalized,
        ));
    }

    /** @param array<int, string> $terms */
    private function hasAny(string $value, array $terms): bool
    {
        foreach ($terms as $term) {
            $escaped = preg_quote($term, '/');
            if (preg_match('/(?:^|\s)'.$escaped.'(?:nya|mu|ku)?(?:$|\s|[?!,.:;()])/iu', $value)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, string> $terms */
    private function firstTerm(string $value, array $terms): ?string
    {
        foreach ($terms as $term) {
            if ($this->hasAny($value, [$term])) {
                return $term;
            }
        }

        return null;
    }
}
