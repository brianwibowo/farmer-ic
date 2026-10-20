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
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
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

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
