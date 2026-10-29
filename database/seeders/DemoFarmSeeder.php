<?php

namespace Database\Seeders;

use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\PlantingCycle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoFarmSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['owner']['email'])->firstOrFail();
        $petugas = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['petugas']['email'])->firstOrFail();
        $team = Team::query()->where('slug', 'farm-ic-demo')->firstOrFail();

        $farm = Farm::query()->firstOrNew(['name' => 'Kebun Cileuksa Demo']);
        $farm->forceFill([
            'team_id' => $team->id,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'address' => 'Kampung Cileuksa, lahan percontohan Farm IC',
            'city' => 'Bogor',
            'district' => 'Sukamakmur',
            'province' => 'Jawa Barat',
            'total_area_ha' => 12.5000,
            'land_tenure' => 'owned',
            'latitude' => -6.5965000,
            'longitude' => 106.9502000,
        ])->save();
        $farm->refresh();

        $petugas->forceFill(['assigned_farm_id' => $farm->id])->save();

        $jagung = Commodity::query()->firstOrCreate(['name' => 'Jagung'], [
            'description' => 'Komoditas utama lahan demo untuk simulasi logbook dan analitik.',
        ]);
        $variety = CropVariety::query()->firstOrCreate([
            'commodity_id' => $jagung->id,
            'name' => 'Bisi 18',
        ], [
            'description' => 'Varietas jagung hibrida untuk siklus tanam percontohan.',
        ]);

        PlantingCycle::query()->firstOrCreate([
            'farm_id' => $farm->id,
            'variety_id' => $variety->id,
            'planted_at' => '2026-10-01',
        ], [
            'estimated_harvest_at' => '2027-01-15',
            'planted_area_ha' => 8.5000,
            'estimated_weight_ton' => 42.000,
            'status' => 'active',
            'created_at' => now(),
        ]);
    }
}
