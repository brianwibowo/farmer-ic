<?php

namespace App\Notifications;

final class IncidentReportNotification extends TaskReminderNotification
{
    protected function title(): string
    {
        return 'Laporan Insiden Baru';
    }

    /** @return array<string, mixed> */
    public function toInboxContent(): array
    {
        return ['version' => 1, 'kind' => 'incident_report', 'incidents' => $this->data['incidents']];
    }

    /** @return array{title: string, message: string, content: array<string, mixed>} */
    public function toInApp(): array
    {
        return ['title' => $this->title(), 'message' => 'Petugas telah melaporkan insiden baru.', 'content' => $this->toInboxContent()];
    }
}
