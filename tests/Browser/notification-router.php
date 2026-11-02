<?php

// A test-only PHP built-in-server router. Not registered in application routes.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$public = realpath(__DIR__.'/../../public');
$asset = realpath($public.'/'.$path);
if ($asset !== false && str_starts_with($asset, $public.DIRECTORY_SEPARATOR) && is_file($asset)) {
    return false;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql_testing') {
    throw new LogicException('Browser router is testing-only.');
}
Tests\Support\TestDatabase::assertSafe(config('database.connections.pgsql_testing.database'), config('database.connections.pgsql.database'));
config(['inertia.ssr.enabled' => false, 'operational_notifications.fonnte.enabled' => false]);
$app->make(Illuminate\Foundation\Vite::class)->useHotFile(storage_path('framework/notification-browser-disabled-hot'));
$app->handleRequest(Illuminate\Http\Request::capture());
