<?php

namespace Tests\Unit\AI;

use App\AI\Services\AiIntentRouter;
use PHPUnit\Framework\TestCase;

class AiIntentRouterTest extends TestCase
{
    private AiIntentRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new AiIntentRouter;
    }

    public function test_current_finance_is_sent_to_grounded_llm(): void
    {
        $decision = $this->router->decide('finansialnya bulan ini');

        $this->assertSame('llm', $decision->intent);
        $this->assertSame('finance_analysis', $decision->reason);
        $this->assertSame('finance', $decision->domain);
    }

    public function test_finance_report_paraphrase_with_sekarang_uses_grounded_llm(): void
    {
        $decision = $this->router->decide('laporan keuangan kebun sekarang ada?');

        $this->assertSame('llm', $decision->intent);
        $this->assertSame('finance_analysis', $decision->reason);
        $this->assertSame('current', $decision->period);
    }

    public function test_english_finance_month_lookup_keeps_the_finance_domain(): void
    {
        $decision = $this->router->decide('finance report April 2026');

        $this->assertSame('finance', $decision->domain);
        $this->assertSame('historical', $decision->period);
    }

    public function test_agronomy_guidance_is_eligible_for_retrieval(): void
    {
        $decision = $this->router->decide('bagaimana cara menanam kedelai');

        $this->assertSame('llm', $decision->intent);
        $this->assertSame('agronomy', $decision->domain);
        $this->assertSame('agriculture_guidance', $decision->reason);
    }

    public function test_explicit_visual_request_is_not_data_lookup(): void
    {
        $decision = $this->router->decide('gambarkan kondisi lahan jagung');

        $this->assertSame('infographic', $decision->intent);
        $this->assertSame('explicit_visual_request', $decision->reason);
    }

    public function test_short_non_greeting_requires_clarification(): void
    {
        $decision = $this->router->decide('tolong bantu');

        $this->assertTrue($decision->needsClarification);
    }
}
