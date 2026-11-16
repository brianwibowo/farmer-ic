<?php

namespace Database\Seeders;

use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\User;
use App\Services\FarmBlockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DemoFarmBlockSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['admin']['email'])->firstOrFail();
        $team = $actor->teams()->where('slug', 'farm-ic-demo')->firstOrFail();
        Gate::forUser($actor)->authorize('viewAny', [Farm::class, $team]);
        Gate::forUser($actor)->authorize('viewAny', [FarmBlock::class, $team]);
        DB::transaction(function () use ($actor, $team): void {
            $farm = Farm::visibleTo($actor, $team)->whereIn('name', DemoFarmSeeder::FARM_NAMES)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('create', [FarmBlock::class, $farm]);
            foreach ([
                ['B01', 'Blok Utara', '10.0000', '2026-05-17 07:00:00', '2026-05-19 16:00:00'],
                ['B02', 'Blok Selatan', '2.5000', '2026-09-20 07:00:00', '2026-09-22 16:00:00'],
            ] as [$code, $name, $area, $clearingStarted, $clearingCompleted]) {
                if (! FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->where('code', $code)->exists()) {
                    $created = app(FarmBlockService::class)->create($farm, $actor, $team, ['code' => $code, 'name' => $name,
                        'area_ha' => $area, 'description' => 'Blok permanen lahan percontohan Cileuksa.']);
                    $created->forceFill(['land_clearing_started_at' => $clearingStarted, 'land_clearing_completed_at' => $clearingCompleted])->save();
                }
            }

            // Fresh demo data follows the block/batch flow. Existing legacy
            // cycles are retained; rerunning a seeder must not rewrite history.
            $cycles = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id);
            if ((clone $cycles)->whereNull('block_id')->exists()) {
                $this->command->warn('Siklus tanam lama dipertahankan. Rekonsiliasi diperlukan sebelum menambahkan batch seed baru.');

                return;
            }
            if ((clone $cycles)->exists()) {
                return;
            }

            $variety = CropVariety::visibleTo($actor, $team)->where('name', 'Bisi 18')->firstOrFail();
            $b01 = FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->where('code', 'B01')->firstOrFail();
            $b02 = FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->where('code', 'B02')->firstOrFail();

            // A completed previous cycle keeps a real Petugas harvest report
            // available for admin harvest transactions.
            Gate::forUser($actor)->authorize('create', [PlantingCycle::class, $b01]);
            $previous = app(FarmBlockService::class)->createBatch($b01, $actor, $team, [
                'reserved_area_ha' => '4.2500', 'subblock_label' => 'A',
            ]);
            Gate::forUser($actor)->authorize('update', $previous);
            $previous->forceFill([
                'status' => 'active', 'variety_id' => $variety->id, 'planted_at' => '2026-06-01',
                'planted_area_ha' => '4.2500', 'estimated_harvest_at' => '2026-08-30', 'estimated_weight_ton' => '21.000',
            ])->save();
            // DemoActivitySeeder records the evidence before closing harvest.

            // Current active cycle for the Petugas workflow.
            Gate::forUser($actor)->authorize('create', [PlantingCycle::class, $b01]);
            $batch = app(FarmBlockService::class)->createBatch($b01, $actor, $team, [
                'reserved_area_ha' => '4.2500', 'subblock_label' => 'B',
            ]);
            Gate::forUser($actor)->authorize('update', $batch);
            $batch->forceFill(['status' => 'active', 'variety_id' => $variety->id, 'planted_at' => '2026-10-01',
                'planted_area_ha' => '4.2500', 'estimated_harvest_at' => '2027-01-15', 'estimated_weight_ton' => '21.000'])->save();

            Gate::forUser($actor)->authorize('create', [PlantingCycle::class, $b02]);
            app(FarmBlockService::class)->createBatch($b02, $actor, $team, [
                'reserved_area_ha' => '1.2500', 'subblock_label' => 'A', 'planned_planting_at' => '2026-11-20',
            ]);
        });
    }
}
