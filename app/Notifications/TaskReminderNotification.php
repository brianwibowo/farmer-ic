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

    /** @return list<array<string, mixed>> */
    private function groupedTasks(): array
    {
        $tasks = $this->data['tasks'] ?? [];
        if (! is_array($tasks)) {
            throw new \InvalidArgumentException('Notification tasks must be an array.');
        }
        $groups = collect($tasks)->groupBy(fn (array $task): string => json_encode([
            $task['farm_id'], $task['block_id'], $task['name'], $task['scheduled_date'], $task['due_date'],
        ], JSON_THROW_ON_ERROR));

        return array_values($groups->map(fn ($tasks): array => [...$tasks->first(), 'count' => $tasks->count()])->all());
    }

    /** @return array<string, mixed> */
    public function toInboxContent(): array
    {
        $groups = [];
        foreach ($this->groupedTasks() as $task) {
            $key = $task['farm_id'].':'.($task['block_id'] ?? 'unassigned');
            $groups[$key] ??= ['key' => $key, 'farm_name' => $task['farm_name'], 'block_name' => $task['block_name'], 'items' => []];
            $groups[$key]['items'][] = [
                'name' => $task['name'], 'count' => $task['count'], 'scheduled_date' => $task['scheduled_date'],
                'due_date' => $task['due_date'], 'days_before' => $task['days_before'],
            ];
        }

        return ['version' => 1, 'kind' => $this instanceof SopDeviationNotification ? 'sop_deviation' : 'task_reminder',
            'groups' => array_values($groups)];
    }

    /** @return array{title: string, message: string, content: array<string, mixed>} */
    public function toInApp(): array
    {
        $lines = [];
        foreach ($this->groupedTasks() as $task) {
            $date = CarbonImmutable::parse($task['scheduled_date'], config('app.timezone'))->settings(['locale' => 'id'])->translatedFormat('j F Y');
            $due = CarbonImmutable::parse($task['due_date'], config('app.timezone'))->settings(['locale' => 'id'])->translatedFormat('j F Y');
            $label = $this instanceof SopDeviationNotification
                ? 'Belum selesai melewati toleransi'
                : ($task['days_before'] === 0 ? 'H-0 (hari ini)' : 'H-'.$task['days_before']);
            if ($lines !== []) {
                $lines[] = '';
            }
            array_push($lines, 'Kebun: '.$task['farm_name'], 'Blok: '.$task['block_name'],
                'Tugas: '.$task['name'].' — '.$label, 'Jumlah tugas: '.$task['count'],
                'Jadwal: '.$date, 'Batas toleransi: '.$due);
        }

        return ['title' => $this->title(), 'message' => implode("\n", $lines), 'content' => $this->toInboxContent()];
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
