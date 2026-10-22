<?php

namespace Tests\Unit\AI;

use App\AI\Services\QdrantClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QdrantClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_missing_collection_and_upserts_points(): void
    {
        config([
            'ai.qdrant.url' => 'http://qdrant.test:6333',
            'ai.qdrant.collection' => 'knowledge_test',
            'ai.qdrant.api_key' => 'qdrant-key',
        ]);
        Http::fakeSequence()
            ->push([], 404)
            ->push(['result' => true], 200)
            ->push(['result' => ['status' => 'completed']], 200);

        $client = new QdrantClient;
        $client->ensureCollection(3);
        $client->upsert([[
            'id' => '00000000-0000-4000-8000-000000000001',
            'vector' => [0.1, 0.2, 0.3],
            'payload' => ['team_id' => 9, 'content' => 'panduan'],
        ]]);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/collections/knowledge_test')
                && data_get($request->data(), 'vectors.size') === 3
                && $request->hasHeader('api-key', 'qdrant-key');
        });
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/points?wait=true')
                && data_get($request->data(), 'points.0.payload.team_id') === 9;
        });
    }

    public function test_it_decodes_query_hits(): void
    {
        config(['ai.qdrant.url' => 'http://qdrant.test:6333']);
        Http::fake(['*' => Http::response([
            'result' => [
                'points' => [
                    ['id' => 'point-1', 'score' => 0.91, 'payload' => ['content' => 'Tanam kedelai']],
                ],
            ],
        ])]);

        $results = (new QdrantClient)->query([0.1, 0.2], 4, ['must' => []]);

        $this->assertSame('point-1', $results[0]['id']);
        $this->assertSame(0.91, $results[0]['score']);
        $this->assertSame('Tanam kedelai', $results[0]['payload']['content']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/points/query')
            && $request['limit'] === 4
            && $request['with_payload'] === true);
    }

    public function test_it_supports_delete_health_and_collection_status_for_index_lifecycle(): void
    {
        config([
            'ai.qdrant.url' => 'http://qdrant.test:6333',
            'ai.qdrant.collection' => 'knowledge_test',
        ]);
        Http::fakeSequence()
            ->push(['result' => ['operation_id' => 1]], 200)
            ->push('healthz ok', 200)
            ->push(['result' => ['status' => 'green', 'points_count' => 7]], 200)
            ->push(['result' => ['count' => 2]], 200);

        $client = new QdrantClient;
        $client->deletePoints(['point-1', 'point-2']);
        $health = $client->health();
        $collection = $client->collectionInfo();
        $count = $client->count(['must' => []]);

        $this->assertTrue($health['ok']);
        $this->assertSame(200, $health['status']);
        $this->assertSame('green', $collection['status']);
        $this->assertSame(7, $collection['points_count']);
        $this->assertSame(2, $count);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/points/delete?wait=true')
            && $request['points'] === ['point-1', 'point-2']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/points/count')
            && $request['exact'] === true);
    }

    public function test_it_deletes_points_by_payload_filter(): void
    {
        config([
            'ai.qdrant.url' => 'http://qdrant.test:6333',
            'ai.qdrant.collection' => 'knowledge_test',
        ]);
        Http::fake(['*' => Http::response(['result' => ['operation_id' => 2]], 200)]);

        (new QdrantClient)->deleteByFilter([
            'must' => [
                ['key' => 'team_id', 'match' => ['value' => 7]],
                ['key' => 'document_id', 'match' => ['value' => '12']],
            ],
        ]);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/points/delete?wait=true')
            && data_get($request->data(), 'filter.must.0.key') === 'team_id'
            && data_get($request->data(), 'filter.must.1.match.value') === '12');
    }
}
