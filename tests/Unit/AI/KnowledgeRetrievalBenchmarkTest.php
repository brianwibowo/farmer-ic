<?php

namespace Tests\Unit\AI;

use App\AI\Contracts\EmbeddingGateway;
use App\AI\Contracts\VectorStore;
use App\AI\Services\InMemoryKnowledgeRetriever;
use App\AI\Services\KnowledgeChunker;
use App\AI\Services\KnowledgeRetrievalBenchmark;
use App\AI\Services\KnowledgeService;
use App\AI\Services\VectorKnowledgeRetriever;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeRetrievalBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_hit_rate_and_latency_for_both_retrievers(): void
    {
        $embedding = new class implements EmbeddingGateway
        {
            public function embed(array $inputs): array
            {
                return ['vectors' => [[0.1, 0.2, 0.3]], 'usage' => [], 'model' => 'fake'];
            }
        };
        $store = new class implements VectorStore
        {
            public function ensureCollection(int $dimensions): void {}

            public function upsert(array $points): void {}

            public function query(array $vector, int $limit, array $filter = []): array
            {
                return [[
                    'id' => 'point-1',
                    'score' => 0.9,
                    'payload' => [
                        'chunk_id' => 'demo:chunk:0',
                        'document_id' => 'demo-agronomy-guides',
                        'content' => 'Panduan kedelai: periksa kondisi tanah.',
                    ],
                ]];
            }
        };
        $benchmark = new KnowledgeRetrievalBenchmark(
            new InMemoryKnowledgeRetriever(new KnowledgeService, new KnowledgeChunker),
            new VectorKnowledgeRetriever($embedding, $store),
        );

        $report = $benchmark->run(Team::factory()->create(), [[
            'prompt' => 'cara nanam kedelai',
            'expected_document' => 'demo-agronomy-guides',
            'expected_terms' => ['kedelai'],
        ]]);

        $this->assertSame(1, $report['summary']['cases']);
        $this->assertSame(1.0, $report['summary']['vector_hit_at_1']);
        $this->assertArrayHasKey('in_memory_mean_latency_ms', $report['summary']);
        $this->assertArrayHasKey('in_memory_p95_latency_ms', $report['summary']);
        $this->assertArrayHasKey('vector_p95_latency_ms', $report['summary']);
        $this->assertArrayHasKey('vector', $report['cases'][0]);
    }
}
