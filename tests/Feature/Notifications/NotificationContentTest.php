<?php

namespace Tests\Feature\Notifications;

use App\Notifications\Channels\InAppChannel;
use App\Notifications\DailyReportNotification;
use App\Notifications\HarvestReminderNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationContentTest extends TestCase
{
    public function test_daily_report_owns_the_template_and_both_channel_formats(): void
    {
        $notification = new DailyReportNotification([
            'team_name' => 'Tenant Jawa Barat', 'event_date' => '2026-10-30', 'show_tenant' => true,
            'farms' => [
                ['id' => 'farm-cianjur', 'name' => 'Kebun Cianjur', 'activities' => [
                    ['occurred_at' => '2026-10-30T02:00:00+00:00', 'performer_name' => 'Rina', 'activity_type' => 'Penyiraman', 'description' => 'Menyiram Blok A'],
                    ['occurred_at' => '2026-10-30T10:00:00+07:00', 'performer_name' => null, 'activity_type' => null, 'description' => null],
                ]],
                ['id' => 'farm-bogor', 'name' => 'Kebun Bogor', 'activities' => []],
            ],
        ]);
        $content = $notification->toInApp();
        $this->assertSame('Report Harian Kebun dari Petugas', $content['title']);
        $this->assertStringContainsString('Tenant: Tenant Jawa Barat', $content['message']);
        $this->assertStringContainsString('09:00 | Petugas: Rina | Penyiraman | Menyiram Blok A', $content['message']);
        $this->assertStringContainsString('10:00 | Petugas: Petugas tidak tersedia | Aktivitas | Tanpa catatan', $content['message']);
        $whatsApp = $notification->toWhatsAppText();
        $this->assertStringStartsWith("*Report Harian Kebun dari Petugas*\n\nSalam hormat Bapak/Ibu,", $whatsApp);
        $this->assertStringContainsString("*Tenant*: Tenant Jawa Barat\n*Tanggal*: 30 Oktober 2026\n*Jumlah kebun*: 2\n*Batas rekap*: 20:00 WIB", $whatsApp);
        $this->assertStringContainsString("\n────────────────\n*1. Kebun: Kebun Cianjur*\n*Jumlah aktivitas*: 2\n\n", $whatsApp);
        $this->assertStringContainsString("*Aktivitas 1: Penyiraman*\n*Waktu*: 09:00 WIB\n*Petugas*: Rina\n*Catatan*: Menyiram Blok A", $whatsApp);
        $this->assertStringContainsString("\n\n*Aktivitas 2: Aktivitas*\n*Waktu*: 10:00 WIB\n*Petugas*: Petugas tidak tersedia\n*Catatan*: Tanpa catatan", $whatsApp);
        $this->assertStringContainsString("\n────────────────\n*2. Kebun: Kebun Bogor*\n*Jumlah aktivitas*: 0\n\nPetugas belum mengisi aktivitas hari ini", $whatsApp);
        $this->assertStringContainsString("\n\n2. Kebun: Kebun Bogor\nPetugas belum mengisi aktivitas hari ini", $content['message']);
        $this->assertStringNotContainsString(' | ', $whatsApp);
        $this->assertStringEndsWith("\n\n*Detail laporan* (tautan sementara):\nhttps://example.com/laporan-harian-kebun", $whatsApp);
        $this->assertStringNotContainsString('https://example.com/', $content['message']);
        $this->assertSame([InAppChannel::class], $notification->via(new \stdClass));
    }

    public function test_empty_daily_report_template_contains_the_required_message(): void
    {
        $notification = new DailyReportNotification([
            'team_name' => 'Tenant Jawa Barat', 'event_date' => '2026-10-30',
            'farms' => [['id' => 'farm-bogor', 'name' => 'Kebun Bogor', 'activities' => []]],
        ]);
        $this->assertStringContainsString('Petugas belum mengisi aktivitas hari ini', $notification->toInApp()['message']);
        $this->assertStringContainsString('*1. Kebun: Kebun Bogor*', $notification->toWhatsAppText());
        $this->assertStringContainsString("*Jumlah aktivitas*: 0\n\nPetugas belum mengisi aktivitas hari ini\n\n", $notification->toWhatsAppText());
        $this->assertStringEndsWith('https://example.com/laporan-harian-kebun', $notification->toWhatsAppText());
        $this->assertStringNotContainsString('tenant', strtolower($notification->toWhatsAppText()));
        $this->assertStringNotContainsString('Tenant Jawa Barat', $notification->toInApp()['message']);
    }

    public static function milestones(): array
    {
        return [[7, 'H-7'], [3, 'H-3'], [0, 'H-0 (Hari Panen)']];
    }

    #[DataProvider('milestones')]
    public function test_harvest_template_formats_each_milestone(int $days, string $label): void
    {
        $notification = new HarvestReminderNotification([
            'days_before' => $days, 'harvest_date' => '2026-11-06', 'farm_name' => 'Kebun Cianjur',
            'cycle_id' => 'siklus-jagung', 'recipient_name' => 'Rina',
        ]);
        $content = $notification->toInApp();
        $this->assertSame('Panen Reminder '.$label, $content['title']);
        $this->assertStringContainsString('6 November 2026', $notification->toWhatsAppText());
        $this->assertStringContainsString('6 November 2026', $content['message']);
        $this->assertStringStartsWith('*Panen Reminder '.$label."*\n\nHalo Rina,\n", $notification->toWhatsAppText());
        $this->assertStringContainsString("\n\n*Kebun*: Kebun Cianjur\n*Tanggal panen*: 6 November 2026\n*Petugas*: Rina\n*Siklus*: siklus-jagung\n\n", $notification->toWhatsAppText());
        $this->assertStringContainsString($days === 0 ? 'Hari ini adalah jadwal panen kebun Anda.' : 'Jadwal panen kebun Anda tinggal '.$days.' hari lagi.', $notification->toWhatsAppText());
        $this->assertStringEndsWith("\n\n*Detail jadwal panen* (tautan sementara):\nhttps://example.com/jadwal-panen", $notification->toWhatsAppText());
        $this->assertStringNotContainsString('https://example.com/', $content['message']);
        $this->assertSame([InAppChannel::class], $notification->via(new \stdClass));
    }
}
