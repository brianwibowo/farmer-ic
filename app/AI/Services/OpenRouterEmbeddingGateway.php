<?php

namespace App\AI\Services;

use App\AI\Contracts\EmbeddingGateway;
use App\AI\Exceptions\EmbeddingException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OpenRouterEmbeddingGateway implements EmbeddingGateway
{
    /** @param array<int, string> $inputs */
    public function embed(array $inputs): array
    {
        $inputs = array_values(array_filter(array_map(
            static fn (mixed $input): string => trim((string) $input),
            $inputs,
        ), static fn (string $input): bool => $input !== ''));

        if ($inputs === []) {
            return ['vectors' => [], 'usage' => [], 'model' => $this->model()];
        }

        $key = trim((string) config('ai.embeddings.api_key'));
        if ($key === '') {
            throw new EmbeddingException('Embedding provider is not configured.');
        }

        $vectors = array_fill(0, count($inputs), null);
        $missing = [];
        $cacheHits = 0;
        $cacheEnabled = (bool) config('ai.embeddings.cache_enabled', true);

        foreach ($inputs as $index => $input) {
            $cached = $cacheEnabled ? $this->cachedVector($input) : null;
            if ($cached !== null) {
                $vectors[$index] = $cached;
                $cacheHits++;

                continue;
            }

            $missing[$index] = $input;
        }

        $usage = [
            'prompt_tokens' => 0,
            'total_tokens' => 0,
            'cache_hits' => $cacheHits,
            'cache_misses' => count($missing),
        ];

        if ($missing === []) {
            return [
                'vectors' => $vectors,
                'usage' => $usage,
                'model' => $this->model(),
            ];
        }

        try {
            $baseUrl = trim((string) config('ai.embeddings.base_url', ''));
            $baseUrl = $baseUrl !== '' ? $baseUrl : 'https://openrouter.ai/api/v1';
            $response = Http::baseUrl(rtrim($baseUrl, '/'))
                ->withToken($key)
                ->acceptJson()
                ->withHeaders([
                    'HTTP-Referer' => config('ai.embeddings.site_url'),
                    'X-Title' => config('ai.embeddings.app_name'),
                ])
                ->timeout((int) config('ai.embeddings.timeout', 30))
                ->connectTimeout((int) config('ai.embeddings.connect_timeout', 10))
                ->post('/embeddings', [
                    'input' => array_values($missing),
                    'model' => $this->model(),
                    'dimensions' => (int) config('ai.embeddings.dimensions', 1536),
                    'encoding_format' => 'float',
                ]);
        } catch (RequestException $exception) {
            throw $this->exceptionFromResponse($exception->response);
        } catch (ConnectionException $exception) {
            throw new EmbeddingException('Embedding provider connection failed.', null, $exception);
        }

        if ($response->failed()) {
            throw $this->exceptionFromResponse($response);
        }

        $json = $response->json();
        $rows = is_array($json) && is_array($json['data'] ?? null) ? $json['data'] : [];
        $providerVectors = [];
        foreach ($rows as $row) {
            $vector = is_array($row) ? ($row['embedding'] ?? null) : null;
            if (! is_array($vector) || $vector === []) {
                throw new EmbeddingException('Embedding provider returned an invalid vector.');
            }

            $providerVectors[(int) ($row['index'] ?? count($providerVectors))] = array_map(
                static fn (mixed $value): float => (float) $value,
                $vector,
            );
        }
        ksort($providerVectors);

        $providerVectors = array_values($providerVectors);
        if (count($providerVectors) !== count($missing)) {
            throw new EmbeddingException('Embedding provider returned an incomplete batch.');
        }

        $expectedDimensions = (int) config('ai.embeddings.dimensions', 1536);
        foreach ($providerVectors as $vector) {
            if (count($vector) !== $expectedDimensions) {
                throw new EmbeddingException('Embedding vector dimensions do not match configuration.');
            }
        }

        $providerUsage = is_array($json) && is_array($json['usage'] ?? null) ? $json['usage'] : [];
        $usage['prompt_tokens'] = (int) ($providerUsage['prompt_tokens'] ?? $providerUsage['total_tokens'] ?? 0);
        $usage['total_tokens'] = (int) ($providerUsage['total_tokens'] ?? 0);
        $missingIndexes = array_keys($missing);
        foreach ($providerVectors as $providerIndex => $vector) {
            $originalIndex = $missingIndexes[$providerIndex] ?? null;
            if ($originalIndex === null) {
                throw new EmbeddingException('Embedding provider returned an invalid batch index.');
            }
            $vectors[$originalIndex] = $vector;
            if ($cacheEnabled) {
                $this->cacheVector($missing[$originalIndex], $vector);
            }
        }

        return [
            'vectors' => $vectors,
            'usage' => $usage,
            'model' => $this->model(),
        ];
    }

    /** @return array<int, float>|null */
    private function cachedVector(string $input): ?array
    {
        try {
            $cached = Cache::get($this->cacheKey($input));
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($cached) || ! is_array($cached['vector'] ?? null)) {
            return null;
        }

        return array_map(static fn (mixed $value): float => (float) $value, $cached['vector']);
    }

    /** @param array<int, float> $vector */
    private function cacheVector(string $input, array $vector): void
    {
        try {
            Cache::put(
                $this->cacheKey($input),
                ['vector' => $vector],
                now()->addSeconds(max(1, (int) config('ai.embeddings.cache_ttl', 600))),
            );
        } catch (\Throwable) {
            // Retrieval remains correct when the optional cache store is unavailable.
        }
    }

    private function cacheKey(string $input): string
    {
        return 'ai:embedding:v1:'.hash('sha256', json_encode([
            $this->model(),
            (int) config('ai.embeddings.dimensions', 1536),
            trim(mb_strtolower($input, 'UTF-8')),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function model(): string
    {
        $model = trim((string) config('ai.embeddings.model', ''));

        return $model !== '' ? $model : 'openai/text-embedding-3-small';
    }

    private function exceptionFromResponse(Response $response): EmbeddingException
    {
        $message = data_get($response->json(), 'error.message')
            ?? data_get($response->json(), 'message')
            ?? 'Embedding provider request failed.';

        return new EmbeddingException(
            is_string($message) ? $message : 'Embedding provider request failed.',
            $response->status(),
        );
    }
}
