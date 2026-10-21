<?php

namespace App\AI\Services;

use App\Models\Team;
use RuntimeException;

/**
 * Small filesystem-backed manifest for vector index lifecycle state.
 *
 * Qdrant remains the serving index; this manifest only records the last
 * successful corpus snapshot so indexing is idempotent and stale points can
 * be removed after a successful upsert. It intentionally avoids a migration.
 */
class IndexManifestStore
{
    public function path(Team $team): string
    {
        $base = trim((string) config('ai.rag.manifest_path', storage_path('app/private/ai-index')));

        return rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'team-'.$team->getKey().'.json';
    }

    /** @return array<string, mixed>|null */
    public function get(Team $team): ?array
    {
        $path = $this->path($team);
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false || trim($contents) === '') {
            return null;
        }

        $manifest = json_decode($contents, true);

        return is_array($manifest) ? $manifest : null;
    }

    /** @param array<string, mixed> $manifest */
    public function put(Team $team, array $manifest): void
    {
        $path = $this->path($team);
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create AI index manifest directory.');
        }

        $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode AI index manifest.');
        }

        // Rename is atomic on the same filesystem, so a reader never sees a
        // partially written manifest during a concurrent status check.
        $temporary = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        if (file_put_contents($temporary, $encoded."\n", LOCK_EX) === false || ! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to persist AI index manifest.');
        }
    }
}
