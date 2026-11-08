<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\DailyReportNotification;
use App\Notifications\HarvestReminderNotification;
use App\Policies\NotificationPolicy;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;

final class InAppChannel
{
    public function send(User $notifiable, DailyReportNotification|HarvestReminderNotification $notification): void
    {
        $data = $notification->data;
        $recipient = app(NotificationService::class)->validRecipient($data);
        if (! $recipient || $recipient->id !== $notifiable->id) {
            return;
        }
        $content = $notification instanceof DailyReportNotification
            ? $notification->toInApp(app(NotificationPolicy::class)->showsTenantContext($recipient))
            : $notification->toInApp();
        DB::table('notifications')->insertOrIgnore([
            'id' => $data['id'], 'user_id' => $recipient->id, 'type' => $data['kind'], 'team_id' => $data['team_id'], 'title' => $content['title'],
            'message' => $content['message'], 'is_read' => false, 'created_at' => now(),
        ]);
    }
}
