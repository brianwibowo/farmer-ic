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
    $title = $role === 'petugas' ? 'Panen Reminder H-7' : 'Report Harian Kebun dari Petugas';
    $message = $role === 'petugas'
            ? "Panen Reminder H-7\nKebun: Kebun Browser Cianjur\nTanggal panen: 06-11-2026\nPeriksa kesiapan panen sesuai jadwal kebun."
            : "Report Harian Kebun dari Petugas\nTenant: Tenant Browser Notifikasi\nJumlah kebun: 1\n\n1. Kebun: Kebun Browser Cianjur\nPetugas belum mengisi aktivitas hari ini";
    $notification = new App\Models\Notification;
    $notification->forceFill([
        'user_id' => $user->id, 'team_id' => $team->id, 'type' => $role === 'petugas' ? 'harvest_reminder' : 'daily_report',
        'title' => $title, 'message' => $message, 'created_at' => now(),
    ])->save();
    for ($day = 1; $day <= 17; $day++) {
        $history = new App\Models\Notification;
        $history->forceFill([
            'user_id' => $user->id, 'team_id' => $team->id, 'type' => $role === 'petugas' ? 'harvest_reminder' : 'daily_report',
            'title' => $title, 'message' => $message, 'is_read' => $day === 1,
            'created_at' => now()->subDays($day)->setTime(20, 0),
        ])->save();
    }
    if ($role === 'owner') {
        $secondTeam->members()->attach($user, ['role' => 'member']);
        $extra = new App\Models\Notification;
        $extra->forceFill([
            'user_id' => $user->id, 'team_id' => $secondTeam->id, 'type' => 'daily_report',
            'title' => $title, 'message' => "Tenant: Tenant Kebun Bandung\nJumlah kebun: 1\n\n1. Kebun: Kebun Bandung\nPetugas belum mengisi aktivitas hari ini", 'created_at' => now(),
        ])->save();
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
            ['id' => 'cianjur', 'name' => 'Kebun Cianjur', 'activity_count' => 9, 'blocks' => [
                ['id' => 'a', 'name' => 'Blok A', 'activity_counts' => ['Penyiraman' => 3, 'Pemupukan' => 1, 'Laporan insiden' => 2],
                    'obstacle_count' => 1, 'obstacle_summary' => 'Pengairan terkendala pompa; perbaikan telah dijadwalkan.',
                    'incidents' => ['Hama · Ringan' => 1, 'Kerusakan alat · Sedang' => 1]],
                ['id' => 'b', 'name' => 'Blok B', 'activity_counts' => ['Penyiangan' => 3],
                    'obstacle_count' => 0, 'obstacle_summary' => null, 'incidents' => []],
            ]],
            ['id' => 'jonggol', 'name' => 'Kebun Jonggol', 'activity_count' => 0, 'blocks' => []],
        ]]);
    } else {
        $tasks = [];
        foreach ([1, 1, 3] as $index => $days) {
            $tasks[] = ['farm_id' => 'cianjur', 'farm_name' => 'Kebun Cianjur',
                'block_id' => $days === 1 ? 'a' : 'b', 'block_name' => $days === 1 ? 'Blok A' : 'Blok B',
                'name' => $days === 1 ? 'Pemupukan susulan' : 'Pemeriksaan hama dan kondisi tanaman',
                'scheduled_date' => now()->addDays($days)->toDateString(),
                'due_date' => now()->addDays($days + 2)->toDateString(), 'days_before' => $days];
        }
        $template = new App\Notifications\TaskReminderNotification(['tasks' => $tasks]);
    }
    $rich = $template->toInApp();
    (new App\Models\Notification)->forceFill([
        'user_id' => $user->id, 'team_id' => $role === 'owner' ? null : $team->id,
        'team_ids' => $role === 'owner' ? json_encode([$team->id, $secondTeam->id], JSON_THROW_ON_ERROR) : null,
        'type' => $role === 'petugas' ? 'task_reminder' : 'weekly_report',
        'title' => $rich['title'], 'message' => $rich['message'], 'content' => $rich['content'],
        'created_at' => now()->addSeconds(2),
    ])->save();
    if ($role === 'admin') {
        $deviation = (new App\Notifications\SopDeviationNotification(['tasks' => [[
            'farm_id' => 'cianjur', 'farm_name' => 'Kebun Cianjur', 'block_id' => 'a', 'block_name' => 'Blok A',
            'name' => 'Pemupukan dasar', 'scheduled_date' => now()->subDays(4)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(), 'days_before' => -4,
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
