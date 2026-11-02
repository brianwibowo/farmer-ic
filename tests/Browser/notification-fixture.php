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
