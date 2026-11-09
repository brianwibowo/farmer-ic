<?php

// Read-only release gate. Expected targets are supplied separately from the
// container environment so a valid connection to the wrong stack fails closed.
if ($argc !== 7 || in_array('', array_slice($argv, 1), true)) {
    fwrite(STDERR, "Usage: php docker/verify-release-target.php <database> <database-host> <redis-host> <redis-prefix> <qdrant-url> <qdrant-collection>\n");
    exit(1);
}

[$script, $expectedDatabase, $expectedDatabaseHost, $expectedRedisHost, $expectedRedisPrefix, $expectedQdrantUrl, $expectedCollection] = $argv;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$fail = static function (string $message): never {
    fwrite(STDERR, "Release target check failed: {$message}\n");
    exit(1);
};

try {
    // URL settings override host/database configuration. Disallow them for this
    // deployment rather than parsing or accidentally logging credentials.
    if (config('database.default') !== 'pgsql'
        || filled(config('database.connections.pgsql.url'))
        || filled(config('database.redis.default.url'))
        || filled(config('database.redis.cache.url'))) {
        $fail('use explicit PostgreSQL/Redis settings without connection URLs.');
    }

    if (! in_array(config('queue.connections.database.connection'), [null, '', 'pgsql'], true)
        || config('queue.connections.redis.connection') !== 'default') {
        $fail('queue connections do not use the checked database and Redis settings.');
    }

    if (config('database.redis.default.host') !== $expectedRedisHost
        || config('database.redis.cache.host') !== $expectedRedisHost
        || (string) config('database.redis.default.port') !== '6379'
        || (string) config('database.redis.cache.port') !== '6379'
        || config('database.redis.options.prefix') !== $expectedRedisPrefix) {
        $fail('Redis host, port or prefix does not match the approved target.');
    }

    if (config('ai.qdrant.url') !== $expectedQdrantUrl
        || config('ai.qdrant.collection') !== $expectedCollection) {
        $fail('Qdrant URL or collection does not match the approved target.');
    }

    $database = $app['db']->connection();
    if ($database->getDriverName() !== 'pgsql'
        || $database->getConfig('host') !== $expectedDatabaseHost
        || (string) $database->getConfig('port') !== '5432'
        || $database->getDatabaseName() !== $expectedDatabase
        || $database->selectOne('SELECT current_database() AS name')->name !== $expectedDatabase) {
        $fail('connected PostgreSQL database, host or port does not match the approved target.');
    }
} catch (Throwable) {
    // Driver exceptions can contain credentials or connection strings.
    $fail('could not verify the configured targets.');
}

fwrite(STDOUT, "Release target check passed.\n");
