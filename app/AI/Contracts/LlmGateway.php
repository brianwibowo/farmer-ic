<?php

namespace App\AI\Contracts;

interface LlmGateway
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function complete(array $messages, string $model, array $options = []): array;

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return iterable<int, array<string, mixed>>
     */
    public function stream(array $messages, string $model, array $options = []): iterable;
}
