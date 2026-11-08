<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Farm;
use App\Models\OperationalTarget;
use App\Models\PlantingCycle;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoOperationsSeeder extends Seeder
{
    public function run(): void
    {
        $petugas = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['petugas']['email'])->firstOrFail();
        $team = $petugas->teams()->where('slug', 'farm-ic-demo')->firstOrFail();
        \Illuminate\Support\Facades\Gate::forUser($petugas)->authorize('viewAny', [Farm::class, $team]);
        $farm = Farm::visibleTo($petugas, $team)->where('team_id', $team->id)
            ->whereIn('name', DemoFarmSeeder::FARM_NAMES)->firstOrFail();
        $cycle = PlantingCycle::visibleTo($petugas, $team)->where('farm_id', $farm->id)->where('status', 'active')->firstOrFail();

        OperationalTarget::query()->firstOrCreate([
            'farm_id' => $farm->id,
            'planting_cycle_id' => $cycle->id,
            'task_name' => 'Penyelesaian pemetaan blok',
        ], [
            'target_unit' => 'blok',
            'target_value' => 4,
            'completed_value' => 2,
            'target_date' => '2026-11-05',
            'status' => 'in_progress',
            'created_by' => $petugas->id,
        ]);
        OperationalTarget::query()->firstOrCreate([
            'farm_id' => $farm->id,
            'planting_cycle_id' => $cycle->id,
            'task_name' => 'Pemeriksaan pertumbuhan mingguan',
        ], [
            'target_unit' => 'kunjungan',
            'target_value' => 12,
            'completed_value' => 4,
            'target_date' => '2026-12-24',
            'status' => 'in_progress',
            'created_by' => $petugas->id,
        ]);

        AuditLog::query()->firstOrCreate([
            'user_id' => $petugas->id,
            'table_name' => 'daily_activity_logs',
            'record_id' => $farm->id,
            'action' => 'demo_seeded',
        ], [
            'new_values' => ['source' => 'DatabaseSeeder', 'farm' => $farm->name],
            'created_at' => now(),
        ]);
    }
}
