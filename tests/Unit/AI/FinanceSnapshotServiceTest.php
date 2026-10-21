<?php

namespace Tests\Unit\AI;

use App\AI\Services\FinanceSnapshotService;
use App\Models\AiKnowledgeDocument;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FinanceSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_matches_origin_dev_finance_corpus(): void
    {
        $snapshot = (new FinanceSnapshotService)->snapshot(Team::factory()->make(['id' => 1]));

        $this->assertSame(170000000, $snapshot['income']);
        $this->assertSame(60000000, $snapshot['expense']);
        $this->assertSame(110000000, $snapshot['net']);
        $this->assertSame(24, $snapshot['transaction_count']);
        $this->assertSame('farmDemoData.json origin/dev', $snapshot['source']);

        $april = array_values(array_filter(
            $snapshot['transactions'],
            static fn (array $transaction): bool => ($transaction['date']['month'] ?? null) === 'Apr',
        ));
        $february = array_values(array_filter(
            $snapshot['transactions'],
            static fn (array $transaction): bool => ($transaction['date']['month'] ?? null) === 'Feb',
        ));

        $this->assertSame(11000000, array_sum(array_map(
            static fn (array $transaction): int => ($transaction['type'] ?? null) === 'income' ? (int) $transaction['amount'] : 0,
            $april,
        )));
        $this->assertSame(4000000, array_sum(array_map(
            static fn (array $transaction): int => ($transaction['type'] ?? null) === 'expense' ? (int) $transaction['amount'] : 0,
            $april,
        )));
        $this->assertSame(14000000, array_sum(array_map(
            static fn (array $transaction): int => ($transaction['type'] ?? null) === 'income' ? (int) $transaction['amount'] : 0,
            $february,
        )));
        $this->assertSame(5000000, array_sum(array_map(
            static fn (array $transaction): int => ($transaction['type'] ?? null) === 'expense' ? (int) $transaction['amount'] : 0,
            $february,
        )));
    }

    public function test_summary_document_cannot_replace_the_full_finance_document(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $root = json_decode(File::get(resource_path('js/data/farmDemoData.json')), true, 512, JSON_THROW_ON_ERROR);
        $finance = $root['finance'];

        AiKnowledgeDocument::query()->create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'title' => 'Finance demo origin/dev',
            'content' => json_encode($finance, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'status' => AiKnowledgeDocument::STATUS_PUBLISHED,
            'source' => 'farmDemoData.json origin/dev',
            'content_hash' => hash('sha256', json_encode($finance, JSON_THROW_ON_ERROR)),
            'version' => 1,
            'published_at' => now(),
            'domain' => 'finance',
        ]);

        $summary = ['totalRevenue' => 170000000, 'totalExpense' => 60000000, 'totalNet' => 110000000];
        AiKnowledgeDocument::query()->create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'title' => 'Demo dashboardFinancialSummary origin/dev',
            'content' => json_encode($summary, JSON_THROW_ON_ERROR),
            'status' => AiKnowledgeDocument::STATUS_PUBLISHED,
            'source' => 'farmDemoData.json origin/dev',
            'content_hash' => hash('sha256', json_encode($summary, JSON_THROW_ON_ERROR)),
            'version' => 2,
            'published_at' => now(),
            'domain' => 'finance',
        ]);

        $snapshot = (new FinanceSnapshotService)->snapshot($team);

        $this->assertSame(24, $snapshot['transaction_count']);
        $this->assertSame(170000000, $snapshot['income']);
        $this->assertSame(60000000, $snapshot['expense']);
    }
}
