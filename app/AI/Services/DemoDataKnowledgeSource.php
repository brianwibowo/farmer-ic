<?php

namespace App\AI\Services;

use App\Models\AiKnowledgeDocument;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\File;

/** Normalizes the origin/dev demo snapshot into searchable PostgreSQL documents. */
class DemoDataKnowledgeSource
{
    /** @return array<int, array<string, mixed>> */
    public function documents(): array
    {
        $path = config('ai.knowledge.demo_data_path', resource_path('js/data/farmDemoData.json'));
        $raw = is_file($path) ? json_decode((string) File::get($path), true) : [];
        if (! is_array($raw)) {
            return [];
        }
        $docs = [];
        foreach ((array) ($raw['kebun'] ?? []) as $item) {
            $id = (string) ($item['id'] ?? uniqid('kebun-', true));
            $docs[] = $this->doc("demo-kebun-{$id}", 'Kebun '.$item['name'], 'garden', $item);
        }
        foreach ((array) ($raw['kebunInsights'] ?? []) as $id => $item) {
            $docs[] = $this->doc("demo-insight-{$id}", 'Insight kebun '.$id, 'insight', ['id' => $id, ...$item]);
        }
        foreach ((array) ($raw['activityLogs'] ?? []) as $item) {
            $id = (string) ($item['id'] ?? uniqid('activity-', true));
            $docs[] = $this->doc("demo-activity-{$id}", 'Aktivitas kebun '.$id, 'activity', $item);
        }
        if (isset($raw['finance'])) {
            $docs[] = $this->doc('demo-finance-origin-dev', 'Finance demo origin/dev', 'finance', $raw['finance']);
        }
        if (isset($raw['report'])) {
            $docs[] = $this->doc('demo-report-origin-dev', 'Report demo origin/dev', 'report', $raw['report']);
        }

        return $docs;
    }

    public function sync(Team $team, User $user): int
    {
        $count = 0;
        foreach ($this->documents() as $document) {
            AiKnowledgeDocument::query()->updateOrCreate(
                ['team_id' => $team->id, 'source' => 'farmDemoData.json origin/dev', 'title' => $document['title']],
                [
                    'created_by' => $user->id,
                    'content' => $document['content'],
                    'content_hash' => hash('sha256', $document['content']),
                    'status' => AiKnowledgeDocument::STATUS_PUBLISHED,
                    'locale' => 'id-ID',
                    'domain' => $document['domain'],
                    'version' => 1,
                    'published_at' => now(),
                ],
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: string, title: string, domain: string, content: string}
     */
    private function doc(string $id, string $title, string $domain, array $data): array
    {
        return ['id' => $id, 'title' => $title, 'domain' => $domain, 'content' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
    }
}
