<?php

namespace App\AI\Contracts;

interface VectorStore
{
    public function ensureCollection(int $dimensions): void;

    /**
     * @param  array<int, array{id: string, vector: array<int, float>, payload: array<string, mixed>}>  $points
     */
    public function upsert(array $points): void;

    /**
     * @param  array<int, float>  $vector
     * @param  array<string, mixed>  $filter
     * @return array<int, array{id: string|int, score: float, payload: array<string, mixed> }>
     */
    public function query(array $vector, int $limit, array $filter = []): array;
}
