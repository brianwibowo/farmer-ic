<?php

namespace App\AI\Services;

use App\AI\Contracts\EmbeddingGateway;
use App\AI\Contracts\KnowledgeRetriever;
use App\AI\Contracts\VectorStore;
use App\Models\Team;

class VectorKnowledgeRetriever implements KnowledgeRetriever
{
    public function __construct(
        private readonly EmbeddingGateway $embeddings,
        private readonly VectorStore $vectors,
        private readonly ?HybridKnowledgeReranker $reranker = null,
    ) {}

    public function retrieve(string $query, Team $team, int $limit = 4): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $embedded = $this->embeddings->embed([$query]);
        $vector = $embedded['vectors'][0] ?? [];
        if ($vector === []) {
            return [];
        }

        $candidateLimit = max(
            $limit * 3,
            (int) config('ai.rag.vector_candidate_k', 12),
        );
        $rows = $this->vectors->query($vector, $candidateLimit, [
            'must' => [
                ['key' => 'team_id', 'match' => ['value' => $team->getKey()]],
                ['key' => 'status', 'match' => ['value' => 'published']],
            ],
        ]);

        $minimum = (float) config('ai.rag.vector_min_score', config('ai.rag.min_score', 0.22));
        $candidates = [];
        foreach ($rows as $row) {
            $payload = $row['payload'];
            $score = $row['score'];
            $content = trim((string) ($payload['content'] ?? ''));
            if ($content === '' || $score < $minimum) {
                continue;
            }

            $candidates[] = [
                ...$row,
                'score' => round($score, 4),
            ];
        }

        $ranked = ($this->reranker ?? new HybridKnowledgeReranker)->rerank($query, $candidates, $limit);
        $results = [];
        foreach ($ranked as $row) {
            $payload = $row['payload'];
            $content = trim((string) ($payload['content'] ?? ''));
            $results[] = [
                'id' => (string) ($payload['chunk_id'] ?? $row['id']),
                'title' => (string) ($payload['title'] ?? 'Knowledge base'),
                'source' => (string) ($payload['source'] ?? 'Knowledge base Farmer IC'),
                'content' => $content,
                'score' => $row['hybrid_score'],
                'metadata' => [
                    'document_id' => $payload['document_id'] ?? null,
                    'version' => $payload['version'] ?? null,
                    'team_id' => $payload['team_id'] ?? $team->getKey(),
                    'retrieval' => 'qdrant',
                    'index_version' => $payload['index_version'] ?? config('ai.rag.index_version', 'embed-v1-chunk-v1'),
                    'embedding_model' => $embedded['model'],
                    'embedding_tokens' => (int) ($embedded['usage']['total_tokens'] ?? 0),
                    'embedding_cache_hits' => (int) ($embedded['usage']['cache_hits'] ?? 0),
                    'embedding_cache_misses' => (int) ($embedded['usage']['cache_misses'] ?? 0),
                    'vector_score' => $row['vector_score'],
                    'lexical_score' => $row['lexical_score'],
                    'hybrid_score' => $row['hybrid_score'],
                    'rerank' => 'hybrid-v1',
                ],
            ];
        }

        return $results;
    }
}
