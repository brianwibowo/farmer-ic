<?php

namespace App\AI\Contracts;

use App\Models\Team;

interface KnowledgeRetriever
{
    /**
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     source: string,
     *     content: string,
     *     score: float,
     *     metadata: array<string, mixed>
     * }>
     */
    public function retrieve(string $query, Team $team, int $limit = 4): array;
}
