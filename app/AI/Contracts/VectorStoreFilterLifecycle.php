<?php

namespace App\AI\Contracts;

/**
 * Optional vector-store operation for removing all points matching a
 * payload filter. Qdrant uses this when a document is unpublished or deleted
 * before its chunk rows are available for point-id based cleanup.
 */
interface VectorStoreFilterLifecycle
{
    /** @param array<string, mixed> $filter */
    public function deleteByFilter(array $filter): void;
}
