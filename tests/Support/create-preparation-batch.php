<?php

// A real second PHP/PostgreSQL connection for the concurrency integration test.
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingRound;
use App\Models\User;
use App\Services\FarmBlockService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\Support\TestDatabase;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$workerApp = require dirname(__DIR__, 2).'/bootstrap/app.php';
$workerApp->make(Kernel::class)->bootstrap();
TestDatabase::assertSafe((string) config('database.connections.pgsql_testing.database'), (string) config('database.connections.pgsql.database'));
if (! $workerApp->environment('testing') || config('database.default') !== 'pgsql_testing') {
    throw new LogicException('Concurrency workers may only write to the dedicated testing database.');
}
DB::select("SELECT set_config('application_name', ?, false)", [$argv[3]]);
$actor = User::findOrFail($argv[1]);
$block = FarmBlock::visibleTo($actor, $actor->currentTeam)->findOrFail($argv[2]);
Gate::forUser($actor)->authorize('create', [PlantingCycle::class, $block]);
Gate::forUser($actor)->authorize('create', [PlantingRound::class, $block]);
try {
    app(FarmBlockService::class)->createBatch($block, $actor, $block->farm->team, ['reserved_area_ha' => '2.0000']);
    echo 'created';
} catch (ValidationException $exception) {
    if (! isset($exception->errors()['reserved_area_ha'])) {
        throw $exception;
    }
    echo 'capacity';
}
