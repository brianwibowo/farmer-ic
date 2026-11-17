<?php

// Isolated browser fixture; never run against the application database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql_testing') {
    throw new LogicException('Browser fixture requires testing / pgsql_testing.');
}
$connection = config('database.connections.pgsql_testing');
Tests\Support\TestDatabase::assertSafe($connection['database'], config('database.connections.pgsql.database'));
if (! str_ends_with($connection['database'], '_notifications_browser')) {
    throw new LogicException('Use a dedicated *_notifications_browser test database.');
}
Tests\Support\TestDatabase::ensureExists($connection);
Tests\Support\TestDatabase::resetConnection('pgsql_testing');
$team = App\Models\Team::factory()->create(['name' => 'Tenant Browser Notifikasi', 'is_personal' => false]);
$secondTeam = App\Models\Team::factory()->create(['name' => 'Tenant Kebun Bandung', 'is_personal' => false]);
$ids = [];
foreach (['admin', 'owner', 'petugas'] as $role) {
    $user = App\Models\User::factory()->create([
        'role' => $role, 'name' => ucfirst($role).' Browser Notifikasi',
        'email' => $role.'@notifications-ui.example', 'password' => 'Notification-UI-Only-42!',
        'phone_number' => '6281200000000',
    ]);
    $team->members()->attach($user, ['role' => 'member']);
    $ids[$role] = $user->id;
    $user->forceFill(['current_team_id' => $team->id])->save();
    if ($role === 'admin') {
        $demoFarm = new App\Models\Farm(['name' => 'Kebun Cianjur', 'land_tenure' => 'owned', 'total_area_ha' => 10]);
        $demoFarm->forceFill(['team_id' => $team->id, 'created_by' => $user->id])->save();
        $demoBlocks = [];
        $reminderTasks = [];
        $commodity = App\Models\Commodity::create(['name' => 'Jagung']);
        $variety = App\Models\CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Bisi 18']);
        $sorghum = App\Models\Commodity::create(['name' => 'Sorgum']);
        $sorghumVariety = App\Models\CropVariety::create(['commodity_id' => $sorghum->id, 'name' => 'Bioguma']);
        foreach (['A', 'B'] as $code) {
            $block = app(App\Services\FarmBlockService::class)->create($demoFarm, $user, $team, ['code' => $code, 'name' => 'Blok '.$code, 'area_ha' => 5]);
            foreach ($code === 'A' ? ['A1', 'A2'] : ['B1'] as $subblock) {
                $days = match ($subblock) {
                    'A1' => 0,
                    'A2' => 1,
                    default => 3,
                };
                $cycle = app(App\Services\FarmBlockService::class)->createBatch($block, $user, $team, ['subblock_label' => $subblock, 'reserved_area_ha' => 2, 'planned_planting_at' => today()->subDays(20)->toDateString()]);
                $cycle->forceFill(['variety_id' => ($code === 'A' ? $variety : $sorghumVariety)->id, 'planted_at' => today()->subDays(20), 'estimated_harvest_at' => today()->addDays($days), 'planted_area_ha' => 2, 'status' => 'active'])->save();
                $harvestTask = App\Models\PlantingCycleTask::create([
                    'planting_cycle_id' => $cycle->id, 'planting_cycle_stage_id' => $cycle->stages()->where('code', 'harvest')->firstOrFail()->id,
                    'name' => $code === 'A' ? 'Panen Jagung' : 'Panen Sorgum',
                    'activity_category' => 'harvest', 'activity_subcategory' => null,
                    'offset_days' => 20 + $days, 'tolerance_days' => 2, 'scheduled_date' => today()->addDays($days), 'due_date' => today()->addDays($days + 2), 'status' => 'pending', 'is_required' => true,
                ]);
                $reminderTasks[] = ['id' => $harvestTask->id, 'cycle_id' => $cycle->id,
                    'subblock_name' => 'Subblok '.$cycle->subblock_label,
                    'commodity_id' => ($code === 'A' ? $commodity : $sorghum)->id,
                    'commodity_name' => ($code === 'A' ? $commodity : $sorghum)->name,
                    'farm_id' => $demoFarm->id, 'farm_name' => $demoFarm->name,
                    'block_id' => $block->id, 'block_name' => $block->name, 'name' => $harvestTask->name,
                    'scheduled_date' => $harvestTask->scheduled_date->toDateString(),
                    'due_date' => $harvestTask->due_date->toDateString(), 'days_before' => $days];
                if ($subblock === 'A1') {
                    App\Models\PlantingCycleTask::create([
                        'planting_cycle_id' => $cycle->id, 'planting_cycle_stage_id' => $cycle->stages()->where('code', 'maintenance')->firstOrFail()->id,
                        'name' => 'Pemupukan dasar', 'activity_category' => 'maintenance', 'activity_subcategory' => null,
                        'offset_days' => 0, 'tolerance_days' => 2, 'scheduled_date' => today()->subDays(4), 'due_date' => today()->subDays(2), 'status' => 'pending', 'is_required' => true,
                    ]);
                }
            }
            $demoBlocks[$code] = $block->id;
        }
    }
    if ($role === 'petugas') {
        $user->forceFill(['assigned_farm_id' => $demoFarm->id])->save();
    }
    if ($role === 'owner') {
        $secondTeam->members()->attach($user, ['role' => 'member']);
    }
    $period = ['period_start' => now()->startOfWeek()->toIso8601String(), 'period_end' => now()->toIso8601String()];
    if ($role === 'owner') {
        $farms = [];
        foreach (['Bogor', 'Bandung'] as $index => $region) {
            $farms[] = [
                'id' => 'farm-'.$index, 'name' => 'Kebun '.$region,
                'region_key' => strtolower($region), 'region_name' => $region, 'unidentified_batches' => 0,
                'crops' => array_map(fn ($name, $target, $harvest) => [
                    'name' => $name, 'target_ton' => $target, 'target_missing' => 0,
                    'harvest_ton' => $harvest, 'harvest_area_ha' => $harvest > 0 ? 0.75 : 0,
                    'harvest_count' => $harvest > 0 ? 2 : 0, 'harvest_missing' => 0,
                ], ['Jagung', 'Rumput', 'Sorgum'], [240.5, 125, 90], $index === 0 ? [4.5, 2.25, 0] : [0, 0, 0]),
            ];
        }
        $template = new App\Notifications\WeeklyReportNotification([...$period, 'audience' => 'owner', 'farms' => $farms]);
    } elseif ($role === 'admin') {
        $template = new App\Notifications\WeeklyReportNotification([...$period, 'audience' => 'admin', 'farms' => [
            ['id' => $demoFarm->id, 'name' => 'Kebun Cianjur', 'activity_count' => 10, 'blocks' => [
                ['id' => 'a', 'name' => 'Blok A', 'activity_counts' => ['Penyiraman' => 3, 'Pemupukan' => 1, 'Laporan insiden' => 2, 'Panen' => 1], 'harvest_count' => 1, 'harvest_ton' => 2.5, 'harvest_missing' => 0,
                    'obstacle_count' => 1, 'obstacle_summary' => 'Pengairan terkendala pompa; perbaikan telah dijadwalkan.',
                    'incidents' => ['Hama · Ringan' => 1, 'Kerusakan alat · Sedang' => 1]],
                ['id' => 'b', 'name' => 'Blok B', 'activity_counts' => ['Penyiangan' => 3],
                    'obstacle_count' => 0, 'obstacle_summary' => null, 'incidents' => []],
            ]],
        ]]);
    } else {
        $template = new App\Notifications\TaskReminderNotification(['tasks' => $reminderTasks]);
    }
    $rich = $template->toInApp();
    (new App\Models\Notification)->forceFill([
        'user_id' => $user->id, 'team_id' => $role === 'owner' ? null : $team->id,
        'team_ids' => $role === 'owner' ? json_encode([$team->id, $secondTeam->id], JSON_THROW_ON_ERROR) : null,
        'type' => $role === 'petugas' ? 'task_reminder' : 'weekly_report',
        'title' => $rich['title'], 'message' => $rich['message'], 'content' => $rich['content'],
        'created_at' => now()->addSeconds(2),
    ])->save();
    if ($role === 'petugas') {
        (new App\Models\Notification)->forceFill([
            'user_id' => $ids['admin'], 'team_id' => $team->id, 'type' => 'task_reminder',
            'title' => $rich['title'], 'message' => $rich['message'], 'content' => $rich['content'],
            'created_at' => now()->addSeconds(3),
        ])->save();
    }
    if ($role === 'admin') {
        $incidentActivity = Illuminate\Support\Facades\DB::transaction(function () use ($demoFarm) {
            $reporter = App\Models\User::factory()->create(['role' => 'petugas']);
            $type = App\Models\ActivityType::firstOrCreate(['code' => 'incident'], ['name' => 'Insiden']);
            $activity = App\Models\FarmActivity::create(['farm_id' => $demoFarm->id, 'performed_by' => $reporter->id, 'activity_type_id' => $type->id, 'activity_date' => now(), 'description' => 'Pompa pengairan berhenti beroperasi. Pemeriksaan teknisi diperlukan.']);
            $activity->incidentDetail()->create(['incident_category' => 'Kerusakan alat', 'severity' => 'light']);

            return $activity;
        });
        $incident = (new App\Notifications\IncidentReportNotification(['incidents' => [[
            'id' => $incidentActivity->id, 'farm_id' => $demoFarm->id, 'farm_name' => $demoFarm->name,
            'date' => now()->toIso8601String(), 'category' => 'Kerusakan alat', 'severity' => 'Ringan', 'description' => $incidentActivity->description,
        ]]]))->toInApp();
        (new App\Models\Notification)->forceFill(['user_id' => $user->id, 'team_id' => $team->id, 'type' => 'incident_report',
            'title' => $incident['title'], 'message' => $incident['message'], 'content' => $incident['content'], 'created_at' => now()->subSecond()])->save();
        $deviation = (new App\Notifications\SopDeviationNotification(['tasks' => [[
            'farm_id' => $demoFarm->id, 'farm_name' => 'Kebun Cianjur', 'block_id' => $demoBlocks['A'], 'block_name' => 'Blok A',
            'name' => 'Pemupukan dasar', 'scheduled_date' => now()->subDays(4)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(), 'days_before' => -4,
            'reason' => 'Belum selesai melewati toleransi jadwal efektif',
        ]]]))->toInApp();
        (new App\Models\Notification)->forceFill([
            'user_id' => $user->id, 'team_id' => $team->id, 'type' => 'sop_deviation',
            'title' => $deviation['title'], 'message' => $deviation['message'], 'content' => $deviation['content'],
            'created_at' => now()->addSecond(),
        ])->save();
    }
}
$empty = App\Models\User::factory()->create([
    'role' => 'petugas', 'name' => 'Petugas Inbox Kosong', 'email' => 'empty@notifications-ui.example',
    'password' => 'Notification-UI-Only-42!',
]);
$team->members()->attach($empty, ['role' => 'member']);
$empty->forceFill(['current_team_id' => $team->id])->save();
$ids['today'] = now()->toDateString();
$ids['date_from'] = now()->subDays(30)->toDateString();
$ids['second_team_id'] = $secondTeam->id;
$directory = storage_path('app/notification-browser');
if (! is_dir($directory)) {
    mkdir($directory, 0755, true);
}
file_put_contents($directory.'/fixture-users.json', json_encode($ids, JSON_THROW_ON_ERROR));
echo "Isolated browser notification fixtures ready.\n";
