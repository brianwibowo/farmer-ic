<?php

namespace App\AI\Services;

use App\Models\AiKnowledgeDocument;
use App\Models\Team;
use Illuminate\Support\Facades\File;

/** Deterministic finance adapter backed by the origin/dev demo corpus. */
class FinanceSnapshotService
{
    /** @return array<string, mixed> */
    public function snapshot(Team $team): array
    {
        $document = AiKnowledgeDocument::query()
            ->where('team_id', $team->id)
            ->where('source', 'Farmer IC finance demo dataset (origin/dev)')
            ->where('status', AiKnowledgeDocument::STATUS_PUBLISHED)
            ->latest('version')
            ->first();
        $raw = $document ? json_decode((string) $document->content, true) : null;
        if (! is_array($raw)) {
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
            'transaction_count' => count((array) ($raw['transactions'] ?? [])),
            'source' => 'Farmer IC finance demo dataset (origin/dev)',
            'calculation' => 'laba bersih = pemasukan - pengeluaran; margin = laba bersih / pemasukan',
        ];
    }
}
