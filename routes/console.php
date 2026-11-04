<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::command('planting:reconcile-windows')->hourly()->timezone('Asia/Jakarta')->withoutOverlapping(10);

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('notifications:dispatch daily-report')->dailyAt('20:00')
    ->timezone(config('app.timezone'))->withoutOverlapping(10);
Schedule::command('notifications:dispatch harvest-reminder')->dailyAt('06:00')
    ->timezone(config('app.timezone'))->withoutOverlapping(10);
