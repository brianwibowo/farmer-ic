<?php

namespace Tests\Unit\AI;

use App\AI\Exceptions\EmbeddingException;
use App\AI\Services\OpenRouterEmbeddingGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterEmbeddingGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_posts_a_batch_and_preserves_embedding_order(): void
    {
        config([
            'ai.embeddings.api_key' => 'embedding-key',
            'ai.embeddings.dimensions' => 3,
            'ai.embeddings.model' => 'test/embedding',
        ]);
        Http::fake([
            '*' => Http::response([
                'data' => [
                    ['index' => 1, 'embedding' => [0.2, 0.3, 0.4]],
                    ['index' => 0, 'embedding' => [0.1, 0.2, 0.3]],
                ],
                'usage' => ['prompt_tokens' => 7, 'total_tokens' => 7],
            ]),
        ]);

        $result = (new OpenRouterEmbeddingGateway)->embed(['pertanyaan kedua', 'pertanyaan pertama']);

        $this->assertSame([[0.1, 0.2, 0.3], [0.2, 0.3, 0.4]], $result['vectors']);
        $this->assertSame(7, $result['usage']['total_tokens']);
        Http::assertSent(function (Request $request): bool {
            return str_ends_with($request->url(), '/embeddings')
                && $request->hasHeader('Authorization', 'Bearer embedding-key')
                && $request['model'] === 'test/embedding'
                && $request['dimensions'] === 3;
        });
    }

    public function test_it_rejects_an_incomplete_or_wrong_dimension_response(): void
    {
        config([
            'ai.embeddings.api_key' => 'embedding-key',
            'ai.embeddings.dimensions' => 3,
        ]);
        Http::fake(['*' => Http::response(['data' => [['index' => 0, 'embedding' => [0.1, 0.2]]]])]);

        $this->expectException(EmbeddingException::class);

        (new OpenRouterEmbeddingGateway)->embed(['pertanyaan']);
    }

    public function test_it_caches_identical_query_embeddings_and_skips_the_second_api_call(): void
    {
        config([
            'ai.embeddings.api_key' => 'embedding-key',
            'ai.embeddings.dimensions' => 3,
            'ai.embeddings.cache_enabled' => true,
        ]);
        config(['cache.default' => 'array']);
        Cache::flush();
        Http::fake(['*' => Http::response([
            'data' => [['index' => 0, 'embedding' => [0.1, 0.2, 0.3]]],
            'usage' => ['total_tokens' => 4],
        ])]);

        $gateway = new OpenRouterEmbeddingGateway;
        $first = $gateway->embed(['query identik']);
        $second = $gateway->embed(['query identik']);

        $this->assertSame($first['vectors'], $second['vectors']);
        $this->assertSame(0, $first['usage']['cache_hits']);
        $this->assertSame(1, $second['usage']['cache_hits']);
        $this->assertSame(0, $second['usage']['cache_misses']);
        Http::assertSentCount(1);
    }
}
