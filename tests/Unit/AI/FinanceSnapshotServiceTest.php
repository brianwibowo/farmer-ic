<?php

namespace Tests\Unit\AI;

use App\AI\Services\FinanceSnapshotService;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertSame('Farmer IC finance demo dataset (origin/dev)', $snapshot['source']);
    }
}
