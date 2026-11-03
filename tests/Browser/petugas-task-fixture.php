<?php

// Dedicated browser fixtures only; never seed/reset the application database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql_testing') {
    throw new LogicException('Requires testing / pgsql_testing.');
}
$connection = config('database.connections.pgsql_testing');
Tests\Support\TestDatabase::assertSafe($connection['database'], config('database.connections.pgsql.database'));
if (! str_ends_with($connection['database'], '_petugas_tasks_browser')) {
    throw new LogicException('Requires dedicated *_petugas_tasks_browser database.');
}
Tests\Support\TestDatabase::ensureExists($connection);
Tests\Support\TestDatabase::resetConnection('pgsql_testing');
$team = App\Models\Team::factory()->create(['name' => 'Tenant Pengujian Tugas', 'is_personal' => false]);
$admin = App\Models\User::factory()->create(['role' => 'admin']);
$team->members()->attach($admin, ['role' => 'member']);
$admin->forceFill(['current_team_id' => $team->id])->save();
$petugas = App\Models\User::factory()->create(['role' => 'petugas', 'name' => 'Petugas Pengujian Tugas', 'email' => 'petugas@tasks-ui.example', 'password' => 'Tasks-UI-Only-42!']);
$team->members()->attach($petugas, ['role' => 'member']);
$farm = new App\Models\Farm(['name' => 'Kebun Pengujian Cileuksa', 'land_tenure' => 'owned', 'total_area_ha' => '10']);
$farm->forceFill(['team_id' => $team->id, 'created_by' => $admin->id])->save();
$petugas->forceFill(['current_team_id' => $team->id, 'assigned_farm_id' => $farm->id])->save();
Illuminate\Support\Facades\Gate::forUser($admin)->authorize('create', [App\Models\FarmBlock::class, $farm]);
$block = app(App\Services\FarmBlockService::class)->create($farm, $admin, $team, ['code' => 'B01', 'name' => 'Blok Utara', 'area_ha' => '5']);
Illuminate\Support\Facades\Gate::forUser($admin)->authorize('create', [App\Models\PlantingCycle::class, $farm]);
$cycle = app(App\Services\FarmBlockService::class)->createBatch($block, $admin, $team, ['reserved_area_ha' => '2', 'planned_planting_at' => today()->subDays(21)->toDateString()]);
$commodity = App\Models\Commodity::create(['name' => 'Jagung']);
$variety = App\Models\CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Bisi 18']);
$cycle->forceFill(['planted_at' => today()->subDays(21), 'planted_area_ha' => '2', 'variety_id' => $variety->id, 'status' => 'active'])->save();
Illuminate\Support\Facades\Gate::forUser($admin)->authorize('create', [App\Models\PlantingCycleTask::class, $cycle]);
$task = App\Models\PlantingCycleTask::create([
    'planting_cycle_id' => $cycle->id,
    'planting_cycle_stage_id' => $cycle->stages()->where('code', 'maintenance')->firstOrFail()->id,
    'activity_category' => 'maintenance', 'activity_subcategory' => 'irrigation', 'name' => 'Pengairan sesuai SOP',
    'instructions' => 'Pastikan air merata dan hindari genangan di sekitar batang.',
    'offset_days' => 21, 'tolerance_days' => 2, 'scheduled_date' => today(), 'due_date' => today()->addDays(2),
    'status' => 'pending', 'is_required' => true,
]);
App\Models\ActivityType::firstOrCreate(['code' => 'irrigation'], ['name' => 'Irigasi']);
echo json_encode(['task' => $task->id, 'email' => $petugas->email], JSON_THROW_ON_ERROR).PHP_EOL;
