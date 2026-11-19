<?php

namespace App\Support;

final class SubblockLifecycle
{
    public const RUNNING = ['planned', 'active'];

    public const HISTORY = ['harvested', 'cancelled'];

    /** @return list<string> */
    public static function statuses(string $view): array
    {
        return $view === 'history' ? self::HISTORY : self::RUNNING;
    }
}
