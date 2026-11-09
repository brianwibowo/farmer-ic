<?php

namespace App\Services\Activity;

use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\User;
use App\Support\TaskActivityFields;
use App\Support\VoluntaryActivityFields;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TaskActivityService
{
    public function __construct(
        private readonly DailyActivityHeaderService $headers,
        private readonly ActivitySequenceService $sequence,
        private readonly HarvestGuard $harvest,
    ) {}

    /** @param array<string, mixed> $data Validated task-specific fields and files. */
    public function create(User $actor, PlantingCycleTask $task, array $data): FarmActivity
    {
        $paths = [];
        try {
            return DB::transaction(function () use ($actor, $task, $data, &$paths): FarmActivity {
                $farm = $task->plantingCycle->farm;
                $farm = Farm::visibleTo($actor, $farm->team)->whereKey($farm->id)->lockForUpdate()->firstOrFail();
                $actor = User::query()->whereKey($actor->id)->sharedLock()->firstOrFail();
                $task = PlantingCycleTask::visibleTo($actor, $farm->team)->whereKey($task->id)->lockForUpdate()->firstOrFail();
                if (! in_array($task->status, ['pending', 'in_progress'], true)) {
                    throw ValidationException::withMessages(['task' => 'Tugas sudah selesai atau ditutup. Laporan baru tidak dapat ditambahkan.']);
                }
                $code = TaskActivityFields::activityCode($task);
                if ($code === null) {
                    throw ValidationException::withMessages(['task' => 'Jenis tugas ini belum mendukung pencatatan.']);
                }
                $cycleQuery = PlantingCycle::visibleTo($actor, $farm->team)->whereKey($task->planting_cycle_id)
                    ->where('farm_id', $farm->id);
                $cycle = ($code === 'harvest' ? $cycleQuery->lockForUpdate() : $cycleQuery->sharedLock())->firstOrFail();
                if ($cycle->planted_at === null || $data['work_date'] < $cycle->planted_at->toDateString()) {
                    throw ValidationException::withMessages(['work_date' => 'Laporan tugas lanjutan harus pada atau setelah tanggal tanam aktual.']);
                }
                $details = ($data['schema'] ?? null) === 'taxonomy' ? VoluntaryActivityFields::normalized($data['details']) : $data['details'];
                $this->sequence->ensureAllowed($actor, $cycle, $code, 'task');
                $type = ActivityType::visibleTo($actor, $farm->team)->where('code', $code)->first();
                if ($type === null) {
                    throw ValidationException::withMessages(['task' => 'Referensi jenis aktivitas belum tersedia. Hubungi admin.']);
                }
                if ($code === 'harvest') {
                    $taxonomy = ($data['schema'] ?? null) === 'taxonomy';
                    $this->harvest->ensureValid(
                        $actor,
                        $farm->team,
                        $cycle,
                        $details,
                        $taxonomy ? 'details.area' : 'details.area_ha',
                        $taxonomy ? 'details.weight' : 'details.weight_ton',
                    );
                } else {
                    if (isset($details['area_ha']) && (float) $details['area_ha'] > (float) $cycle->planted_area_ha) {
                        throw ValidationException::withMessages(['details.area_ha' => 'Luas dikerjakan tidak boleh melebihi luas tanam subblok.']);
                    }
                    if ((isset($details['area_ha']) && $details['area_ha'] <= 0) || (isset($details['weight_ton']) && $details['weight_ton'] <= 0)) {
                        throw ValidationException::withMessages(['details' => 'Luas minimal 1 m² dan berat panen minimal 1 kg.']);
                    }
                }
                if (($details['quantity_kg'] ?? 0) > 999999999.999) {
                    throw ValidationException::withMessages(['details.quantity' => 'Jumlah terlalu besar setelah konversi satuan.']);
                }
                $log = $this->headers->resolve($farm, $actor, $data);
                // Timestamp inherits the shared day start, not per-activity hours.
                $date = CarbonImmutable::parse($data['work_date'].' '.$log->start_time, 'Asia/Jakarta');
                $activity = new FarmActivity([
                    'farm_id' => $farm->id, 'planting_cycle_id' => $cycle->id, 'activity_type_id' => $type->id,
                    'performed_by' => $actor->id, 'activity_date' => $date, 'daily_activity_log_id' => $log->id,
                    'position' => $this->headers->nextPosition($log, $actor), 'worker_count' => (int) $data['worker_count'],
                    'description' => $data['notes'] ?? null, 'obstacle_status' => 'none',
                ]);
                $round = \App\Models\PlantingRound::visibleTo($actor, $farm->team)->whereKey($cycle->getAttribute('planting_round_id'))->first();
                $block = $round ? \App\Models\FarmBlock::visibleTo($actor, $farm->team)->where('farm_id', $farm->id)->whereKey($round->block_id)->firstOrFail() : null;
                $activity->forceFill(['block_id' => $block?->id, 'planting_cycle_task_id' => $task->id, 'task_details' => json_encode($details, JSON_THROW_ON_ERROR)])->save();
                match ($type->detail_key) {
                    'irrigation' => $activity->irrigationDetail()->create(['water_source' => $details['water_source'], 'irrigation_method' => $details['method'], 'machine_type' => $details['pump'] ?? null, 'duration_hours' => $details['duration_hours']]),
                    'fertilization' => $activity->fertilizationDetail()->create([
                        'fertilization_stage' => $code === 'base_fertilization' ? 'dasar' : 'susulan',
                        'fertilizer_type' => $details['fertilizer'] ?? $details['fertilizer_type'] ?? null,
                        'brand_name' => $details['brand'] ?? $details['brand_name'] ?? null,
                        'quantity_kg' => $details['quantity_kg'],
                        'quantity_unit' => $details['quantity_unit'] ?? null,
                        'sack_size_kg' => $details['bag_size'] ?? $details['sack_size_kg'] ?? null,
                        'application_method' => $details['method'] ?? $details['application_method'] ?? null,
                        'area_ha' => $details['area_ha'],
                    ]),
                    'weeding' => $activity->weedingDetail()->create(['area_ha' => $details['area_ha'], 'method' => $details['method'], 'depth_cm' => $details['depth_cm'] ?? null]),
                    'other' => $activity->otherDetail()->create(['description' => $details['description'] ?? $data['notes'] ?? null, 'area_ha' => $details['area_ha'] ?? null]),
                    'pesticide' => $activity->pesticideDetail()->create(['pesticide_category' => $details['control_type'], 'purpose' => $details['target'], 'brand_name' => $details['product'], 'dosage_per_tank' => $details['dosage'], 'tank_size_liters' => $details['water_liters'], 'tank_count' => $details['tank_count']]),
                    'harvest' => $activity->harvestDetail()->create(['harvested_area_ha' => $details['area_ha'], 'gross_weight_ton' => $details['weight_ton'], 'moisture_percentage' => $details['moisture'] ?? null]),
                    'growth_check' => $activity->observation()->create(['planting_cycle_id' => $cycle->id, 'observed_by' => $actor->id, 'observed_at' => $date, 'growth_phase' => $details['phase'], 'average_height_cm' => $details['height_cm'], 'pest_indication' => $details['pest'], 'disease_indication' => $details['disease']]),
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

                if ($task->status === 'pending') {
                    $task->forceFill(['status' => 'in_progress'])->save();
                }

                // Recording evidence does not approve or complete the task.
                return $activity;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
    }
}
