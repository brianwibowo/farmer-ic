<?php

namespace App\Notifications;

final class SopDeviationNotification extends TaskReminderNotification
{
    protected function title(): string
    {
        return 'Laporan Ketidaksesuaian SOP';
    }
}
