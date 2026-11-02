<?php

namespace App\Jobs;

use App\Notifications\DailyReportNotification;
use App\Notifications\HarvestReminderNotification;
use App\Policies\NotificationPolicy;
use App\Services\Notifications\FonnteClient;
use App\Services\Notifications\FonnteException;
use App\Services\Notifications\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class SendWhatsAppNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public function __construct(public readonly DailyReportNotification|HarvestReminderNotification $notification)
    {
        $this->onConnection(config('operational_notifications.connection'));
        $this->onQueue(config('operational_notifications.whatsapp_queue'));
        $this->afterCommit();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(NotificationService $notifications, FonnteClient $client): void
    {
        $data = $this->notification->data;
        $expiry = CarbonImmutable::parse($data['event_date'], config('app.timezone'))->addDay()->startOfDay();
        $requestWindow = FonnteClient::VALIDATION_TIMEOUT + FonnteClient::SEND_TIMEOUT;
        if (! config('operational_notifications.fonnte.enabled') || now()->addSeconds($requestWindow)->gte($expiry)) {
            return;
        }
        $recipient = $notifications->validRecipient($data);
        if (! $recipient) {
            return;
        }
        $number = $recipient->phone_number;
        if (! is_string($number) || ! preg_match('/^[1-9][0-9]{7,14}$/D', $number)) {
            Log::warning('Operational WhatsApp skipped', ['notification_id' => $data['id'], 'reason' => 'missing_or_invalid_number']);

            return;
        }
        try {
            if (! $client->isRegistered($number)) {
                Log::warning('Operational WhatsApp skipped', ['notification_id' => $data['id'], 'reason' => 'number_not_registered']);

                return;
            }
            // Validation is a network request; data or time may have changed while waiting.
            $recipient = $notifications->validRecipient($data);
            if (! $recipient || $recipient->phone_number !== $number || now()->addSeconds(FonnteClient::SEND_TIMEOUT)->gte($expiry)) {
                Log::warning('Operational WhatsApp skipped', ['notification_id' => $data['id'], 'reason' => 'context_changed_during_validation']);

                return;
            }
            $message = $this->notification instanceof DailyReportNotification
                ? $this->notification->toWhatsAppText(app(NotificationPolicy::class)->showsTenantContext($recipient))
                : $this->notification->toWhatsAppText();
            $client->send($number, $message);
            Log::info('Operational WhatsApp accepted by provider', ['notification_id' => $data['id']]);
        } catch (FonnteException $exception) {
            Log::warning('Operational WhatsApp failed', ['notification_id' => $data['id'], 'reason' => $exception->reasonCode, 'classification' => $exception->classification]);
            $delay = $this->backoff()[min(2, max(0, $this->attempts() - 1))];
            if ($exception->classification === 'transient' && $this->attempts() < $this->tries
                && now()->addSeconds($delay + $requestWindow)->lt($expiry)) {
                $this->release($delay);

                return;
            }
            $this->fail($exception);
        }
    }
}
