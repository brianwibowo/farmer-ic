<?php

namespace App\AI\Contracts;

interface EmbeddingGateway
{
    /**
     * @param  array<int, string>  $inputs
     * @return array{vectors: array<int, array<int, float>>, usage: array<string, int>, model: string}
     */
    public function embed(array $inputs): array;
}
