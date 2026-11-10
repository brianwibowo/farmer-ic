<?php

// Standalone, committed PostgreSQL fixture so independent workers see the same
// data. Never run against the application's local/demo database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql_testing') {
    throw new LogicException('Requires the testing PostgreSQL connection.');
}
$connection = config('database.connections.pgsql_testing');
Tests\Support\TestDatabase::assertSafe($connection['database'], config('database.connections.pgsql.database'));
if (! str_ends_with($connection['database'], '_daily_log_concurrency')) {
    throw new LogicException('Requires a dedicated *_daily_log_concurrency database.');
}
if (($argv[1] ?? '') === 'worker') {
    $actor = App\Models\User::findOrFail($argv[2]);
    $task = App\Models\PlantingCycleTask::findOrFail($argv[3]);
    Illuminate\Support\Facades\Gate::forUser($actor)->authorize('recordTask', [App\Models\FarmActivity::class, $task]);
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/daily-log-concurrency')]);
    $activity = app(App\Services\Activity\TaskActivityService::class)->create($actor, $task, [
        'work_date' => '2026-11-03', 'start_time' => '07:00', 'end_time' => '16:00', 'worker_count' => (int) $argv[4],
        'daily_notes' => 'Tim harian bersama.', 'notes' => 'Pengairan dari worker paralel.',
        'details' => ['area_ha' => '1', 'water_source' => 'Sumur', 'method' => 'Pompa', 'duration_hours' => '1'],
        'photos' => [new Illuminate\Http\UploadedFile(public_path('images/logo.png'), 'bukti.png', 'image/png', null, true)],
    ]);
    echo $activity->id.PHP_EOL;
    exit(0);
}
Tests\Support\TestDatabase::ensureExists($connection);
Tests\Support\TestDatabase::resetConnection('pgsql_testing');
$team = App\Models\Team::factory()->create(['is_personal' => false]);
$actors = collect([3, 8])->map(function (int $count) use ($team) {
    $actor = App\Models\User::factory()->create(['role' => 'petugas']);
    $team->members()->attach($actor, ['role' => 'member']);
    $actor->forceFill(['current_team_id' => $team->id])->save();

    return $actor;
});
$farm = new App\Models\Farm(['name' => 'Kebun Uji Paralel', 'land_tenure' => 'owned', 'total_area_ha' => '5']);
$farm->forceFill(['team_id' => $team->id, 'created_by' => $actors[0]->id])->save();
foreach ($actors as $actor) {
    $actor->forceFill(['assigned_farm_id' => $farm->id])->save();
}
$commodity = App\Models\Commodity::create(['name' => 'Jagung']);
$variety = App\Models\CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Bisi 18']);
$cycle = App\Models\PlantingCycle::create(['farm_id' => $farm->id, 'variety_id' => $variety->id, 'planted_at' => '2026-11-01', 'planted_area_ha' => '2', 'status' => 'active']);
$stage = new App\Models\PlantingCycleStage;
$stage->forceFill(['planting_cycle_id' => $cycle->id, 'code' => 'maintenance', 'position' => 5, 'status' => 'in_progress', 'started_at' => now()])->save();
$task = App\Models\PlantingCycleTask::create([
    'planting_cycle_id' => $cycle->id, 'planting_cycle_stage_id' => $stage->id,
    'activity_category' => 'maintenance', 'activity_subcategory' => 'irrigation', 'name' => 'Pengairan paralel',
    'offset_days' => 2, 'tolerance_days' => 2, 'status' => 'pending', 'is_required' => true,
]);
App\Models\ActivityType::firstOrCreate(['code' => 'irrigation'], ['name' => 'Pengairan']);
$secondTask = $task->replicate();
$secondTask->save();
$tasks = [$task, $secondTask];
$processes = [];
foreach ($actors as $index => $actor) {
    $process = new Symfony\Component\Process\Process([PHP_BINARY, __FILE__, 'worker', $actor->id, $tasks[$index]->id, (string) [3, 8][$index]], base_path(), null, null, 60);
    $process->start();
    $processes[] = $process;
}
foreach ($processes as $process) {
    $process->wait();
    if (! $process->isSuccessful()) {
        throw new RuntimeException($process->getOutput().$process->getErrorOutput());
    }
}
$log = App\Models\DailyActivityLog::visibleTo($actors[0], $team)->sole();
$activities = App\Models\FarmActivity::visibleTo($actors[0], $team)->where('daily_activity_log_id', $log->id)->orderBy('position')->get();
$positions = $activities->pluck('position')->all();
$workerCounts = $activities->pluck('worker_count')->all();
sort($workerCounts);
if ($positions !== [1, 2] || $workerCounts !== [3, 8]) {
    throw new RuntimeException('Concurrent appends duplicated/overwrote the header, position, or activity worker count.');
}
echo json_encode(['headers' => 1, 'activities' => 2, 'positions' => $positions, 'workers' => $workerCounts], JSON_THROW_ON_ERROR).PHP_EOL;
