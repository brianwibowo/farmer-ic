<?php

namespace Tests\Feature\Database;

use Tests\TestCase;

class MigrationLayoutTest extends TestCase
{
    public function test_default_migration_path_contains_one_definition_per_erd_and_runtime_table(): void
    {
        $files = $this->app->make('migrator')->getMigrationFiles([database_path('migrations')]);
        $this->assertNotEmpty($files);
        $this->assertSame([], glob(database_path('migrations/*'), GLOB_ONLYDIR));
        $tables = [];
        foreach ($files as $file) {
            preg_match_all("/Schema::create\('([^']+)'/", file_get_contents($file), $matches);
            array_push($tables, ...$matches[1]);
        }
        $this->assertCount(count($tables), array_unique($tables), 'A table must have exactly one create migration.');
        preg_match_all('/^Table\s+(\w+)\s*\{/m', file_get_contents(database_path('schema.dbml')), $erd);
        $expected = [...$erd[1], 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens'];
        sort($expected);
        sort($tables);
        $this->assertSame($expected, $tables);
    }
}
