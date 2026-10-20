<?php

namespace Tests\Unit\AI;

use App\AI\Services\HybridKnowledgeReranker;
use Tests\TestCase;

class HybridKnowledgeRerankerTest extends TestCase
{
    public function test_finance_paraphrase_promotes_application_facts_over_a_semantically_close_agronomy_chunk(): void
    {
        $rows = [
            [
                'id' => 'agronomy:chunk:0',
                'score' => 0.3792,
                'payload' => [
                    'document_id' => 'demo-agronomy-guides',
                    'title' => 'Panduan agronomi demo',
                    'content' => 'Periksa kondisi kebun, kelembapan tanah, dan fase tanaman.',
                ],
            ],
            [
                'id' => 'facts:chunk:1',
                'score' => 0.3525,
                'payload' => [
                    'document_id' => 'application-facts',
                    'title' => 'Fakta aplikasi Farmer IC',
                    'content' => 'Data finansial bulan ini belum tersedia sebagai financial statement.',
                ],
            ],
        ];

        $results = (new HybridKnowledgeReranker)->rerank(
            'laporan keuangan kebun sekarang ada?',
            $rows,
            2,
        );

        $this->assertSame('application-facts', $results[0]['payload']['document_id']);
        $this->assertGreaterThan($results[1]['hybrid_score'], $results[0]['hybrid_score']);
        $this->assertLessThan($results[1]['vector_score'], $results[0]['vector_score']);
        $this->assertSame('demo-agronomy-guides', $results[1]['payload']['document_id']);
    }

    public function test_single_agriculture_cost_term_does_not_apply_finance_prior(): void
    {
        $rows = [
            [
                'id' => 'agronomy:chunk:0',
                'score' => 0.60,
                'payload' => [
                    'document_id' => 'demo-agronomy-guides',
                    'title' => 'Pemupukan jagung',
                    'content' => 'Catat biaya pupuk dan fase tanaman sebelum aplikasi.',
                ],
            ],
            [
                'id' => 'facts:chunk:1',
                'score' => 0.59,
                'payload' => [
                    'document_id' => 'application-facts',
                    'title' => 'Fakta aplikasi Farmer IC',
                    'content' => 'Data finansial bulan ini belum tersedia.',
                ],
            ],
        ];

        $results = (new HybridKnowledgeReranker)->rerank('biaya pupuk jagung', $rows, 2);

        $this->assertSame('demo-agronomy-guides', $results[0]['payload']['document_id']);
    }
}
