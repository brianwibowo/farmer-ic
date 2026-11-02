<?php

namespace Database\Seeders;

use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\PlantingRound;
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
            foreach ([['B01', 'Blok Utara', '10.0000'], ['B02', 'Blok Selatan', '2.5000']] as [$code, $name, $area]) {
                if (! FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->where('code', $code)->exists()) {
                    $created = app(FarmBlockService::class)->create($farm, $actor, $team, ['code' => $code, 'name' => $name,
                        'area_ha' => $area, 'description' => 'Blok permanen lahan percontohan Cileuksa.']);
                    $created->forceFill(['land_clearing_started_at' => '2026-09-20 07:00:00', 'land_clearing_completed_at' => '2026-09-22 16:00:00'])->save();
                }
            }

            // Fresh demo data follows the block/batch flow. Existing legacy
            // cycles are retained; rerunning a seeder must not rewrite history.
            $cycles = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id);
            if ((clone $cycles)->whereNull('planting_round_id')->exists()) {
                $this->command->warn('Siklus demo lama dipertahankan. Rekonsiliasi diperlukan sebelum menambahkan batch seed baru.');

                return;
            }
            if ((clone $cycles)->exists()) {
                return;
            }

            foreach ([['B01', '4.2500'], ['B02', '1.2500']] as [$code, $area]) {
                $block = FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->where('code', $code)->firstOrFail();
                Gate::forUser($actor)->authorize('create', [PlantingCycle::class, $block]);
                Gate::forUser($actor)->authorize('create', [PlantingRound::class, $block]);
                $batch = app(FarmBlockService::class)->createBatch($block, $actor, $team, ['reserved_area_ha' => $area, 'subblock_label' => 'A']);
                if ($code !== 'B01') {
                    continue;
                }
                Gate::forUser($actor)->authorize('update', $batch);
                $variety = CropVariety::visibleTo($actor, $team)->where('name', 'Bisi 18')->firstOrFail();
                $batch->forceFill(['status' => 'active', 'variety_id' => $variety->id, 'planted_at' => '2026-10-01',
                    'planted_area_ha' => $area, 'estimated_harvest_at' => '2027-01-15', 'estimated_weight_ton' => '21.000'])->save();
                $dates = [
                    'land_clearing' => ['completed', '2026-09-20 07:00:00', '2026-09-22 16:00:00'],
                    'soil_preparation' => ['completed', '2026-09-23 07:00:00', '2026-09-26 16:00:00'],
                    'basic_fertilizer' => ['completed', '2026-09-27 07:00:00', '2026-09-30 16:00:00'],
                    'planting' => ['completed', '2026-10-01 07:00:00', '2026-10-01 16:00:00'],
                    'maintenance' => ['in_progress', '2026-10-02 07:00:00', null],
                    'harvest' => ['upcoming', null, null],
                ];
                foreach (PlantingCycleStage::visibleTo($actor, $team)->where('planting_cycle_id', $batch->id)->orderBy('position')->get() as $stage) {
                    [$status, $started, $completed] = $dates[$stage->code];
                    $stage->forceFill(['status' => $status, 'started_at' => $started, 'completed_at' => $completed])->save();
                }
            }
        });
    }
}
