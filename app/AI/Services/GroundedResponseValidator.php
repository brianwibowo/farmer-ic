<?php

namespace App\AI\Services;

use App\AI\Data\GroundedResponseValidation;

class GroundedResponseValidator
{
    /** @var array<string, string> */
    private const MONTH_ALIASES = [
        'januari' => 'Jan', 'jan' => 'Jan',
        'februari' => 'Feb', 'feb' => 'Feb',
        'maret' => 'Mar', 'mar' => 'Mar',
        'april' => 'Apr', 'apr' => 'Apr',
        'mei' => 'Mei',
        'juni' => 'Jun', 'jun' => 'Jun',
        'juli' => 'Jul', 'jul' => 'Jul',
        'agustus' => 'Agu', 'agu' => 'Agu', 'agt' => 'Agu',
        'september' => 'Sep', 'sep' => 'Sep',
        'oktober' => 'Okt', 'okt' => 'Okt',
        'november' => 'Nov', 'nov' => 'Nov',
        'desember' => 'Des', 'des' => 'Des',
    ];

    /**
     * Validate citation markers in a provider response before the source
     * footer is appended. A footer citation alone must not make an otherwise
     * ungrounded answer look valid.
     *
     * @param  array<int, array<string, mixed>>  $evidence
     */
    public function validate(string $content, array $evidence): GroundedResponseValidation
    {
        $issues = [];
        $citations = [];

        if (trim($content) === '') {
            $issues[] = 'response_empty';
        }

        if ($evidence !== []) {
            preg_match_all('/(?<![A-Za-z0-9])\[S(\d+)\]/i', $content, $matches);
            $citations = array_values(array_unique(array_map(
                static fn (string $value): int => (int) $value,
                $matches[1],
            )));

            if ($citations === []) {
                $issues[] = 'citation_missing';
            }

            $maxCitation = count($evidence);
            $hasInvalidCitation = collect($citations)->contains(
                static fn (int $citation): bool => $citation < 1 || $citation > $maxCitation,
            );

            if ($hasInvalidCitation) {
                $issues[] = 'citation_invalid';
            }
        }

        return new GroundedResponseValidation(
            ok: $issues === [],
            citations: $citations,
            issues: array_values(array_unique($issues)),
        );
    }

    /**
     * Reconcile finance numbers in provider output against the canonical
     * snapshot selected by the application. LLM output is presentation only;
     * it is never allowed to change the values used by the product.
     *
     * The extractor deliberately recognises only amounts adjacent to a
     * finance label. This avoids treating unrelated years, land sizes, or
     * agronomy measurements as financial claims. A response without a
     * labelled amount is left alone so narrative-only finance answers remain
     * possible; a labelled amount must match exactly.
     *
     * @param  array<string, mixed>  $period
     */
    public function validateFinance(string $content, array $period): GroundedResponseValidation
    {
        $issues = [];
        $claims = $this->extractFinanceClaims($content);
        $expected = [
            'income' => is_numeric($period['income'] ?? null) ? (int) $period['income'] : null,
            'expense' => is_numeric($period['expense'] ?? null) ? (int) $period['expense'] : null,
            'net' => is_numeric($period['net'] ?? null) ? (int) $period['net'] : null,
        ];
        $mismatches = [];

        foreach ($claims as $field => $claim) {
            $expectedValue = $expected[$field] ?? null;
            if ($expectedValue === null || (int) $claim['value'] === $expectedValue) {
                continue;
            }

            $issues[] = 'finance_'.$field.'_mismatch';
            $mismatches[$field] = [
                'expected' => $expectedValue,
                'actual' => (int) $claim['value'],
                'raw' => $claim['raw'],
            ];
        }

        $requestedYear = is_numeric($period['year'] ?? null) ? (int) $period['year'] : null;
        $mentionedYears = $this->mentionedYears($content);
        if ($requestedYear !== null && $mentionedYears !== [] && ! in_array($requestedYear, $mentionedYears, true)) {
            $issues[] = 'finance_period_mismatch';
            $mismatches['period'] = [
                'expected_year' => $requestedYear,
                'mentioned_years' => $mentionedYears,
            ];
        }

        $requestedMonth = is_string($period['month'] ?? null) ? $period['month'] : null;
        $mentionedPeriods = str_starts_with((string) $requestedMonth, 'Q')
            ? $this->mentionedQuarters($content)
            : $this->mentionedMonths($content);
        if ($requestedMonth !== null && $mentionedPeriods !== [] && ! in_array($requestedMonth, $mentionedPeriods, true)) {
            $issues[] = 'finance_period_mismatch';
            $mismatches['period'] = [
                ...((array) ($mismatches['period'] ?? [])),
                'expected_month' => $requestedMonth,
                'mentioned_periods' => $mentionedPeriods,
            ];
        }

        $numeric = [
            'checked' => $claims !== [],
            'expected' => $expected,
            'claims' => $claims,
            'mismatches' => $mismatches,
            'period' => [
                'requested' => (bool) ($period['requested'] ?? false),
                'year' => $requestedYear,
                'month' => $period['month'] ?? null,
            ],
        ];

        return new GroundedResponseValidation(
            ok: $issues === [],
            issues: array_values(array_unique($issues)),
            numeric: $numeric,
        );
    }

    /**
     * Replace an invalid grounded answer with a short, honest abstention when
     * strict citation validation is enabled. The default remains opt-in so
     * existing deployments can observe validation before changing behavior.
     *
     * @param  array<int, array<string, mixed>>  $evidence
     */
    public function guard(string $content, array $evidence): string
    {
        $validation = $this->validate($content, $evidence);

        if ($validation->ok || ! (bool) config('ai.rag.require_citations', false)) {
            return trim($content);
        }

        return 'Saya belum bisa memastikan jawaban dari sumber yang tersedia. '
            .'Silakan beri konteks tambahan atau pilih panduan yang lebih spesifik.';
    }

    /**
     * @return array<string, array{value: int, raw: string, label: string}>
     */
    private function extractFinanceClaims(string $content): array
    {
        $labels = [
            'income' => 'pemasukan|pendapatan|revenue|income',
            'expense' => 'pengeluaran|biaya|expense|expenses',
            'net' => 'laba(?:\s+bersih)?|laba\\/rugi(?:\s+bersih)?|keuntungan|net(?:\s+profit)?',
        ];
        $claims = [];

        foreach ($labels as $field => $labelPattern) {
            $pattern = '~\b(?<label>'.$labelPattern.')\b[^\d\r\n]{0,32}(?<amount>(?:Rp\s*)?[-+]?\d[\d.,\s]*(?:\s*(?:ribu|rb|juta|jt|miliar|m))?)~iu';
            if (! preg_match($pattern, $content, $match)) {
                continue;
            }

            $value = $this->parseAmount((string) $match['amount']);
            if ($value === null) {
                continue;
            }

            $claims[$field] = [
                'value' => $value,
                'raw' => trim((string) $match['amount']),
                'label' => trim((string) $match['label']),
            ];
        }

        return $claims;
    }

    private function parseAmount(string $raw): ?int
    {
        $value = trim(str_ireplace(['rp', "\u{00A0}"], ['', ' '], $raw));
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $multiplier = 1.0;
        if (preg_match('/\b(miliar|juta|jt|ribu|rb|m)\b/iu', $value, $modifier)) {
            $multiplier = match (mb_strtolower($modifier[1], 'UTF-8')) {
                'miliar' => 1_000_000_000,
                'juta', 'jt', 'm' => 1_000_000,
                'ribu', 'rb' => 1_000,
                default => 1.0,
            };
            $value = trim((string) preg_replace('/\s*(?:miliar|juta|jt|ribu|rb|m)\b.*/iu', '', $value));
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        if ($value === '' || ! preg_match('/\d/', $value)) {
            return null;
        }

        // Indonesian thousands separators are dots, while a comma can be a
        // decimal separator in compact forms such as "11,5 juta". Preserve
        // a one/two-digit decimal tail and strip grouping punctuation.
        $number = preg_replace('/[^\d.,]/', '', $value) ?? '';
        if ($number === '') {
            return null;
        }
        if (str_contains($number, ',') && str_contains($number, '.')) {
            $lastComma = strrpos($number, ',');
            $lastDot = strrpos($number, '.');
            if ($lastComma > $lastDot) {
                $number = str_replace('.', '', $number);
                $number = str_replace(',', '.', $number);
            } else {
                $number = str_replace(',', '', $number);
            }
        } elseif (str_contains($number, ',')) {
            $parts = explode(',', $number);
            $number = count($parts) === 2 && strlen($parts[1]) <= 2
                ? $parts[0].'.'.$parts[1]
                : implode('', $parts);
        } elseif (str_contains($number, '.')) {
            $parts = explode('.', $number);
            $number = count($parts) === 2 && strlen($parts[1]) <= 2
                ? $parts[0].'.'.$parts[1]
                : implode('', $parts);
        }

        if (! is_numeric($number)) {
            return null;
        }

        $parsed = (int) round(((float) $number) * $multiplier);

        return $negative ? -$parsed : $parsed;
    }

    /** @return array<int, int> */
    private function mentionedYears(string $content): array
    {
        preg_match_all('/\b(20\d{2})\b/', $content, $matches);

        return array_values(array_unique(array_map(static fn (string $year): int => (int) $year, $matches[1])));
    }

    /** @return array<int, string> */
    private function mentionedMonths(string $content): array
    {
        $pattern = implode('|', array_map(static fn (string $month): string => preg_quote($month, '/'), array_keys(self::MONTH_ALIASES)));
        preg_match_all('/\b('.$pattern.')\b/iu', $content, $matches);

        return array_values(array_unique(array_map(
            static fn (string $month): string => self::MONTH_ALIASES[mb_strtolower($month, 'UTF-8')],
            $matches[1] ?? [],
        )));
    }

    /** @return array<int, string> */
    private function mentionedQuarters(string $content): array
    {
        preg_match_all('/\b(?:q(?:uarter)?|kuartal)\s*([1-4])\b/iu', $content, $matches);

        return array_values(array_unique(array_map(
            static fn (string $quarter): string => 'Q'.$quarter,
            $matches[1],
        )));
    }
}
