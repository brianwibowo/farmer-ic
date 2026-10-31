<?php

namespace App\Notifications;

use App\Notifications\Channels\InAppChannel;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class HarvestReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // Placeholder until the harvest schedule detail page URL is available.
    private const DETAIL_URL = 'https://example.com/jadwal-panen';

    public int $tries = 3;

    public int $timeout = 30;

    /** @param array<string, mixed> $data */
    public function __construct(public readonly array $data)
    {
        $this->onConnection(config('operational_notifications.connection'));
        $this->onQueue(config('operational_notifications.in_app_queue'));
        $this->afterCommit();
    }

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [InAppChannel::class];
    }

    /** @return array{title: string, message: string} */
    public function toInApp(): array
    {
        $label = $this->data['days_before'] === 0 ? 'H-0 (Hari Panen)' : 'H-'.$this->data['days_before'];
        $date = CarbonImmutable::parse($this->data['harvest_date'], config('app.timezone'))->settings(['locale' => 'id'])->translatedFormat('j F Y');

        return [
            'title' => 'Panen Reminder '.$label,
            'message' => 'Panen Reminder '.$label."\nKebun: ".$this->data['farm_name']
                ."\nSiklus: ".$this->data['cycle_id']."\nTanggal panen: ".$date
                ."\nPetugas: ".$this->data['recipient_name']."\nPeriksa kesiapan panen sesuai jadwal kebun.",
        ];
    }

    public function toWhatsAppText(): string
    {
        $label = $this->data['days_before'] === 0 ? 'H-0 (Hari Panen)' : 'H-'.$this->data['days_before'];
        $date = CarbonImmutable::parse($this->data['harvest_date'], config('app.timezone'))->settings(['locale' => 'id'])->translatedFormat('j F Y');
        $lines = [
            '*Panen Reminder '.$label.'*',
            '',
            'Halo '.$this->data['recipient_name'].',',
            $this->data['days_before'] === 0
                ? 'Hari ini adalah jadwal panen kebun Anda.'
                : 'Jadwal panen kebun Anda tinggal '.$this->data['days_before'].' hari lagi.',
            '',
            '*Kebun*: '.$this->data['farm_name'],
            '*Tanggal panen*: '.$date,
            '*Petugas*: '.$this->data['recipient_name'],
            '*Siklus*: '.$this->data['cycle_id'],
            '',
            'Silakan periksa kesiapan panen sesuai jadwal kebun.',
            '',
            '*Detail jadwal panen* (tautan sementara):',
            self::DETAIL_URL,
        ];

        return implode("\n", $lines);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }
}
