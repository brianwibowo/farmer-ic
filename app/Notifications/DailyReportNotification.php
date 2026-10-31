<?php

namespace App\Notifications;

use App\Notifications\Channels\InAppChannel;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class DailyReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // Placeholder until the report detail page URL is available.
    private const DETAIL_URL = 'https://example.com/laporan-harian-kebun';

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
    public function toInApp(?bool $showTenant = null): array
    {
        $showTenant ??= ($this->data['show_tenant'] ?? false) === true;
        $lines = $showTenant ? ['Tenant: '.$this->data['team_name']] : [];
        $lines[] = 'Jumlah kebun: '.count($this->data['farms']);
        $lines[] = 'Batas rekap: 20:00 WIB';
        foreach ($this->data['farms'] as $index => $farm) {
            $lines[] = '';
            $lines[] = ($index + 1).'. Kebun: '.$farm['name'];
            if ($farm['activities'] === []) {
                $lines[] = 'Petugas belum mengisi aktivitas hari ini';
            } else {
                foreach ($farm['activities'] as $activity) {
                    $time = CarbonImmutable::parse($activity['occurred_at'])->setTimezone(config('app.timezone'))->format('H:i');
                    $lines[] = '- '.$time.' | Petugas: '.($activity['performer_name'] ?? 'Petugas tidak tersedia')
                        .' | '.($activity['activity_type'] ?? 'Aktivitas').' | '.($activity['description'] ?: 'Tanpa catatan');
                }
            }
        }

        return [
            'title' => 'Report Harian Kebun dari Petugas',
            'message' => implode("\n", $lines),
        ];
    }

    public function toWhatsAppText(?bool $showTenant = null): string
    {
        $showTenant ??= ($this->data['show_tenant'] ?? false) === true;
        $date = CarbonImmutable::parse($this->data['event_date'], config('app.timezone'))->settings(['locale' => 'id'])->translatedFormat('j F Y');
        $lines = [
            '*Report Harian Kebun dari Petugas*',
            '',
            'Salam hormat Bapak/Ibu,',
            $showTenant ? 'Berikut rekap aktivitas seluruh kebun dalam tenant Anda.' : 'Berikut rekap aktivitas seluruh kebun Anda.',
            '',
        ];
        if ($showTenant) {
            $lines[] = '*Tenant*: '.$this->data['team_name'];
        }
        array_push($lines,
            '*Tanggal*: '.$date,
            '*Jumlah kebun*: '.count($this->data['farms']),
            '*Batas rekap*: 20:00 WIB',
        );

        foreach ($this->data['farms'] as $index => $farm) {
            $lines[] = '';
            $lines[] = '────────────────';
            $lines[] = '*'.($index + 1).'. Kebun: '.$farm['name'].'*';
            $lines[] = '*Jumlah aktivitas*: '.count($farm['activities']);
            $lines[] = '';
            if ($farm['activities'] === []) {
                $lines[] = 'Petugas belum mengisi aktivitas hari ini';
            } else {
                $number = 1;
                foreach ($farm['activities'] as $activity) {
                    $time = CarbonImmutable::parse($activity['occurred_at'])->setTimezone(config('app.timezone'))->format('H:i');
                    if ($number > 1) {
                        $lines[] = '';
                    }
                    $lines[] = '*Aktivitas '.$number.': '.($activity['activity_type'] ?? 'Aktivitas').'*';
                    $lines[] = '*Waktu*: '.$time.' WIB';
                    $lines[] = '*Petugas*: '.($activity['performer_name'] ?? 'Petugas tidak tersedia');
                    $lines[] = '*Catatan*: '.($activity['description'] ?: 'Tanpa catatan');
                    $number++;
                }
            }
        }

        $lines[] = '';
        $lines[] = $showTenant
            ? 'Satu rekap seluruh kebun dalam tenant ini sampai pukul 20:00 WIB.'
            : 'Satu rekap seluruh kebun Anda sampai pukul 20:00 WIB.';
        $lines[] = '';
        $lines[] = '*Detail laporan* (tautan sementara):';
        $lines[] = self::DETAIL_URL;

        return implode("\n", $lines);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }
}
