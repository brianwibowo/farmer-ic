<?php

namespace App\Notifications;

final class InboxPrompt
{
    public static function message(string $title): string
    {
        return '*'.$title."*\nNotifikasi baru telah masuk. Buka inbox aplikasi untuk melihat rinciannya:\n"
            .rtrim((string) config('app.url'), '/').'/notifications';
    }
}
