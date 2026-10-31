<?php

namespace App\Services\Notifications;

final class FonnteException extends \RuntimeException
{
    public function __construct(public readonly string $reasonCode, public readonly string $classification)
    {
        parent::__construct('WhatsApp provider: '.$reasonCode);
    }

    public function __toString(): string
    {
        // failed_jobs stores this string. Do not include call-argument traces.
        return self::class.': '.$this->getMessage();
    }
}
