<?php

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\User;
use App\Services\FarmBlockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class DemoFarmBlockSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Blok demo hanya dapat diisi pada environment local atau testing.');
        }
        $actor = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['admin']['email'])->firstOrFail();
        $team = $actor->teams()->where('slug', 'farm-ic-demo')->firstOrFail();
        Gate::forUser($actor)->authorize('viewAny', [Farm::class, $team]);
        Gate::forUser($actor)->authorize('viewAny', [FarmBlock::class, $team]);
        DB::transaction(function () use ($actor, $team): void {
            $farm = Farm::visibleTo($actor, $team)->where('name', 'Kebun Cileuksa Demo')->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('create', [FarmBlock::class, $farm]);
            foreach ([['B01', 'Blok Utara', '6.0000'], ['B02', 'Blok Selatan', '6.5000']] as [$code, $name, $area]) {
                if (! FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->where('code', $code)->exists()) {
                    app(FarmBlockService::class)->create($farm, $actor, $team, ['code' => $code, 'name' => $name,
                        'area_ha' => $area, 'description' => 'Blok permanen lahan percontohan Cileuksa.']);
                }
            }
        });
    }
}
