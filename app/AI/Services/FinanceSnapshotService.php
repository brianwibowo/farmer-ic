<?php

namespace App\AI\Services;

use App\Models\AiKnowledgeDocument;
use App\Models\Team;
use Illuminate\Support\Facades\File;

/** Deterministic finance adapter backed by the origin/dev demo corpus. */
class FinanceSnapshotService
{
    /** @var array<string, string> */
    private const MONTH_ALIASES = [
        'januari' => 'Jan',
        'jan' => 'Jan',
        'februari' => 'Feb',
        'feb' => 'Feb',
        'maret' => 'Mar',
        'mar' => 'Mar',
        'april' => 'Apr',
        'apr' => 'Apr',
        'mei' => 'Mei',
        'juni' => 'Jun',
        'jun' => 'Jun',
        'juli' => 'Jul',
        'jul' => 'Jul',
        'agustus' => 'Agu',
        'agu' => 'Agu',
        'agt' => 'Agu',
        'september' => 'Sep',
        'sep' => 'Sep',
        'oktober' => 'Okt',
        'okt' => 'Okt',
        'november' => 'Nov',
        'nov' => 'Nov',
        'desember' => 'Des',
        'des' => 'Des',
    ];

    /** @var array<string, string> */
    private const MONTH_LABELS = [
        'Jan' => 'Januari',
        'Feb' => 'Februari',
        'Mar' => 'Maret',
        'Apr' => 'April',
        'Mei' => 'Mei',
        'Jun' => 'Juni',
        'Jul' => 'Juli',
        'Agu' => 'Agustus',
        'Sep' => 'September',
        'Okt' => 'Oktober',
        'Nov' => 'November',
        'Des' => 'Desember',
    ];

    /** @return array<string, mixed> */
    public function snapshot(Team $team): array
    {
        $document = AiKnowledgeDocument::query()
            ->where('team_id', $team->id)
            ->where('source', 'farmDemoData.json origin/dev')
            ->where('domain', 'finance')
            ->where('title', 'Finance demo origin/dev')
            ->where('status', AiKnowledgeDocument::STATUS_PUBLISHED)
            ->latest('version')
            ->first();
        $raw = $document ? json_decode((string) $document->content, true) : null;
        if (! is_array($raw) || ! array_key_exists('transactions', $raw)) {
            $path = config('ai.knowledge.demo_data_path', resource_path('js/data/farmDemoData.json'));
            $root = is_string($path) && File::exists($path)
                ? json_decode(File::get($path), true)
                : null;
            $raw = is_array($root) && is_array($root['finance'] ?? null)
                ? $root['finance']
                : null;
        }
        if (! is_array($raw) || ! array_key_exists('transactions', $raw)) {
            $path = resource_path('ai/knowledge/finance-demo-origin-dev.json');
            $raw = File::exists($path) ? json_decode(File::get($path), true) : [];
        }
        $raw = is_array($raw) ? $raw : [];
        $income = 0;
        $expense = 0;
        foreach ((array) ($raw['transactions'] ?? []) as $transaction) {
            $amount = (int) ($transaction['amount'] ?? 0);
            if (($transaction['type'] ?? null) === 'income') {
                $income += $amount;
            }
            if (($transaction['type'] ?? null) === 'expense') {
                $expense += $amount;
            }
        }
        $net = $income - $expense;

        return [
            'period' => 'Januari–Desember 2026 (demo origin/dev)',
            'income' => $income,
            'expense' => $expense,
            'net' => $net,
            'margin_percent' => $income > 0 ? round($net / $income * 100, 1) : 0,
            'quarters' => $raw['quarters'] ?? [],
            'transactions' => $raw['transactions'] ?? [],
            'transaction_count' => count((array) ($raw['transactions'] ?? [])),
            'source' => 'farmDemoData.json origin/dev',
            'calculation' => 'laba bersih = pemasukan - pengeluaran; margin = laba bersih / pemasukan',
        ];
    }

    /**
     * Resolve the period requested for a finance image and its verified values.
     *
     * @return array{
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
     * }
     */
    public function imagePeriod(Team $team, string $prompt): array
    {
        $requested = $this->parsePeriod($prompt);
        $snapshot = $this->snapshot($team);
        $transactions = $this->transactionRows($snapshot['transactions'] ?? []);
        $years = [];
        foreach ($transactions as $transaction) {
            $year = (int) data_get($transaction, 'date.year', 0);
            if ($year > 0) {
                $years[] = $year;
            }
        }
        $years = array_values(array_unique($years));

        if ($requested === null) {
            return [
                'requested' => false,
                'available' => true,
                'year' => null,
                'month' => null,
                'month_label' => null,
                'income' => (int) $snapshot['income'],
                'expense' => (int) $snapshot['expense'],
                'net' => (int) $snapshot['net'],
                'transaction_count' => (int) $snapshot['transaction_count'],
                'period' => (string) $snapshot['period'],
            ];
        }

        $year = $requested['year'] ?? (count($years) === 1 ? $years[0] : null);
        // A requested year is available only when it is explicitly present in
        // the transaction corpus. Treat an empty/malformed corpus as missing
        // data instead of presenting a fabricated zero-valued year.
        if ($year === null || ! in_array($year, $years, true)) {
            return $this->unavailableImagePeriod($requested['month'], $year, (string) $snapshot['period']);
        }

        if ($requested['month'] === null) {
            $yearTransactions = array_values(array_filter(
                $transactions,
                static fn (array $transaction): bool => (int) ($transaction['date']['year'] ?? 0) === $year,
            ));
            $income = $this->sumTransactions($yearTransactions, 'income');
            $expense = $this->sumTransactions($yearTransactions, 'expense');

            return [
                'requested' => true,
                'available' => true,
                'year' => $year,
                'month' => null,
                'month_label' => null,
                'income' => $income,
                'expense' => $expense,
                'net' => $income - $expense,
                'transaction_count' => count($yearTransactions),
                'period' => (string) $snapshot['period'],
            ];
        }

        $month = $requested['month'];
        $monthTransactions = array_values(array_filter(
            $transactions,
            fn (array $transaction): bool => (int) ($transaction['date']['year'] ?? 0) === $year
                && $this->canonicalMonth((string) ($transaction['date']['month'] ?? '')) === $month,
        ));
        $income = $this->sumTransactions($monthTransactions, 'income');
        $expense = $this->sumTransactions($monthTransactions, 'expense');

        if ($monthTransactions === []) {
            return $this->unavailableImagePeriod($month, $year, (string) $snapshot['period']);
        }

        return [
            'requested' => true,
            'available' => true,
            'year' => $year,
            'month' => $month,
            'month_label' => self::MONTH_LABELS[$month],
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
            'transaction_count' => count($monthTransactions),
            'period' => (string) $snapshot['period'],
        ];
    }

    /**
     * Resolve a finance period for both image and text validation. The demo
     * corpus has quarter totals in addition to transaction rows, so quarter
     * requests must use those canonical totals instead of falling back to the
     * annual snapshot.
     *
     * @return array{
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
     * }
     */
    public function periodForPrompt(Team $team, string $prompt): array
    {
        $quarter = $this->parseQuarter($prompt);
        if ($quarter === null) {
            return $this->imagePeriod($team, $prompt);
        }

        $snapshot = $this->snapshot($team);
        $transactions = $this->transactionRows($snapshot['transactions'] ?? []);
        $years = [];
        foreach ($transactions as $transaction) {
            $year = (int) data_get($transaction, 'date.year', 0);
            if ($year > 0) {
                $years[] = $year;
            }
        }
        $years = array_values(array_unique($years));
        $year = $quarter['year'] ?? (count($years) === 1 ? $years[0] : null);
        $quarterKey = 'Q'.$quarter['quarter'];
        $quarterData = data_get($snapshot, 'quarters.'.$quarterKey);

        if ($year === null || ! in_array($year, $years, true) || ! is_array($quarterData)) {
            return $this->unavailableImagePeriod(null, $year, (string) $snapshot['period']);
        }

        $months = [
            'Q1' => ['Jan', 'Feb', 'Mar'],
            'Q2' => ['Apr', 'Mei', 'Jun'],
            'Q3' => ['Jul', 'Agu', 'Sep'],
            'Q4' => ['Okt', 'Nov', 'Des'],
        ][$quarterKey];
        $transactionCount = count(array_filter(
            $transactions,
            static fn (array $transaction): bool => (int) ($transaction['date']['year'] ?? 0) === $year
                && in_array((string) ($transaction['date']['month'] ?? ''), $months, true),
        ));
        $income = (int) ($quarterData['totalRevenue'] ?? 0);
        $expense = (int) ($quarterData['totalExpense'] ?? 0);

        return [
            'requested' => true,
            'available' => true,
            'year' => $year,
            // Q1–Q4 are accepted as a period key by the validator and
            // renderer; month_label keeps the existing response contract.
            'month' => $quarterKey,
            'month_label' => $quarterKey,
            'income' => $income,
            'expense' => $expense,
            'net' => (int) ($quarterData['netTotal'] ?? ($income - $expense)),
            'transaction_count' => $transactionCount,
            'period' => (string) ($quarterData['label'] ?? $quarterKey.' '.$year),
        ];
    }

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
    public function imagePromptContext(array $period): string
    {
        $scope = $period['requested'] && $period['month_label'] !== null
            ? $period['month_label'].' '.$period['year']
            : ($period['requested'] && $period['year'] !== null ? 'tahun '.$period['year'] : $period['period']);

        return sprintf(
            'Data finance terverifikasi Farmer IC untuk %s. Gunakan angka persis ini pada gambar/visual: pemasukan %s, pengeluaran %s, laba bersih %s. Jangan membuat atau mengubah angka.',
            $scope,
            $this->formatRupiah((int) $period['income']),
            $this->formatRupiah((int) $period['expense']),
            $this->formatRupiah((int) $period['net']),
        );
    }

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
    public function unavailableImageMessage(array $period): string
    {
        $scope = $period['month_label'] !== null
            ? $period['month_label'].' '.($period['year'] ?? '')
            : 'tahun '.($period['year'] ?? 'yang diminta');

        return "Data keuangan untuk {$scope} tidak tersedia. Data finance demo yang tersedia adalah {$period['period']}. Saya tidak membuat gambar agar tidak mengarang angka.";
    }

    /**
     * Short deterministic response for a text finance lookup whose requested
     * month/year is outside the canonical demo corpus.
     *
     * @param  array<string, mixed>  $period
     */
    public function unavailableDataMessage(array $period): string
    {
        $scope = $period['month_label'] !== null
            ? $period['month_label'].' '.($period['year'] ?? '')
            : 'tahun '.($period['year'] ?? 'yang diminta');

        return "Data keuangan untuk {$scope} tidak tersedia. Data finance demo yang tersedia adalah {$period['period']}. Saya tidak akan mengarang angka.";
    }

    /**
     * Render the canonical values when a provider response contains a
     * conflicting numeric claim. The model is never used to rewrite the
     * numbers; it only supplies optional prose around this result.
     *
     * @param  array<string, mixed>  $period
     */
    public function deterministicSummary(array $period, bool $withCitation = true): string
    {
        $scope = $period['requested'] && $period['month_label'] !== null
            ? $period['month_label'].' '.($period['year'] ?? '')
            : ($period['requested'] && $period['year'] !== null ? 'tahun '.$period['year'] : $period['period']);
        $citation = $withCitation ? ' [S1]' : '';

        return sprintf(
            "Laporan keuangan %s:%s\n\n- Pemasukan: %s\n- Pengeluaran: %s\n- Laba bersih: %s\n\nAngka dihitung dari transaksi finance yang tersedia.",
            $scope,
            $citation,
            $this->formatRupiah((int) $period['income']),
            $this->formatRupiah((int) $period['expense']),
            $this->formatRupiah((int) $period['net']),
        );
    }

    /** @return array{month: string|null, year: int|null} */
    private function parsePeriod(string $prompt): ?array
    {
        $normalized = mb_strtolower($prompt, 'UTF-8');
        $monthPattern = implode('|', array_map(
            static fn (string $month): string => preg_quote($month, '/'),
            array_keys(self::MONTH_ALIASES),
        ));

        if (preg_match('/\b(?<month>'.$monthPattern.')\b(?:\s+(?:tahun\s*)?(?<year>20\d{2}))?/iu', $normalized, $match)) {
            return [
                'month' => self::MONTH_ALIASES[mb_strtolower($match['month'], 'UTF-8')],
                'year' => isset($match['year']) && $match['year'] !== '' ? (int) $match['year'] : null,
            ];
        }

        if (preg_match('/\b(?<year>20\d{2})\s*(?:tahun\s*)?/iu', $normalized, $match)) {
            return ['month' => null, 'year' => (int) $match['year']];
        }

        return null;
    }

    /** @return array{quarter: int, year: int|null}|null */
    private function parseQuarter(string $prompt): ?array
    {
        if (! preg_match('/\bq(?:uarter)?\s*(?<quarter>[1-4])\b(?:\s+(?:tahun\s*)?(?<year>20\d{2}))?/iu', $prompt, $match)) {
            return null;
        }

        return [
            'quarter' => (int) $match['quarter'],
            'year' => isset($match['year']) ? (int) $match['year'] : null,
        ];
    }

    private function canonicalMonth(string $value): ?string
    {
        return self::MONTH_ALIASES[mb_strtolower(trim($value), 'UTF-8')] ?? null;
    }

    /** @param array<int, array<string, mixed>> $transactions */
    private function sumTransactions(array $transactions, string $type): int
    {
        return array_sum(array_map(
            static fn (array $transaction): int => ($transaction['type'] ?? null) === $type ? (int) ($transaction['amount'] ?? 0) : 0,
            $transactions,
        ));
    }

    /** @return array{requested: bool, available: bool, year: int|null, month: string|null, month_label: string|null, income: int|null, expense: int|null, net: int|null, transaction_count: int, period: string} */
    private function unavailableImagePeriod(?string $month, ?int $year, string $period): array
    {
        return [
            'requested' => true,
            'available' => false,
            'year' => $year,
            'month' => $month,
            'month_label' => $month !== null ? self::MONTH_LABELS[$month] : null,
            'income' => null,
            'expense' => null,
            'net' => null,
            'transaction_count' => 0,
            'period' => $period,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function transactionRows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $transaction) {
            if (is_array($transaction)) {
                $rows[] = $transaction;
            }
        }

        return $rows;
    }

    private function formatRupiah(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
