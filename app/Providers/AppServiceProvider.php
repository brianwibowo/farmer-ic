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
use App\Contracts\KebunAssignments;
use App\Models\Team;
use App\Models\User;
use App\Policies\ResourceAccessPolicy;
use App\Support\Access\ResourceAccess;
use App\Support\Access\UnconfiguredKebunAssignments;
use App\Support\RolePermissions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(KebunAssignments::class, UnconfiguredKebunAssignments::class);

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
    public function boot(RolePermissions $permissions): void
    {
        $this->configureDefaults();

        Gate::policy(ResourceAccess::class, ResourceAccessPolicy::class);

        Gate::define('ai.access', fn (User $user, ?Team $team = null): bool => $team !== null
            && $permissions->scope($user, 'ai.access') === 'any'
            && $permissions->withinTeam($user, $team));
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
