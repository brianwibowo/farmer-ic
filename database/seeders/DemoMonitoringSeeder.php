<?php

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\FarmRisk;
use App\Models\PlantingCycle;
use App\Models\PlantObservation;
use App\Models\User;
use App\Models\WeatherRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoMonitoringSeeder extends Seeder
{
    public function run(): void
    {
        $farm = Farm::query()->where('name', 'Kebun Cileuksa Demo')->firstOrFail();
        $cycle = PlantingCycle::query()->where('farm_id', $farm->id)->where('status', 'active')->firstOrFail();
        $petugas = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['petugas']['email'])->firstOrFail();
        $observation = PlantObservation::query()->where('planting_cycle_id', $cycle->id)->first();

        if ($observation) {
            FarmRisk::query()->firstOrCreate([
                'planting_cycle_id' => $cycle->id,
                'risk_description' => 'Potensi serangan ulat pada sisi timur blok.',
            ], [
                'observation_id' => $observation->id,
                'severity' => 'medium',
                'recommendation' => 'Lakukan scouting dua kali seminggu dan siapkan pengendalian hayati.',
                'status' => 'monitoring',
                'detected_at' => now(),
            ]);
        }

        $weather = WeatherRecord::query()->firstOrNew([
            'farm_id' => $farm->id,
            'recorded_at' => '2026-10-28 07:00:00+07',
        ]);
        $weather->forceFill([
            'condition' => 'Cerah berawan',
            'temperature_c' => 28.40,
            'humidity_percentage' => 72.00,
            'data_source' => 'Stasiun cuaca demo',
        ])->save();

        if ($observation && ! DB::table('ai_insights')->where('planting_cycle_id', $cycle->id)->exists()) {
            DB::table('ai_insights')->insert([
                'id' => (string) Str::uuid(),
                'planting_cycle_id' => $cycle->id,
                'observation_id' => $observation->id,
                'summary' => 'Pertumbuhan jagung berada pada kondisi sehat dengan risiko hama ringan.',
                'next_step' => 'Ulangi pengamatan tinggi tanaman dan cek daun bagian bawah.',
                'confidence_score' => 86.00,
                'model_name' => 'farm-ic-demo-analysis',
                'generated_at' => now(),
            ]);
        }
    }
}
