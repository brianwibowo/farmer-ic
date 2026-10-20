<?php

namespace Tests\Unit\AI;

use App\AI\Services\InMemoryKnowledgeRetriever;
use App\AI\Services\KnowledgeChunker;
use App\AI\Services\KnowledgeService;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InMemoryKnowledgeRetrieverTest extends TestCase
{
    use RefreshDatabase;

    public function test_retrieval_returns_relevant_static_finance_guardrail(): void
    {
        $team = Team::factory()->create();
        $retriever = new InMemoryKnowledgeRetriever(new KnowledgeService, new KnowledgeChunker);

        $results = $retriever->retrieve('data finansial bulan ini', $team);

        $this->assertNotEmpty($results);
        $match = collect($results)->first(
            static fn (array $result): bool => str_contains(strtolower((string) $result['content']), 'finansial'),
        );
        $this->assertNotNull($match);
        $this->assertStringStartsWith('application-facts:chunk:', (string) $match['id']);
    }

    public function test_retrieval_ignores_unrelated_query(): void
    {
        $team = Team::factory()->create();
        $retriever = new InMemoryKnowledgeRetriever(new KnowledgeService, new KnowledgeChunker);

        $results = $retriever->retrieve('resep masakan rendang', $team);

        $this->assertSame([], $results);
    }

    public function test_retrieval_finds_demo_agronomy_guide_for_paraphrased_query(): void
    {
        $team = Team::factory()->create();
        $retriever = new InMemoryKnowledgeRetriever(new KnowledgeService, new KnowledgeChunker);

        $results = $retriever->retrieve('cara nanam kedelai dari awal', $team);

        $this->assertNotEmpty($results);
        $this->assertSame('demo-agronomy-guides', $results[0]['metadata']['document_id']);
        $this->assertStringContainsString('kedelai', strtolower((string) $results[0]['content']));
    }
}
