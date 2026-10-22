<?php

namespace Tests\Unit\AI;

use App\AI\Services\FinanceImageRenderer;
use Tests\TestCase;

class FinanceImageRendererTest extends TestCase
{
    public function test_render_keeps_exact_finance_values_in_svg_text_and_accessible_description(): void
    {
        $svg = (new FinanceImageRenderer)->render([
            'requested' => true,
            'available' => true,
            'year' => 2026,
            'month' => 'Apr',
            'month_label' => 'April',
            'income' => 11000000,
            'expense' => 4000000,
            'net' => 7000000,
            'transaction_count' => 4,
            'period' => 'Januari–Desember 2026 (demo origin/dev)',
        ]);

        $this->assertStringStartsWith('<svg ', $svg);
        $this->assertStringContainsString('Laporan Keuangan April 2026', $svg);
        $this->assertStringContainsString('Pemasukan Rp11.000.000', $svg);
        $this->assertStringContainsString('pengeluaran Rp4.000.000', $svg);
        $this->assertStringContainsString('laba bersih Rp7.000.000', $svg);
        $this->assertStringContainsString('>Rp11.000.000<', $svg);
        $this->assertStringContainsString('>Rp4.000.000<', $svg);
        $this->assertStringContainsString('>Rp7.000.000<', $svg);
    }

    public function test_render_rejects_unavailable_periods(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FinanceImageRenderer)->render([
            'available' => false,
            'income' => null,
            'expense' => null,
            'net' => null,
        ]);
    }
}
