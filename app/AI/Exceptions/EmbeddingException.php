<?php

namespace App\AI\Exceptions;

use RuntimeException;

class EmbeddingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }
}
