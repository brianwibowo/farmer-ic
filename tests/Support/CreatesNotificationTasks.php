<?php

namespace Tests\Support;

use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\PlantingCycleTask;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

trait CreatesNotificationTasks
{
    private function notificationTask(Farm $farm, int $days = 0, array $attributes = [], ?FarmBlock $block = null): PlantingCycleTask
    {
        return DB::transaction(function () use ($farm, $days, $attributes, $block): PlantingCycleTask {
            $commodity = Commodity::firstOrCreate(['name' => 'Jagung Notifikasi']);
            $variety = CropVariety::firstOrCreate(['commodity_id' => $commodity->id, 'name' => 'Bisi Notifikasi']);
            if ($block === null) {
                $block = new FarmBlock;
                $block->forceFill(['farm_id' => $farm->id, 'created_by' => $farm->getAttribute('created_by'),
                    'code' => 'B'.strtoupper(str()->random(8)), 'name' => 'Blok Utara', 'area_ha' => 1, 'is_active' => true,
                    'land_clearing_started_at' => now()->subDays(40)])->save();
            }
            $cycle = new PlantingCycle;
            $cycle->forceFill(['farm_id' => $farm->id, 'block_id' => $block->id, 'variety_id' => $variety->id,
                'created_by' => $farm->getAttribute('created_by'), 'reserved_area_ha' => '0.0100',
                'batch_number' => 1 + (int) PlantingCycle::where('farm_id', $farm->id)->max('batch_number'),
                'subblock_label' => str()->random(10), 'planted_at' => now()->subDays(30)->toDateString(),
                'estimated_harvest_at' => now()->addDays(30)->toDateString(), 'planted_area_ha' => '0.0100', 'status' => 'active'])->save();
            foreach (PlantingCycleStage::CODES as $position => $code) {
                $stage = new PlantingCycleStage;
                $stage->forceFill(['planting_cycle_id' => $cycle->id, 'code' => $code, 'position' => $position + 1, 'status' => 'upcoming'])->save();
            }
            $stage = $cycle->stages()->where('code', 'maintenance')->firstOrFail();
            $scheduled = CarbonImmutable::today(config('app.timezone'))->addDays($days);

            return PlantingCycleTask::create([
                'planting_cycle_id' => $cycle->id, 'planting_cycle_stage_id' => $stage->id,
                'activity_category' => 'maintenance', 'activity_subcategory' => 'irrigation',
                'name' => 'Pengairan awal', 'offset_days' => 7, 'tolerance_days' => 2,
                'scheduled_date' => $scheduled->toDateString(), 'due_date' => $scheduled->addDays(2)->toDateString(),
                'is_required' => true, 'status' => 'pending', ...$attributes,
            ]);
        });
    }
}
