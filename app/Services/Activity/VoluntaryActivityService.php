<?php

namespace App\Services\Activity;

use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\User;
use App\Support\VoluntaryActivityFields;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class VoluntaryActivityService
{
    public function __construct(
        private readonly DailyActivityHeaderService $headers,
        private readonly ActivitySequenceService $sequence,
    ) {}

    /** @param array<string, mixed> $data */
    public function record(User $actor, Farm $farm, ActivityType $type, ?PlantingCycle $cycle, array $data, ?FarmBlock $block = null): FarmActivity
    {
        $paths = [];
        try {
            return DB::transaction(function () use ($actor, $farm, $type, $cycle, $data, $block, &$paths): FarmActivity {
                $team = $farm->team;
                $farm = Farm::visibleTo($actor, $team)->whereKey($farm->id)->lockForUpdate()->firstOrFail();
                $type = ActivityType::visibleTo($actor, $team)->whereKey($type->id)->where('is_active', true)->where('is_selectable', true)->firstOrFail();
                if (VoluntaryActivityCategoryService::requiresTask($type->code)) {
                    throw ValidationException::withMessages(['type' => 'Panen hanya dapat dicatat melalui tugas SOP.']);
                }
                if ($block !== null) {
                    $block = FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->where('is_active', true)->whereKey($block->id)->lockForUpdate()->firstOrFail();
                }
                if ($cycle !== null) {
                    $cycle = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
                    if (! in_array($cycle->status, ['planned', 'active'], true)) {
                        throw ValidationException::withMessages(['cycle_id' => 'Subblok sudah ditutup.']);
                    }
                    $this->sequence->ensureAllowed($actor, $cycle, $type->code);
                    $blockId = $cycle->getAttribute('block_id');
                    $block = $blockId === null ? null : FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->whereKey($blockId)->firstOrFail();
                    if (in_array($type->code, ['irrigation', 'weeding', 'follow_up_fertilization', 'growth_check', 'pest_control'], true)
                        && ($cycle->planted_at === null || $data['work_date'] < $cycle->planted_at->toDateString())) {
                        throw ValidationException::withMessages(['work_date' => 'Perawatan dan panen dicatat pada atau setelah tanggal acuan tanam.']);
                    }
                }
                $d = VoluntaryActivityFields::normalized($data['details']);
                if (isset($d['area_ha']) && ($d['area_ha'] <= 0 || ($cycle && $d['area_ha'] > (float) $cycle->getAttribute('reserved_area_ha')) || (! $cycle && $block && $d['area_ha'] > (float) $block->area_ha))) {
                    throw ValidationException::withMessages(['details.area' => 'Luas harus minimal 1 m² dan tidak melebihi luas lokasi yang dipilih.']);
                }
                if (($d['quantity_kg'] ?? 0) > 999999999.999) {
                    throw ValidationException::withMessages(['details.quantity' => 'Jumlah terlalu besar setelah konversi satuan.']);
                }
                $log = $this->headers->resolve($farm, $actor, $data);
                $date = CarbonImmutable::parse($data['work_date'].' '.$log->start_time, 'Asia/Jakarta');
                $activity = new FarmActivity([
                    'farm_id' => $farm->id, 'planting_cycle_id' => $cycle?->id, 'activity_type_id' => $type->id,
                    'performed_by' => $actor->id, 'activity_date' => $date, 'daily_activity_log_id' => $log->id,
                    'position' => $this->headers->nextPosition($log, $actor), 'worker_count' => (int) $data['worker_count'],
                    'description' => $data['notes'] ?? null, 'obstacle_status' => 'none',
                ]);
                $activity->forceFill(['block_id' => $block?->id, 'task_details' => json_encode($d, JSON_THROW_ON_ERROR)])->save();
                match ($type->detail_key) {
                    'irrigation' => $activity->irrigationDetail()->create(['water_source' => $d['water_source'], 'irrigation_method' => $d['method'], 'machine_type' => $d['pump'] ?? null, 'duration_hours' => $d['duration_hours']]),
                    'fertilization' => $activity->fertilizationDetail()->create([
                        'fertilization_stage' => $type->code === 'base_fertilization' ? 'dasar' : 'susulan',
                        'fertilizer_type' => $d['fertilizer'] ?? null,
                        'brand_name' => $d['brand'] ?? null,
                        'quantity_kg' => $d['quantity_kg'] ?? null,
                        'quantity_unit' => $d['quantity_unit'] ?? null,
                        'sack_size_kg' => $d['bag_size'] ?? null,
                        'application_method' => $d['method'] ?? null,
                        'area_ha' => $d['area_ha'] ?? null,
                    ]),
                    'weeding' => $activity->weedingDetail()->create(['area_ha' => $d['area_ha'], 'method' => $d['method'], 'depth_cm' => $d['depth_cm'] ?? null]),
                    'pesticide' => $activity->pesticideDetail()->create(['pesticide_category' => $d['control_type'], 'purpose' => $d['target'], 'brand_name' => $d['product'], 'dosage_per_tank' => $d['dosage'].' ml/L', 'tank_size_liters' => $d['water_liters'], 'tank_count' => $d['tank_count']]),
                    'growth_check' => $activity->observation()->create(['planting_cycle_id' => $cycle->id, 'observed_by' => $actor->id, 'observed_at' => $date, 'growth_phase' => $d['phase'], 'average_height_cm' => $d['height_cm'], 'pest_indication' => $d['pest'], 'disease_indication' => $d['disease']]),
                    'incident' => $activity->incidentDetail()->create(['incident_category' => $d['incident_category'], 'severity' => $d['severity'], 'affected_area_ha' => $d['area_ha']]),
                    'land_cleaning' => $activity->landCleaningDetail()->create(['area_ha' => $d['area_ha'], 'method' => $d['method'], 'product_name' => $d['product'] ?? null, 'dosage_per_tank' => $d['dosage'] ?? null, 'water_liters' => $d['water_liters'] ?? null, 'tank_count' => $d['tank_count'] ?? null]),
                    'loosening' => $activity->looseningDetail()->create(['area_ha' => $d['area_ha'], 'method' => $d['method'], 'depth_cm' => $d['depth_cm'] ?? null]),
                    'bed_preparation' => $activity->bedPreparationDetail()->create(['bed_count' => $d['bed_count'], 'length_m' => $d['length_m'], 'width_m' => $d['width_m'], 'height_cm' => $d['height_cm'] ?? null, 'bed_spacing_cm' => $d['spacing_cm'] ?? null, 'area_ha' => $d['area_ha'] ?? null]),
                    'liming' => $activity->limingDetail()->create(['area_ha' => $d['area_ha'], 'material_type' => $d['material'], 'brand_name' => $d['brand'] ?? null, 'quantity_value' => $d['quantity'], 'quantity_unit' => $d['quantity_unit'], 'sack_size_kg' => $d['bag_size'] ?? null, 'application_method' => $d['method']]),
                    'other' => $activity->otherDetail()->create(['description' => $d['description'], 'area_ha' => $d['area_ha'] ?? null]),
                    default => null,
                };
                foreach ($data['photos'] as $photo) {
                    $path = $photo->store('task-activities/'.$activity->id, 'local');
                    if ($path === false) {
                        throw new \RuntimeException('Foto tidak berhasil disimpan.');
                    }
                    $paths[] = $path;
                    ActivityPhoto::create(['activity_id' => $activity->id, 'image_url' => $path]);
                }

                app(PlantingCycleStageService::class)->record($actor, $team, $activity, $type);

                return $activity;
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }
    }
}
