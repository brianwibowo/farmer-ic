<?php

namespace App\AI\Services;

use InvalidArgumentException;

/**
 * Renders finance figures as a local SVG so exact numbers do not depend on a
 * generative image model's ability to draw text. The SVG is an image artifact
 * and can be attached through the same public storage path as provider images.
 */
class FinanceImageRenderer
{
    /**
     * @param array{
     *     requested: bool,
     *     available: bool,
     *     year: int|null,
     *     month: string|null,
     *     month_label: string|null,
     *     income: int|null,
     *     expense: int|null,
     *     net: int|null,
     *     transaction_count: int,
     *     period: string
     * } $period
     */
    public function render(array $period): string
    {
        if (! $period['available']) {
            throw new InvalidArgumentException('Cannot render an unavailable finance period.');
        }

        foreach (['income', 'expense', 'net'] as $key) {
            if (! is_int($period[$key] ?? null)) {
                throw new InvalidArgumentException("Finance period is missing {$key}.");
            }
        }

        $scope = $this->scope($period);
        $title = $this->escape('Laporan Keuangan '.$scope);
        $subtitle = $this->escape('Data finance terverifikasi Farmer IC');
        $income = $this->escape($this->formatRupiah($period['income']));
        $expense = $this->escape($this->formatRupiah($period['expense']));
        $net = $this->escape($this->formatRupiah($period['net']));
        $transactionCount = $this->escape((string) $period['transaction_count']);
        $netColor = $period['net'] >= 0 ? '#146c43' : '#b42318';
        $escapedNetColor = $this->escape($netColor);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800" viewBox="0 0 1200 800" role="img" aria-labelledby="title description">
  <title id="title">{$title}</title>
  <desc id="description">{$subtitle}. Pemasukan {$income}, pengeluaran {$expense}, laba bersih {$net}.</desc>
  <rect width="1200" height="800" rx="36" fill="#f8faf8"/>
  <rect x="40" y="40" width="1120" height="720" rx="28" fill="#ffffff" stroke="#d7e3da" stroke-width="2"/>
  <circle cx="104" cy="114" r="34" fill="#e5f5e8"/>
  <path d="M86 128h36M92 118h24M98 108h12" stroke="#146c43" stroke-width="7" stroke-linecap="round"/>
  <text x="160" y="108" fill="#173b29" font-family="Arial, sans-serif" font-size="36" font-weight="700">{$title}</text>
  <text x="160" y="148" fill="#587063" font-family="Arial, sans-serif" font-size="22">{$subtitle}</text>
  <line x1="80" y1="190" x2="1120" y2="190" stroke="#d7e3da" stroke-width="2"/>

  <g transform="translate(80 240)">
    <rect width="320" height="250" rx="24" fill="#effaf1" stroke="#bce6c5" stroke-width="2"/>
    <circle cx="54" cy="60" r="28" fill="#c9efd1"/>
    <path d="M54 76V44M42 56h24" stroke="#146c43" stroke-width="6" stroke-linecap="round"/>
    <text x="38" y="122" fill="#587063" font-family="Arial, sans-serif" font-size="22">PEMASUKAN</text>
    <text x="38" y="180" fill="#173b29" font-family="Arial, sans-serif" font-size="31" font-weight="700">{$income}</text>
    <text x="38" y="218" fill="#587063" font-family="Arial, sans-serif" font-size="18">Pendapatan tercatat</text>
  </g>

  <g transform="translate(440 240)">
    <rect width="320" height="250" rx="24" fill="#fff7ed" stroke="#f4d6ad" stroke-width="2"/>
    <circle cx="54" cy="60" r="28" fill="#ffe4bf"/>
    <path d="M42 60h24" stroke="#a05a00" stroke-width="6" stroke-linecap="round"/>
    <text x="38" y="122" fill="#765c3c" font-family="Arial, sans-serif" font-size="22">PENGELUARAN</text>
    <text x="38" y="180" fill="#5b3a13" font-family="Arial, sans-serif" font-size="31" font-weight="700">{$expense}</text>
    <text x="38" y="218" fill="#765c3c" font-family="Arial, sans-serif" font-size="18">Biaya tercatat</text>
  </g>

  <g transform="translate(800 240)">
    <rect width="320" height="250" rx="24" fill="#eef5ff" stroke="#c9dcfa" stroke-width="2"/>
    <circle cx="54" cy="60" r="28" fill="#d7e6ff"/>
    <path d="M40 68l14-16 12 9 14-17" fill="none" stroke="#2058a6" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
    <text x="38" y="122" fill="#536985" font-family="Arial, sans-serif" font-size="22">LABA BERSIH</text>
    <text x="38" y="180" fill="{$escapedNetColor}" font-family="Arial, sans-serif" font-size="31" font-weight="700">{$net}</text>
    <text x="38" y="218" fill="#536985" font-family="Arial, sans-serif" font-size="18">Pemasukan − pengeluaran</text>
  </g>

  <g transform="translate(80 550)">
    <rect width="1040" height="130" rx="22" fill="#f4f8f5"/>
    <text x="36" y="52" fill="#587063" font-family="Arial, sans-serif" font-size="20">PERIODE</text>
    <text x="36" y="92" fill="#173b29" font-family="Arial, sans-serif" font-size="27" font-weight="700">{$title}</text>
    <text x="820" y="52" fill="#587063" font-family="Arial, sans-serif" font-size="20">TRANSAKSI</text>
    <text x="820" y="92" fill="#173b29" font-family="Arial, sans-serif" font-size="27" font-weight="700">{$transactionCount}</text>
  </g>
  <text x="80" y="728" fill="#587063" font-family="Arial, sans-serif" font-size="17">Sumber: farmDemoData.json · Angka dihitung dari transaksi pada periode yang dipilih.</text>
</svg>
SVG;
    }

    /** @param array<string, mixed> $period */
    protected function scope(array $period): string
    {
        if (($period['month_label'] ?? null) !== null && ($period['year'] ?? null) !== null) {
            return (string) $period['month_label'].' '.(int) $period['year'];
        }

        if (($period['year'] ?? null) !== null) {
            return 'Tahun '.(int) $period['year'];
        }

        return (string) ($period['period'] ?? 'Periode finance');
    }

    protected function formatRupiah(int $value): string
    {
        $sign = $value < 0 ? '-' : '';

        return $sign.'Rp'.number_format(abs($value), 0, ',', '.');
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
