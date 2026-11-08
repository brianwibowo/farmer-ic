<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use LogicException;
use PDO;

final class TestDatabase
{
    private static bool $prepared = false;

    public static function assertSafe(string $database, string $applicationDatabase): void
    {
        if (! preg_match('/^farmer_ic_(?:crud|schema)_test_[a-z0-9_]+$/', $database)
            || strlen($database) > 63 || $database === $applicationDatabase) {
            throw new LogicException('Refusing to reset an unsafe test database. Use a separate farmer_ic_crud_test_* or farmer_ic_schema_test_* database.');
        }
    }

    /** @param array<string, mixed> $connection */
    public static function ensureExists(array $connection): void
    {
        self::assertSafe($connection['database'], (string) config('database.connections.pgsql.database'));
        $admin = new PDO(
            'pgsql:host='.$connection['host'].';port='.$connection['port'].';dbname=postgres',
            $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $query = $admin->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
        $query->execute([$connection['database']]);
        if (! $query->fetchColumn()) {
            // The identifier is constrained to ASCII letters, digits and underscores above.
            $admin->exec('CREATE DATABASE "'.$connection['database'].'"');
        }
    }

    public static function resetConnection(string $name): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('Database reset is restricted to the testing environment.');
        }
        $settings = config('database.connections.'.$name);
        self::assertSafe($settings['database'], (string) config('database.connections.pgsql.database'));
        $connection = DB::connection($name);
        if ($connection->scalar('SELECT current_database()') !== $settings['database'] || $connection->transactionLevel() !== 0) {
            throw new LogicException('Unexpected database or active transaction; refusing test reset.');
        }
        if (Artisan::call('migrate:fresh', ['--database' => $name, '--force' => true]) !== 0) {
            throw new LogicException('Could not prepare the dedicated test schema: '.Artisan::output());
        }
    }

    public static function prepare(Application $app): void
    {
        if (! $app->environment('testing') || config('database.default') !== 'pgsql_testing') {
            throw new LogicException('Tests require APP_ENV=testing and DB_CONNECTION=pgsql_testing. No database was reset.');
        }
        $connection = config('database.connections.pgsql_testing');
        self::assertSafe($connection['database'], (string) config('database.connections.pgsql.database'));
        if (! self::$prepared) {
            self::ensureExists($connection);
            self::resetConnection('pgsql_testing');
            self::$prepared = true;
            RefreshDatabaseState::$migrated = true;
        }
        // The existing domain tests use transactional aliases of the same test DB.
        if (! getenv('FARMER_CRUD_TEST_DATABASE')) {
            putenv('FARMER_CRUD_TEST_DATABASE='.$connection['database']);
        }
    }
}
