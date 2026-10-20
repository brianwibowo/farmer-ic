<?php

namespace App\Console\Commands;

use App\AI\Jobs\IndexKnowledgeDocument;
use App\Models\AiKnowledgeDocument;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexKnowledgeCommand extends Command
{
    protected $signature = 'ai:knowledge:reindex
        {--team= : Team slug; defaults to all teams}
        {--force : Re-embed and re-upsert even when content has not changed}
        {--sync : Run jobs synchronously instead of dispatching to queue}
        {--status : Show current index status for all published documents}';

    protected $description = 'Queue reindex jobs for all published knowledge documents';

    public function handle(): int
    {
        if ($this->option('status')) {
            return $this->showStatus();
        }

        $teams = $this->teams();
        if ($teams->isEmpty()) {
            $this->components->error('No teams found.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $sync = (bool) $this->option('sync');
        $dispatched = 0;

        foreach ($teams as $team) {
            $documents = $team->aiKnowledgeDocuments()
                ->where('status', AiKnowledgeDocument::STATUS_PUBLISHED)
                ->orderBy('id')
                ->get(['id', 'title']);

            if ($documents->isEmpty()) {
                $this->components->warn("Team [{$team->slug}]: no published documents.");

                continue;
            }

            $this->components->info("Team [{$team->slug}]: dispatching {$documents->count()} document(s)…");

            foreach ($documents as $document) {
                $job = new IndexKnowledgeDocument($document->id, $force);

                if ($sync) {
                    dispatch_sync($job);
                    $this->line("  ✓ {$document->title} (id:{$document->id}) indexed synchronously.");
                } else {
                    dispatch($job);
                    $this->line("  → {$document->title} (id:{$document->id}) dispatched.");
                }

                $dispatched++;
            }
        }

        $verb = $sync ? 'Indexed' : 'Dispatched';
        $this->components->info("{$verb} {$dispatched} document(s).");

        return self::SUCCESS;
    }

    private function showStatus(): int
    {
        $teams = $this->teams();

        $rows = [];
        foreach ($teams as $team) {
            $documents = $team->aiKnowledgeDocuments()
                ->where('status', AiKnowledgeDocument::STATUS_PUBLISHED)
                ->orderBy('id')
                ->get(['id', 'title', 'index_status', 'index_version', 'indexed_at', 'index_error']);

            foreach ($documents as $document) {
                $rows[] = [
                    'team' => $team->slug ?? $team->id,
                    'id' => $document->id,
                    'title' => mb_substr($document->title, 0, 40),
                    'index_status' => $document->index_status ?? 'pending',
                    'index_version' => $document->index_version ?? '—',
                    'indexed_at' => $document->indexed_at?->toDateTimeString() ?? '—',
                    'error' => $document->index_error ? mb_substr($document->index_error, 0, 60) : '—',
                ];
            }
        }

        if ($rows === []) {
            $this->components->warn('No published documents found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Team', 'ID', 'Title', 'Index Status', 'Version', 'Indexed At', 'Error'],
            $rows,
        );

        return self::SUCCESS;
    }

    /** @return Collection<int, Team> */
    private function teams(): Collection
    {
        $slug = $this->option('team');
        if (is_string($slug) && trim($slug) !== '') {
            $team = Team::query()->where('slug', trim($slug))->first();

            return $team ? collect([$team]) : collect();
        }

        return Team::query()->orderBy('id')->get();
    }
}
