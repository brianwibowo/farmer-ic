<?php

namespace App\Providers;

use App\AI\Contracts\EmbeddingGateway;
use App\AI\Contracts\KnowledgeRetriever;
use App\AI\Contracts\LlmGateway;
use App\AI\Contracts\VectorStore;
use App\AI\Services\InMemoryKnowledgeRetriever;
use App\AI\Services\OpenRouterEmbeddingGateway;
use App\AI\Services\OpenRouterGateway;
use App\AI\Services\QdrantClient;
use App\AI\Services\VectorKnowledgeRetriever;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(LlmGateway::class, OpenRouterGateway::class);
        $this->app->bind(EmbeddingGateway::class, OpenRouterEmbeddingGateway::class);
        $this->app->bind(VectorStore::class, QdrantClient::class);
        $this->app->bind(KnowledgeRetriever::class, function ($app): KnowledgeRetriever {
            return config('ai.rag.vector_enabled')
                ? $app->make(VectorKnowledgeRetriever::class)
                : $app->make(InMemoryKnowledgeRetriever::class);
        });
    }
}
