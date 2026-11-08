<?php

namespace Database\Seeders;

use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Gate;

class DemoFarmSeeder extends Seeder
{
    public const FARM_NAME = 'Kebun Cileuksa';

    /** Legacy name is only a lookup alias, never a new display value. */
    public const FARM_NAMES = [self::FARM_NAME, 'Kebun Cileuksa Demo'];

    public function run(): void
    {
        $owner = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['owner']['email'])->firstOrFail();
        $petugas = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['petugas']['email'])->firstOrFail();
        $team = Team::query()->where('slug', 'farm-ic-demo')->firstOrFail();

        Gate::forUser($owner)->authorize('viewAny', [Farm::class, $team]);
        $farm = Farm::visibleTo($owner, $team)->where('team_id', $team->id)
            ->whereIn('name', self::FARM_NAMES)->first() ?? new Farm;
        $isNewFarm = ! $farm->exists;
        $admin = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['admin']['email'])->firstOrFail();
        Gate::forUser($admin)->authorize($isNewFarm ? 'create' : 'update', $isNewFarm ? [Farm::class, $team] : $farm);
        \Illuminate\Support\Facades\Gate::forUser($owner)->authorize('viewAny', [\App\Models\AdministrativeRegion::class, $team]);
        $hasRegion = \App\Models\AdministrativeRegion::visibleTo($owner, $team)->whereKey('32.01.09')->exists();
        if ($hasRegion && (! $farm->exists || $farm->getAttribute('district_code') === '32.01.09')) {
            $farm->forceFill(['district_code' => '32.01.09']);
        }
        $farm->forceFill([
            'name' => self::FARM_NAME,
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
            'description' => 'Komoditas utama lahan untuk simulasi logbook dan analitik.',
        ]);
        if ($isNewFarm) {
            $admin = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['admin']['email'])->firstOrFail();
            \Illuminate\Support\Facades\Gate::forUser($admin)->authorize('update', $farm);
            $farm->forceFill(['commodity_id' => $jagung->id, 'target_volume_ton' => '80.000'])->save();
        }
        CropVariety::query()->firstOrCreate([
            'commodity_id' => $jagung->id,
            'name' => 'Bisi 18',
        ], [
            'description' => 'Varietas jagung hibrida untuk siklus tanam percontohan.',
        ]);

        $this->call(DemoFarmBlockSeeder::class);
    }
}
