<?php

namespace App\AI\Contracts;

/**
 * Optional lifecycle operations supported by vector stores that can reconcile
 * an index safely after a knowledge corpus changes.
 */
interface VectorStoreLifecycle
{
    /** @param array<int, string> $ids */
    public function deletePoints(array $ids): void;

    /** @return array{ok: bool, status: int, message?: string} */
    public function health(): array;

    /** @return array<string, mixed> */
    public function collectionInfo(): array;

    /** @param array<string, mixed> $filter */
    public function count(array $filter = []): int;
}
