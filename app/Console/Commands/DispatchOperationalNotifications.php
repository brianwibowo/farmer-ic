<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationService;
use Illuminate\Console\Command;

class DispatchOperationalNotifications extends Command
{
    protected $signature = 'notifications:dispatch {kind : daily-report or harvest-reminder}';

    protected $description = 'Queue operational notifications for the current day';

    public function handle(NotificationService $notifications): int
    {
        $count = match ($this->argument('kind')) {
            'daily-report' => $notifications->dailyReports(),
            'harvest-reminder' => $notifications->harvestReminders(),
            default => null,
        };
        if ($count === null) {
            $this->error('Unknown kind. Use daily-report or harvest-reminder.');

            return self::INVALID;
        }
        $this->info('Notifications queued: '.$count);

        return self::SUCCESS;
    }
}
