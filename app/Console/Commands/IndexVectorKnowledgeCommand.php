<?php

namespace App\Console\Commands;

use App\AI\Services\QdrantKnowledgeIndexer;
use App\Models\Team;
use Illuminate\Console\Command;

class IndexVectorKnowledgeCommand extends Command
{
    protected $signature = 'ai:knowledge:index-vector
        {--team= : Team slug to index; defaults to the first team}
        {--force : Allow indexing even when vector retrieval is disabled}
        {--rebuild : Ignore the local manifest and reconcile the full team corpus}
        {--status : Show the last successful index manifest and Qdrant health}';

    protected $description = 'Embed and upsert the team knowledge corpus into Qdrant';

    public function handle(QdrantKnowledgeIndexer $indexer): int
    {
        if (! config('ai.rag.vector_enabled') && ! $this->option('force')) {
            $this->components->error('AI_RAG_VECTOR_ENABLED=false. Pass --force only when the vector services are configured.');

            return self::FAILURE;
        }

        $team = $this->team();
        if (! $team) {
            $this->components->error('No team found. Create a demo user/team first.');

            return self::FAILURE;
        }

        try {
            if ($this->option('status')) {
                $status = $indexer->status($team);
                $this->line((string) json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;
            }

            $summary = $indexer->indexTeam($team, (bool) $this->option('rebuild'));
        } catch (\RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $verb = $summary['skipped'] ? 'Index already current for' : 'Indexed';
        $this->components->info(sprintf(
            '%s %d documents into %d chunks using %s (%d embedding tokens).',
            $verb,
            $summary['documents'],
            $summary['chunks'],
            $summary['model'] ?: 'configured embedding model',
            $summary['tokens'],
        ));
        if ($summary['removed'] > 0) {
            $this->components->info(sprintf('Removed %d stale vector points.', $summary['removed']));
        }

        return self::SUCCESS;
    }

    private function team(): ?Team
    {
        $slug = $this->option('team');

        return is_string($slug) && trim($slug) !== ''
            ? Team::query()->where('slug', trim($slug))->first()
            : Team::query()->orderBy('id')->first();
    }
}
