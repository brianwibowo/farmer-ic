<?php

namespace App\Notifications;

use App\Notifications\Channels\InAppChannel;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

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

    protected function title(): string
    {
        return 'Reminder Tugas SOP';
    }

    /** @return array{title: string, message: string} */
    public function toInApp(): array
    {
        $tasks = $this->data['tasks'] ?? [];
        if (! is_array($tasks)) {
            throw new \InvalidArgumentException('Notification tasks must be an array.');
        }
        $groups = collect($tasks)->groupBy(fn (array $task): string => json_encode([
            $task['farm_id'], $task['block_id'], $task['name'], $task['scheduled_date'], $task['due_date'],
        ], JSON_THROW_ON_ERROR));
        $lines = [];
        foreach ($groups as $tasks) {
            $task = $tasks->first();
            $date = CarbonImmutable::parse($task['scheduled_date'], config('app.timezone'))->settings(['locale' => 'id'])->translatedFormat('j F Y');
            $due = CarbonImmutable::parse($task['due_date'], config('app.timezone'))->settings(['locale' => 'id'])->translatedFormat('j F Y');
            $label = $this instanceof SopDeviationNotification
                ? 'Belum selesai melewati toleransi'
                : ($task['days_before'] === 0 ? 'H-0 (hari ini)' : 'H-'.$task['days_before']);
            if ($lines !== []) {
                $lines[] = '';
            }
            array_push($lines, 'Kebun: '.$task['farm_name'], 'Blok: '.$task['block_name'],
                'Tugas: '.$task['name'].' — '.$label, 'Jumlah tugas: '.$tasks->count(),
                'Jadwal: '.$date, 'Batas toleransi: '.$due);
        }

        return ['title' => $this->title(), 'message' => implode("\n", $lines)];
    }

    public function toWhatsAppText(): string
    {
        $content = $this->toInApp();

        return '*'.$content['title']."*\n\n".$content['message'];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }
}
