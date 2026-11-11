<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\DailyReportNotification;
use App\Notifications\HarvestReminderNotification;
use App\Notifications\TaskReminderNotification;
use App\Notifications\WeeklyReportNotification;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;

final class InAppChannel
{
    public function send(User $notifiable, WeeklyReportNotification|TaskReminderNotification|DailyReportNotification|HarvestReminderNotification $notification): void
    {
        $data = $notification->data;
        $recipient = app(NotificationService::class)->validRecipient($data);
        if (! $recipient || $recipient->id !== $notifiable->id) {
            return;
        }
        $data = app(NotificationService::class)->dataForDelivery($recipient, $data);
        if ($data === null) {
            return;
        }
        $notification = new ($notification::class)($data);
        $content = $notification->toInApp();
        DB::table('notifications')->insertOrIgnore([
            'id' => $data['id'], 'user_id' => $recipient->id, 'type' => $data['kind'], 'team_id' => $data['team_id'],
            'team_ids' => isset($data['team_ids']) ? json_encode($data['team_ids'], JSON_THROW_ON_ERROR) : null,
            'title' => $content['title'],
            'message' => $content['message'], 'is_read' => false, 'created_at' => now(),
        ]);
    }
}
