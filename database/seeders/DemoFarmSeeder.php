<?php

namespace Database\Seeders;

use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
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
        \Illuminate\Support\Facades\Gate::forUser($owner)->authorize('viewAny', [\App\Models\AdministrativeRegion::class, $team]);
        $hasRegion = \App\Models\AdministrativeRegion::visibleTo($owner, $team)->whereKey('32.01.09')->exists();
        if ($hasRegion && (! $farm->exists || $farm->getAttribute('district_code') === '32.01.09')) {
            $farm->forceFill(['district_code' => '32.01.09']);
        }
        $farm->forceFill([
            'team_id' => $team->id,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'address' => 'Kampung Cileuksa, lahan percontohan Farm IC',
            'city' => $farm->getAttribute('district_code') === '32.01.09' ? 'Kabupaten Bogor' : 'Bogor',
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
        CropVariety::query()->firstOrCreate([
            'commodity_id' => $jagung->id,
            'name' => 'Bisi 18',
        ], [
            'description' => 'Varietas jagung hibrida untuk siklus tanam percontohan.',
        ]);

        $this->call(DemoFarmBlockSeeder::class);
    }
}
