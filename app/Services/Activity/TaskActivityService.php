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
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TaskActivityService
{
    public function __construct(private readonly DailyActivityHeaderService $headers) {}

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
                    throw ValidationException::withMessages(['task' => 'Tugas sedang direview atau sudah ditutup. Laporan baru tidak dapat ditambahkan.']);
                }
                $cycle = PlantingCycle::visibleTo($actor, $farm->team)->whereKey($task->planting_cycle_id)
                    ->where('farm_id', $farm->id)->sharedLock()->firstOrFail();
                if ($cycle->planted_at === null || $data['work_date'] < $cycle->planted_at->toDateString()) {
                    throw ValidationException::withMessages(['work_date' => 'Laporan tugas lanjutan harus pada atau setelah tanggal tanam aktual.']);
                }
                $details = $data['details'];
                $code = TaskActivityFields::activityCode($task);
                if ($code === null) {
                    throw ValidationException::withMessages(['task' => 'Jenis tugas ini belum mendukung pencatatan.']);
                }
                $type = ActivityType::visibleTo($actor, $farm->team)->where('code', $code)->first();
                if ($type === null) {
                    throw ValidationException::withMessages(['task' => 'Referensi jenis aktivitas belum tersedia. Hubungi admin.']);
                }
                if (isset($details['area_ha']) && (float) $details['area_ha'] > (float) $cycle->planted_area_ha) {
                    throw ValidationException::withMessages(['details.area_ha' => 'Luas dikerjakan tidak boleh melebihi luas tanam subblok.']);
                }
                $log = $this->headers->resolve($farm, $actor, $data);
                // Timestamp inherits the shared day start, not per-activity hours.
                $date = CarbonImmutable::parse($data['work_date'].' '.$log->start_time, 'Asia/Jakarta');
                $activity = new FarmActivity([
                    'farm_id' => $farm->id, 'planting_cycle_id' => $cycle->id, 'activity_type_id' => $type->id,
                    'performed_by' => $actor->id, 'activity_date' => $date, 'daily_activity_log_id' => $log->id,
                    'position' => $this->headers->nextPosition($log, $actor), 'description' => $data['notes'] ?? null, 'obstacle_status' => 'none',
                ]);
                $activity->forceFill(['planting_cycle_task_id' => $task->id, 'task_details' => json_encode($details, JSON_THROW_ON_ERROR)])->save();
                match ($type->detail_key) {
                    'irrigation' => $activity->irrigationDetail()->create(['water_source' => $details['water_source'], 'irrigation_method' => $details['method'], 'machine_type' => $details['pump'] ?? null, 'duration_hours' => $details['duration_hours']]),
                    'fertilization' => $activity->fertilizationDetail()->create(['fertilization_stage' => 'susulan', 'fertilizer_type' => $details['fertilizer'], 'quantity_kg' => $details['quantity_kg'], 'application_method' => $details['method']]),
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

                // Recording evidence does not approve or complete the task.
                return $activity;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
    }
}
