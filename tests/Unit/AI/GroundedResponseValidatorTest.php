<?php

namespace Tests\Unit\AI;

use App\AI\Services\GroundedResponseValidator;
use Tests\TestCase;

class GroundedResponseValidatorTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    private function evidence(): array
    {
        return [
            ['id' => 'soybean:0', 'source' => 'Panduan kedelai'],
            ['id' => 'soil:0', 'source' => 'Panduan tanah'],
        ];
    }

    public function test_valid_citation_points_to_retrieved_evidence(): void
    {
        $result = app(GroundedResponseValidator::class)->validate(
            'Periksa kelembapan tanah setelah hujan [S1].',
            $this->evidence(),
        );

        $this->assertTrue($result->ok);
        $this->assertSame([1], $result->citations);
        $this->assertSame([], $result->issues);
    }

    public function test_missing_or_unknown_citations_are_rejected(): void
    {
        $validator = app(GroundedResponseValidator::class);

        $missing = $validator->validate('Periksa kelembapan tanah.', $this->evidence());
        $unknown = $validator->validate('Gunakan jadwal ini [S3].', $this->evidence());

        $this->assertFalse($missing->ok);
        $this->assertContains('citation_missing', $missing->issues);
        $this->assertFalse($unknown->ok);
        $this->assertContains('citation_invalid', $unknown->issues);
    }

    public function test_strict_mode_abstains_without_exposing_ungrounded_text(): void
    {
        config(['ai.rag.require_citations' => true]);

        $guarded = app(GroundedResponseValidator::class)->guard(
            'Jawaban yang tidak memiliki sumber.',
            $this->evidence(),
        );

        $this->assertStringContainsString('belum bisa memastikan', $guarded);
        $this->assertStringNotContainsString('Jawaban yang tidak memiliki sumber', $guarded);
    }

    public function test_empty_evidence_does_not_require_a_citation(): void
    {
        config(['ai.rag.require_citations' => true]);

        $result = app(GroundedResponseValidator::class)->validate(
            'Jawaban umum untuk pertanyaan pengguna.',
            [],
        );

        $this->assertTrue($result->ok);
        $this->assertSame([], $result->issues);
    }

    public function test_finance_numbers_must_match_the_canonical_period(): void
    {
        $result = app(GroundedResponseValidator::class)->validateFinance(
            'April 2026: pemasukan Rp99.000.000, pengeluaran Rp1.000.000, laba bersih Rp98.000.000.',
            [
                'requested' => true,
                'available' => true,
                'year' => 2026,
                'month' => 'Apr',
                'month_label' => 'April',
                'income' => 11_000_000,
                'expense' => 4_000_000,
                'net' => 7_000_000,
            ],
        );

        $this->assertFalse($result->ok);
        $this->assertContains('finance_income_mismatch', $result->issues);
        $this->assertContains('finance_expense_mismatch', $result->issues);
        $this->assertContains('finance_net_mismatch', $result->issues);
        $this->assertSame(11_000_000, data_get($result->numeric, 'mismatches.income.expected'));
    }

    public function test_finance_amount_parser_accepts_rupiah_and_compact_juta_values(): void
    {
        $result = app(GroundedResponseValidator::class)->validateFinance(
            'Pemasukan Rp11.000.000, pengeluaran 4 juta, laba bersih Rp7.000.000.',
            [
                'requested' => true,
                'available' => true,
                'year' => 2026,
                'month' => 'Apr',
                'month_label' => 'April',
                'income' => 11_000_000,
                'expense' => 4_000_000,
                'net' => 7_000_000,
            ],
        );

        $this->assertTrue($result->ok);
        $this->assertTrue((bool) data_get($result->numeric, 'checked'));
    }

    public function test_finance_period_claim_is_checked_when_provider_mentions_another_month(): void
    {
        $result = app(GroundedResponseValidator::class)->validateFinance(
            'Laporan Maret 2026: pemasukan Rp11.000.000, pengeluaran Rp4.000.000, laba bersih Rp7.000.000.',
            [
                'requested' => true,
                'available' => true,
                'year' => 2026,
                'month' => 'Apr',
                'month_label' => 'April',
                'income' => 11_000_000,
                'expense' => 4_000_000,
                'net' => 7_000_000,
            ],
        );

        $this->assertFalse($result->ok);
        $this->assertContains('finance_period_mismatch', $result->issues);
    }
}
