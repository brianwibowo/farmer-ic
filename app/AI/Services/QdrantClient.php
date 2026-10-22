<?php

namespace App\AI\Services;

use App\AI\Contracts\VectorStore;
use App\AI\Contracts\VectorStoreFilterLifecycle;
use App\AI\Contracts\VectorStoreLifecycle;
use App\AI\Exceptions\VectorStoreException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class QdrantClient implements VectorStore, VectorStoreFilterLifecycle, VectorStoreLifecycle
{
    public function ensureCollection(int $dimensions): void
    {
        $response = $this->send(fn (): Response => $this->request()->get('/collections/'.rawurlencode($this->collection())));

        if ($response->successful()) {
            $configuredSize = data_get($response->json(), 'result.config.params.vectors.size');
            if (is_numeric($configuredSize) && (int) $configuredSize !== $dimensions) {
                throw new VectorStoreException('Qdrant collection dimensions do not match embedding configuration.');
            }

            return;
        }

        if ($response->status() !== 404) {
            throw $this->exceptionFromResponse($response);
        }

        $created = $this->send(fn (): Response => $this->request()->put('/collections/'.rawurlencode($this->collection()), [
            'vectors' => [
                'size' => $dimensions,
                'distance' => 'Cosine',
            ],
        ]));

        if ($created->failed() && $created->status() !== 409) {
            throw $this->exceptionFromResponse($created);
        }
    }

    /** @param array<int, array{id: string, vector: array<int, float>, payload: array<string, mixed>}> $points */
    public function upsert(array $points): void
    {
        if ($points === []) {
            return;
        }

        $response = $this->send(fn (): Response => $this->request()->put(
            '/collections/'.rawurlencode($this->collection()).'/points?wait=true',
            ['points' => array_values($points)],
        ));

        if ($response->failed()) {
            throw $this->exceptionFromResponse($response);
        }
    }

    /** @param array<int, string> $ids */
    public function deletePoints(array $ids): void
    {
        $ids = array_values(array_filter(array_map('strval', $ids), static fn (string $id): bool => $id !== ''));
        if ($ids === []) {
            return;
        }

        $this->deleteSelection(['points' => $ids]);
    }

    /** @param array<string, mixed> $filter */
    public function deleteByFilter(array $filter): void
    {
        if ($filter === []) {
            return;
        }

        $this->deleteSelection(['filter' => $filter]);
    }

    /** @param array<string, mixed> $selection */
    private function deleteSelection(array $selection): void
    {
        $response = $this->send(fn (): Response => $this->request()->post(
            '/collections/'.rawurlencode($this->collection()).'/points/delete?wait=true',
            $selection,
        ));

        if ($response->failed()) {
            throw $this->exceptionFromResponse($response);
        }
    }

    /** @return array{ok: bool, status: int, message?: string} */
    public function health(): array
    {
        $response = $this->send(fn (): Response => $this->request()->get('/healthz'));

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'message' => trim($response->body()),
        ];
    }

    /** @return array<string, mixed> */
    public function collectionInfo(): array
    {
        $response = $this->send(fn (): Response => $this->request()->get('/collections/'.rawurlencode($this->collection())));
        if ($response->failed()) {
            throw $this->exceptionFromResponse($response);
        }

        $result = $response->json('result');

        return is_array($result) ? $result : [];
    }

    /** @param array<string, mixed> $filter */
    public function count(array $filter = []): int
    {
        $payload = ['exact' => true];
        if ($filter !== []) {
            $payload['filter'] = $filter;
        }

        $response = $this->send(fn (): Response => $this->request()->post(
            '/collections/'.rawurlencode($this->collection()).'/points/count',
            $payload,
        ));
        if ($response->failed()) {
            throw $this->exceptionFromResponse($response);
        }

        return (int) ($response->json('result.count') ?? 0);
    }

    /**
     * @param  array<int, float>  $vector
     * @param  array<string, mixed>  $filter
     */
    public function query(array $vector, int $limit, array $filter = []): array
    {
        $payload = [
            'query' => array_values($vector),
            'limit' => max(1, $limit),
            'with_payload' => true,
        ];
        if ($filter !== []) {
            $payload['filter'] = $filter;
        }

        $response = $this->send(fn (): Response => $this->request()->post(
            '/collections/'.rawurlencode($this->collection()).'/points/query',
            $payload,
        ));

        if ($response->failed()) {
            throw $this->exceptionFromResponse($response);
        }

        $result = $response->json('result');
        $rows = is_array($result) && is_array($result['points'] ?? null)
            ? $result['points']
            : $result;
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static function (mixed $row): ?array {
                if (! is_array($row) || ! isset($row['id'])) {
                    return null;
                }

                return [
                    'id' => is_scalar($row['id']) ? (string) $row['id'] : '',
                    'score' => (float) ($row['score'] ?? 0),
                    'payload' => is_array($row['payload'] ?? null) ? $row['payload'] : [],
                ];
            },
            $rows,
        )));
    }

    protected function request(): PendingRequest
    {
        $url = trim((string) config('ai.qdrant.url', ''));
        $url = $url !== '' ? $url : 'http://qdrant:6333';
        $request = Http::baseUrl(rtrim($url, '/'))
            ->acceptJson()
            ->timeout((int) config('ai.qdrant.timeout', 10))
            ->connectTimeout((int) config('ai.qdrant.connect_timeout', 3));

        $apiKey = trim((string) config('ai.qdrant.api_key'));
        if ($apiKey !== '') {
            $request = $request->withHeaders(['api-key' => $apiKey]);
        }

        return $request;
    }

    private function collection(): string
    {
        $collection = trim((string) config('ai.qdrant.collection', ''));

        return $collection !== '' ? $collection : 'farmer_ic_knowledge';
    }

    /** @param callable(): Response $callback */
    private function send(callable $callback): Response
    {
        try {
            return $callback();
        } catch (ConnectionException $exception) {
            throw new VectorStoreException('Qdrant connection failed.', null, $exception);
        }
    }

    private function exceptionFromResponse(Response $response): VectorStoreException
    {
        $message = data_get($response->json(), 'status.error')
            ?? data_get($response->json(), 'message')
            ?? 'Qdrant request failed.';

        return new VectorStoreException(
            is_string($message) ? $message : 'Qdrant request failed.',
            $response->status(),
        );
    }
}
