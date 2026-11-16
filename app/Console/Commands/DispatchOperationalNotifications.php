<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationService;
use Illuminate\Console\Command;

class DispatchOperationalNotifications extends Command
{
    protected $signature = 'notifications:dispatch {kind : weekly-report, task-reminder, sop-deviation or incident-report}';

    protected $description = 'Queue operational notifications for the current day';

    public function handle(NotificationService $notifications): int
    {
        $notifications->resumePendingDeliveries();
        $count = match ($this->argument('kind')) {
            'weekly-report' => $notifications->weeklyReports(),
            'task-reminder' => $notifications->taskReminders(),
            'sop-deviation' => $notifications->sopDeviations(),
            'incident-report' => $notifications->incidentReports(),
            default => null,
        };
        if ($count === null) {
            $this->error('Unknown kind. Use weekly-report, task-reminder, sop-deviation or incident-report.');

            return self::INVALID;
        }
        $this->info('Notifications queued: '.$count);

        return self::SUCCESS;
    }
}
