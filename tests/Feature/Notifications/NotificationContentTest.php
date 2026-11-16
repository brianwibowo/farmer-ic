<?php

namespace Tests\Feature\Notifications;

use App\Notifications\SopDeviationNotification;
use App\Notifications\TaskReminderNotification;
use Tests\TestCase;

class NotificationContentTest extends TestCase
{
    public function test_reminder_keeps_one_entry_per_distinct_subblock(): void
    {
        $task = ['farm_id' => 'farm', 'farm_name' => 'Lahan Cianjur', 'block_id' => 'block', 'block_name' => 'Blok A',
            'name' => 'Panen Jagung', 'scheduled_date' => '2026-11-17', 'due_date' => '2026-11-19', 'days_before' => 1];
        $first = [...$task, 'cycle_id' => 'cycle-a1', 'subblock_name' => 'Subblok A1'];
        $second = [...$task, 'cycle_id' => 'cycle-a2', 'subblock_name' => 'Subblok A2'];
        $notification = new TaskReminderNotification(['tasks' => [$first, $second, $first]]);
        $items = $notification->toInboxContent()['groups'][0]['items'];
        $this->assertCount(1, $items);
        $this->assertSame([
            ['id' => 'cycle-a1', 'name' => 'Subblok A1'],
            ['id' => 'cycle-a2', 'name' => 'Subblok A2'],
        ], $items[0]['subblocks']);
    }

    public function test_task_messages_roll_up_identical_subblock_tasks_to_their_block(): void
    {
        $task = ['farm_id' => 'farm', 'farm_name' => 'Kebun Cianjur', 'block_id' => 'block', 'block_name' => 'Blok Utara',
            'name' => 'Pengairan awal', 'scheduled_date' => '2026-11-16', 'due_date' => '2026-11-18', 'days_before' => 1];
        $reminder = new TaskReminderNotification(['tasks' => [$task, $task]]);
        $message = $reminder->toInApp()['message'];
        $this->assertSame(1, substr_count($message, 'Tugas: Pengairan awal'));
        $this->assertStringContainsString('Jumlah subblok: 2', $message);
        $this->assertStringNotContainsString('Batas toleransi:', $message);
        $this->assertStringContainsString('H-1', $message);
        $this->assertStringContainsString('Blok: Blok Utara', $message);
        $this->assertStringContainsString('/notifications', $reminder->toWhatsAppText());
        $this->assertStringNotContainsString('Kebun Cianjur', $reminder->toWhatsAppText());
        $this->assertStringContainsString('16 November 2026', $message);
        $this->assertStringNotContainsString('Subblok', $message);
        $deviation = new SopDeviationNotification(['tasks' => [$task]]);
        $this->assertStringNotContainsString('Kebun Cianjur', $deviation->toWhatsAppText());
        $this->assertSame('Laporan Ketidaksesuaian SOP', $deviation->toInApp()['title']);
        $this->assertStringContainsString('Belum selesai melewati toleransi', $deviation->toInApp()['message']);
        $this->assertStringNotContainsString('H-1', $deviation->toWhatsAppText());
    }
}
