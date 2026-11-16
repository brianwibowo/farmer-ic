<?php

namespace App\Jobs;

use App\Notifications\Channels\InAppChannel;
use App\Notifications\TaskReminderNotification;
use App\Notifications\WeeklyReportNotification;
use App\Services\Notifications\FonnteClient;
use App\Services\Notifications\FonnteException;
use App\Services\Notifications\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class SendWhatsAppNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public function __construct(public readonly WeeklyReportNotification|TaskReminderNotification $notification)
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
        if ($notifications->dataForDelivery($recipient, $data) === null) {
            return;
        }
        $delivery = DB::table('operational_notification_deliveries')->where('id', $data['id'])->first();
        if ($delivery === null || $delivery->whatsapp_state !== 'pending') {
            return;
        }
        // The prompt must never precede the inbox record, regardless of worker order.
        (new InAppChannel)->send($recipient, $this->notification);
        if (! DB::table('notifications')->where('id', $data['id'])->exists()) {
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
            $data = $notifications->dataForDelivery($recipient, $data);
            if ($data === null) {
                return;
            }
            $notification = new ($this->notification::class)($data);
            $message = $notification->toWhatsAppText();
            $claimed = DB::table('operational_notification_deliveries')->where('id', $data['id'])->where('whatsapp_state', 'pending')->update(['whatsapp_state' => 'sending']);
            if ($claimed !== 1) {
                return;
            }
            try {
                $client->send($number, $message);
            } catch (FonnteException $exception) {
                $this->handleProviderFailure($exception, $data, $expiry, true);

                return;
            }
            DB::table('operational_notification_deliveries')->where('id', $data['id'])->update(['whatsapp_state' => 'accepted']);
            Log::info('Operational WhatsApp accepted by provider', ['notification_id' => $data['id']]);
        } catch (FonnteException $exception) {
            $this->handleProviderFailure($exception, $data, $expiry, false);
        }
    }

    /** @param array<string, mixed> $data */
    private function handleProviderFailure(FonnteException $exception, array $data, CarbonImmutable $expiry, bool $claimedSend): void
    {
        $requestWindow = FonnteClient::VALIDATION_TIMEOUT + FonnteClient::SEND_TIMEOUT;

        Log::warning('Operational WhatsApp failed', ['notification_id' => $data['id'], 'reason' => $exception->reasonCode, 'classification' => $exception->classification]);
        $delay = $this->backoff()[min(2, max(0, $this->attempts() - 1))];
        if ($exception->classification === 'transient' && $this->attempts() < $this->tries
            && now()->addSeconds($delay + $requestWindow)->lt($expiry)) {
            if ($claimedSend) {
                DB::table('operational_notification_deliveries')->where('id', $data['id'])->where('whatsapp_state', 'sending')->update(['whatsapp_state' => 'pending']);
            }
            $this->release($delay);

            return;
        }
        DB::table('operational_notification_deliveries')->where('id', $data['id'])->where('whatsapp_state', $claimedSend ? 'sending' : 'pending')->update(['whatsapp_state' => 'failed']);
        $this->fail($exception);
    }
}
