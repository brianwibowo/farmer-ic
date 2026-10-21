<?php

namespace Tests\Unit\AI;

use App\AI\Contracts\EmbeddingGateway;
use App\AI\Contracts\VectorStore;
use App\AI\Contracts\VectorStoreLifecycle;
use App\AI\Services\IndexManifestStore;
use App\AI\Services\KnowledgeChunker;
use App\AI\Services\KnowledgeService;
use App\AI\Services\QdrantKnowledgeIndexer;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class QdrantKnowledgeIndexerTest extends TestCase
{
    use RefreshDatabase;

    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manifestPath = sys_get_temp_dir().'/farmer-ic-ai-index-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->manifestPath);
        parent::tearDown();
    }

    public function test_it_indexes_chunked_team_scoped_payloads_in_batches(): void
    {
        config([
            'ai.embeddings.dimensions' => 3,
            'ai.embeddings.batch_size' => 2,
            'ai.embeddings.model' => 'fake-embedding',
            'ai.rag.manifest_path' => $this->manifestPath,
        ]);
        $embedding = new class implements EmbeddingGateway
        {
            public function embed(array $inputs): array
            {
                return [
                    'vectors' => array_fill(0, count($inputs), [0.1, 0.2, 0.3]),
                    'usage' => ['total_tokens' => count($inputs)],
                    'model' => 'fake-embedding',
                ];
            }
        };
        $store = new class implements VectorStore
        {
            public int $dimensions = 0;

            /** @var array<int, array<int, array<string, mixed>>> */
            public array $batches = [];

            public function ensureCollection(int $dimensions): void
            {
                $this->dimensions = $dimensions;
            }

            public function upsert(array $points): void
            {
                $this->batches[] = $points;
            }

            public function query(array $vector, int $limit, array $filter = []): array
            {
                return [];
            }
        };

        $summary = (new QdrantKnowledgeIndexer(
            new KnowledgeService,
            new KnowledgeChunker,
            $embedding,
            $store,
        ))->indexTeam(Team::factory()->create());

        $this->assertSame(3, $store->dimensions);
        $this->assertGreaterThan(0, $summary['chunks']);
        $this->assertSame($summary['chunks'], array_sum(array_map('count', $store->batches)));
        $this->assertSame('fake-embedding', $summary['model']);
        $first = $store->batches[0][0];
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $first['id']);
        $this->assertSame('published', $first['payload']['status']);
        $this->assertArrayHasKey('team_id', $first['payload']);
        $this->assertArrayHasKey('index_version', $first['payload']);
        $this->assertFalse($summary['skipped']);
        $this->assertNotSame('', $summary['fingerprint']);
    }

    public function test_it_skips_an_unchanged_corpus_and_reports_manifest_status(): void
    {
        config([
            'ai.embeddings.dimensions' => 3,
            'ai.embeddings.batch_size' => 32,
            'ai.embeddings.model' => 'fake-embedding',
            'ai.rag.manifest_path' => $this->manifestPath,
        ]);
        $embedding = new class implements EmbeddingGateway
        {
            public int $calls = 0;

            public function embed(array $inputs): array
            {
                $this->calls++;

                return [
                    'vectors' => array_fill(0, count($inputs), [0.1, 0.2, 0.3]),
                    'usage' => ['total_tokens' => count($inputs)],
                    'model' => 'fake-embedding',
                ];
            }
        };
        $store = new class implements VectorStore, VectorStoreLifecycle
        {
            public int $upserts = 0;

            public int $points = 0;

            public function ensureCollection(int $dimensions): void {}

            public function upsert(array $points): void
            {
                $this->upserts++;
                $this->points += count($points);
            }

            public function query(array $vector, int $limit, array $filter = []): array
            {
                return [];
            }

            public function deletePoints(array $ids): void {}

            public function health(): array
            {
                return ['ok' => true, 'status' => 200];
            }

            public function collectionInfo(): array
            {
                return ['status' => 'green'];
            }

            public function count(array $filter = []): int
            {
                return $this->points;
            }
        };
        $team = Team::factory()->create();
        $indexer = new QdrantKnowledgeIndexer(
            new KnowledgeService,
            new KnowledgeChunker,
            $embedding,
            $store,
            new IndexManifestStore,
        );

        $first = $indexer->indexTeam($team);
        $second = $indexer->indexTeam($team);

        $this->assertFalse($first['skipped']);
        $this->assertTrue($second['skipped']);
        $this->assertGreaterThan(0, $embedding->calls);
        $this->assertGreaterThan(0, $store->upserts);
        $this->assertTrue($indexer->status($team)['indexed']);
    }
}
