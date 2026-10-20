<?php

namespace Tests\Unit\AI;

use App\AI\Contracts\EmbeddingGateway;
use App\AI\Contracts\VectorStore;
use App\AI\Services\VectorKnowledgeRetriever;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VectorKnowledgeRetrieverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_embeds_the_prompt_filters_by_team_and_returns_grounded_payload(): void
    {
        $embedding = new class implements EmbeddingGateway
        {
            public function embed(array $inputs): array
            {
                return [
                    'vectors' => [[0.1, 0.2, 0.3]],
                    'usage' => ['total_tokens' => 5],
                    'model' => 'fake',
                ];
            }
        };
        $store = new class implements VectorStore
        {
            /** @var array<string, mixed> */
            public array $filter = [];

            public function ensureCollection(int $dimensions): void {}

            public function upsert(array $points): void {}

            public function query(array $vector, int $limit, array $filter = []): array
            {
                $this->filter = $filter;

                return [[
                    'id' => 'point-1',
                    'score' => 0.88,
                    'payload' => [
                        'chunk_id' => 'demo-agronomy-guides:chunk:0',
                        'document_id' => 'demo-agronomy-guides',
                        'title' => 'Panduan kedelai',
                        'source' => 'demo',
                        'content' => 'Periksa tanah sebelum menanam kedelai.',
                        'team_id' => 12,
                        'status' => 'published',
                    ],
                ]];
            }
        };
        $team = Team::factory()->create();

        $results = (new VectorKnowledgeRetriever($embedding, $store))->retrieve('cara menanam kedelai', $team);

        $this->assertSame('demo-agronomy-guides', $results[0]['metadata']['document_id']);
        $this->assertSame('qdrant', $results[0]['metadata']['retrieval']);
        $this->assertSame('fake', $results[0]['metadata']['embedding_model']);
        $this->assertSame(5, $results[0]['metadata']['embedding_tokens']);
        $this->assertSame($team->id, data_get($store->filter, 'must.0.match.value'));
        $this->assertSame('published', data_get($store->filter, 'must.1.match.value'));
    }
}
