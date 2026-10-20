<?php

namespace App\AI\Services;

use App\AI\Contracts\KnowledgeRetriever;
use App\Models\Team;

class InMemoryKnowledgeRetriever implements KnowledgeRetriever
{
    public function __construct(
        private readonly KnowledgeService $knowledge,
        private readonly KnowledgeChunker $chunker,
    ) {}

    public function retrieve(string $query, Team $team, int $limit = 4): array
    {
        $queryTerms = $this->terms($query);
        if ($queryTerms === []) {
            return [];
        }

        $matches = [];
        foreach ($this->knowledge->retrievalDocuments($team) as $document) {
            foreach ($this->chunker->chunk(
                (string) $document['content'],
                (string) $document['title'],
                (string) $document['source'],
                (string) $document['id'],
            ) as $chunk) {
                $score = $this->score($queryTerms, $chunk);
                if ($score < (float) config('ai.rag.min_score', 1.0)) {
                    continue;
                }

                $matches[] = [
                    'id' => $chunk['id'],
                    'title' => $chunk['title'],
                    'source' => $chunk['source'],
                    'content' => $chunk['content'],
                    'score' => round($score, 4),
                    'metadata' => [
                        'document_id' => $document['id'],
                        'version' => $document['version'] ?? null,
                        'team_id' => $team->id,
                        'retrieval' => 'in-memory',
                        'index_version' => 'in-memory-v1',
                    ],
                ];
            }
        }

        usort($matches, fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        return array_slice($matches, 0, max(1, $limit));
    }

    /** @return array<int, string> */
    private function terms(string $value): array
    {
        $normalized = mb_strtolower($value, 'UTF-8');
        $normalized = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized);
        $tokens = array_filter(
            explode(' ', trim($normalized)),
            fn (string $token): bool => mb_strlen($token) > 2
                && ! in_array($token, [
                    'yang', 'dan', 'dari', 'untuk', 'saya', 'kami', 'kamu', 'anda',
                    'ini', 'itu', 'ada', 'apa', 'bisa', 'mau', 'kalau', 'dengan',
                    'seperti', 'perlu', 'cara', 'gimana', 'bagaimana', 'boleh',
                    'langsung', 'sudah', 'nggak', 'tidak', 'pada', 'dalam', 'ke', 'di',
                ], true),
        );

        $expanded = [];
        foreach ($tokens as $token) {
            $expanded[] = $token;
            $expanded[] = match (true) {
                in_array($token, ['menanam', 'penanaman', 'ditanam', 'tanaman'], true) => 'tanam',
                in_array($token, ['pemupukan', 'memupuk', 'pupuknya'], true) => 'pupuk',
                in_array($token, ['penyiraman', 'pengairan', 'irigasi', 'irigasinya', 'siram', 'menyiram', 'disiram'], true) => 'air',
                in_array($token, ['kering', 'lembap', 'lembab', 'kelembapan'], true) => 'kelembapan',
                in_array($token, ['larva', 'telur', 'serangga'], true) => 'hama',
                in_array($token, ['kedelainya'], true) => 'kedelai',
                in_array($token, ['jagng'], true) => 'jagung',
                in_array($token, ['inii'], true) => 'ini',
                in_array($token, ['pemeriksaannya', 'pemeriksaan'], true) => 'pemeriksaan',
                default => $token,
            };
        }

        return array_values(array_unique($expanded));
    }

    /**
     * @param  array<int, string>  $queryTerms
     * @param  array<string, mixed>  $chunk
     */
    private function score(array $queryTerms, array $chunk): float
    {
        $contentTerms = $this->terms($chunk['content']);
        $titleTerms = $this->terms($chunk['title']);
        $overlap = count(array_intersect($queryTerms, $contentTerms));
        $titleOverlap = count(array_intersect($queryTerms, $titleTerms));
        $score = $overlap + ($titleOverlap * 2);

        $phrase = mb_strtolower(trim(implode(' ', array_slice($queryTerms, 0, 3))), 'UTF-8');
        if ($phrase !== '' && str_contains(mb_strtolower($chunk['content'].' '.$chunk['title'], 'UTF-8'), $phrase)) {
            $score += 1.5;
        }

        $haystack = mb_strtolower($chunk['content'].' '.$chunk['title'], 'UTF-8');
        if (in_array('air', $queryTerms, true) && str_contains($haystack, 'pengairan')) {
            $score += 2.0;
        }
        if (in_array('pemeriksaan', $queryTerms, true) && str_contains($haystack, 'pemeriksaan')) {
            $score += 2.0;
        }

        // The static application-facts catalog contains image labels for
        // every crop activity. For advice questions, prefer the agronomy
        // guide that contains the procedure instead of the catalog entry.
        $agronomySignals = ['jagung', 'kedelai', 'air', 'kelembapan', 'hama', 'pemeriksaan', 'pupuk', 'tanah'];
        $applicationSignals = ['aplikasi', 'farmer', 'gambar', 'fitur', 'data', 'katalog', 'komoditas'];
        if (
            str_starts_with((string) ($chunk['id'] ?? ''), 'application-facts:')
            && array_intersect($queryTerms, $applicationSignals) !== []
        ) {
            $score += 3.0;
        }
        if (
            str_starts_with((string) ($chunk['id'] ?? ''), 'demo-agronomy-guides:')
            && array_intersect($queryTerms, $agronomySignals) !== []
            && array_intersect($queryTerms, $applicationSignals) === []
        ) {
            $score += 2.5;
        }

        return $score / max(1, count($queryTerms));
    }
}
