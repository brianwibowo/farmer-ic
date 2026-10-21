<?php

namespace Tests\Unit\AI;

use App\AI\Contracts\KnowledgeRetriever;
use App\AI\Services\AiIntentRouter;
use App\AI\Services\AiQualityGate;
use App\Models\Team;
use Tests\TestCase;

class AiQualityGateTest extends TestCase
{
    public function test_percentile_uses_nearest_observed_rank_for_latency_gate(): void
    {
        $gate = new AiQualityGate(new AiIntentRouter);

        $this->assertSame(40.0, $gate->percentile([10, 20, 30, 40], 0.95));
        $this->assertSame(20.0, $gate->percentile([10, 20, 30, 40], 0.50));
        $this->assertSame(0.0, $gate->percentile([], 0.95));
    }

    public function test_fallback_chain_rejects_duplicate_or_empty_provider_candidates(): void
    {
        $gate = new AiQualityGate(new AiIntentRouter);

        $this->assertFalse($gate->validateFallbackChain(['primary', 'primary'])['valid']);
        $this->assertFalse($gate->validateFallbackChain(['', '  '])['valid']);
        $this->assertSame(
            ['primary', 'fallback'],
            $gate->validateFallbackChain([' primary ', 'fallback'])['candidates'],
        );
    }

    public function test_it_validates_indonesian_route_variants_without_provider_call(): void
    {
        $gate = new AiQualityGate(new AiIntentRouter);
        $report = $gate->evaluateRouting([
            [
                'id' => 'finance-paraphrase',
                'prompt' => 'laporan keuangan kebun sekarang ada?',
                'expected_route' => [
                    'intent' => 'llm', 'domain' => 'finance', 'period' => 'current',
                    'reason' => 'finance_analysis',
                ],
            ],
            [
                'id' => 'corn-typo',
                'prompt' => 'pemupukan jagng sesuai fase gimana ya',
                'expected_route' => [
                    'intent' => 'llm', 'domain' => 'agronomy', 'reason' => 'agriculture_guidance',
                ],
            ],
            [
                'id' => 'irrigation-stt',
                'prompt' => 'tanah kebun saya kering, irigasinya perlu seperti apa',
                'expected_route' => [
                    'intent' => 'llm', 'domain' => 'garden', 'reason' => 'agriculture_guidance',
                ],
            ],
        ]);

        $this->assertSame(1.0, $report['accuracy']);
        $this->assertSame(3, $report['checked']);
        $this->assertSame([], $report['failures']);
    }

    public function test_it_rejects_missing_and_out_of_range_citations(): void
    {
        $gate = new AiQualityGate(new AiIntentRouter);
        $valid = $gate->validateCitations("Jawaban berbasis sumber [S1].\n\nSumber: [S1] Panduan.", 2);
        $missing = $gate->validateCitations('Jawaban tanpa sumber.', 2);
        $outOfRange = $gate->validateCitations("Jawaban [S3].\n\nSumber: [S3] Invented.", 2);

        $this->assertTrue($valid['valid']);
        $this->assertFalse($missing['valid']);
        $this->assertContains('missing_citation', $missing['errors']);
        $this->assertFalse($outOfRange['valid']);
        $this->assertContains('citation_out_of_range:S3', $outOfRange['errors']);
    }

    public function test_it_reports_retrieval_accuracy_and_p95_without_network(): void
    {
        $retriever = new class implements KnowledgeRetriever
        {
            public function retrieve(string $query, Team $team, int $limit = 4): array
            {
                return [[
                    'id' => 'chunk-1',
                    'content' => 'Panduan pemupukan jagung sesuai fase.',
                    'score' => 0.9,
                    'metadata' => ['document_id' => 'demo-agronomy-guides', 'retrieval' => 'fake'],
                ]];
            }
        };
        $gate = new AiQualityGate(new AiIntentRouter);
        $report = $gate->evaluateRetrieval(Team::factory()->make(), $retriever, [[
            'id' => 'corn',
            'prompt' => 'pemupukan jagung sesuai fase',
            'expected_document' => 'demo-agronomy-guides',
            'expected_terms' => ['jagung'],
        ]]);

        $this->assertSame(1.0, $report['hit_at_1']);
        $this->assertSame(1.0, $report['accuracy']);
        $this->assertGreaterThanOrEqual(0.0, $report['p95_latency_ms']);
        $this->assertSame(1, $report['checked']);
    }
}
