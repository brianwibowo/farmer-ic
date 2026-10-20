<?php

namespace App\AI\Services;

class HybridKnowledgeReranker
{
    /**
     * @param  array<int, array{id: string|int, score: float, payload: array<string, mixed>}>  $rows
     * @return array<int, array{id: string|int, score: float, payload: array<string, mixed>, vector_score: float, lexical_score: float, hybrid_score: float}>
     */
    public function rerank(string $query, array $rows, int $limit): array
    {
        $queryTerms = $this->terms($query);
        $isFinanceQuery = $this->containsAny($queryTerms, ['finance'])
            && $this->containsAny($queryTerms, ['report', 'current', 'income', 'expense', 'profit_loss']);
        $ranked = [];

        foreach ($rows as $row) {
            $payload = $row['payload'];
            $lexicalScore = $this->lexicalScore($queryTerms, $payload);
            $vectorScore = (float) $row['score'];
            $semanticWeight = min(1.0, max(0.0, (float) config('ai.rag.vector_semantic_weight', 0.85)));
            $hybridScore = ($semanticWeight * $vectorScore) + ((1 - $semanticWeight) * $lexicalScore);

            // The app-facts document is the authoritative source for feature
            // and data-availability questions. Apply this small prior only
            // when the query contains finance/report language; never boost it
            // globally or it would distort agronomy retrieval.
            if ($isFinanceQuery && ($payload['document_id'] ?? null) === 'application-facts') {
                $hybridScore += 0.08;
            }
            $hybridScore = min(1.0, $hybridScore);

            $ranked[] = [
                ...$row,
                'vector_score' => round($vectorScore, 4),
                'lexical_score' => round($lexicalScore, 4),
                'hybrid_score' => round($hybridScore, 4),
            ];
        }

        usort($ranked, static function (array $left, array $right): int {
            $hybrid = $right['hybrid_score'] <=> $left['hybrid_score'];

            return $hybrid !== 0 ? $hybrid : $right['vector_score'] <=> $left['vector_score'];
        });

        return array_slice($ranked, 0, max(1, $limit));
    }

    /**
     * @param  array<int, string>  $queryTerms
     * @param  array<string, mixed>  $payload
     */
    private function lexicalScore(array $queryTerms, array $payload): float
    {
        $contentTerms = $this->terms((string) ($payload['content'] ?? ''));
        $titleTerms = $this->terms((string) ($payload['title'] ?? ''));
        $contentOverlap = count(array_intersect($queryTerms, $contentTerms));
        $titleOverlap = count(array_intersect($queryTerms, $titleTerms));

        return min(1.0, ($contentOverlap + (2 * $titleOverlap)) / max(1, count($queryTerms)));
    }

    /** @return array<int, string> */
    private function terms(string $value): array
    {
        $normalized = mb_strtolower($value, 'UTF-8');
        $normalized = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized);
        $tokens = array_values(array_filter(
            explode(' ', trim($normalized)),
            fn (string $token): bool => mb_strlen($token, 'UTF-8') > 2 && ! in_array($token, $this->stopwords(), true),
        ));
        $terms = [];
        foreach ($tokens as $token) {
            $terms[] = $this->alias($token);
        }

        if (str_contains($normalized, 'laporan keuangan') || str_contains($normalized, 'laporan finansial')) {
            $terms[] = 'finance';
            $terms[] = 'report';
        }
        if (preg_match('/\b(bulan ini|bulan sekarang|sekarang|saat ini|kini|current)\b/u', $normalized)) {
            $terms[] = 'current';
        }

        return array_values(array_unique($terms));
    }

    private function alias(string $token): string
    {
        return match (true) {
            in_array($token, ['keuangan', 'finansial', 'financial', 'finance'], true) => 'finance',
            in_array($token, ['laporan', 'report', 'statement'], true) => 'report',
            in_array($token, ['pemasukan', 'pendapatan', 'omzet', 'income'], true) => 'income',
            in_array($token, ['pengeluaran', 'biaya', 'expense'], true) => 'expense',
            in_array($token, ['laba', 'rugi', 'profit'], true) => 'profit_loss',
            in_array($token, ['sekarang', 'kini', 'current'], true) => 'current',
            in_array($token, ['jagng'], true) => 'jagung',
            in_array($token, ['inii'], true) => 'ini',
            in_array($token, ['irigasinya', 'irigasi', 'pengairan', 'penyiraman', 'siram'], true) => 'air',
            in_array($token, ['kering', 'lembap', 'lembab', 'kelembapan'], true) => 'kelembapan',
            default => $token,
        };
    }

    /** @return array<int, string> */
    private function stopwords(): array
    {
        return [
            'yang', 'dan', 'dari', 'untuk', 'saya', 'kami', 'kamu', 'anda', 'ini',
            'itu', 'ada', 'apa', 'bisa', 'mau', 'kalau', 'dengan', 'seperti', 'perlu',
            'cara', 'gimana', 'bagaimana', 'boleh', 'langsung', 'sudah', 'nggak', 'tidak',
        ];
    }

    /**
     * @param  array<int, string>  $terms
     * @param  array<int, string>  $needles
     */
    private function containsAny(array $terms, array $needles): bool
    {
        return array_intersect($terms, $needles) !== [];
    }
}
