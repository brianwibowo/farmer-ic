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
        $transactions = array_values(array_filter(
            (array) ($snapshot['transactions'] ?? []),
            static fn (mixed $transaction): bool => is_array($transaction),
        ));
        $years = array_values(array_unique(array_filter(array_map(
            static fn (array $transaction): int => (int) ($transaction['date']['year'] ?? 0),
            $transactions,
        ))));

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
        if ($year === null || ($years !== [] && ! in_array($year, $years, true))) {
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
            'Data finance terverifikasi Farmer IC untuk %s. Gunakan angka persis ini pada infografis: pemasukan %s, pengeluaran %s, laba bersih %s. Jangan membuat atau mengubah angka.',
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

    /** @return array<string, mixed> */
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

    private function formatRupiah(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
