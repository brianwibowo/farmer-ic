<?php

namespace App\Services\Activity;

use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\User;
use App\Support\ActivityInputLimits;
use App\Support\TaskActivityFields;
use App\Support\VoluntaryActivityFields;
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
        private readonly TaskReportingWindow $window,
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
                if (! in_array($cycle->status, ['planned', 'active'], true)) {
                    throw ValidationException::withMessages(['task' => 'Subblok sudah ditutup.']);
                }
                $this->window->ensureAllowed($task, $cycle, $data['work_date']);
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
                        throw ValidationException::withMessages([($data['schema'] ?? null) === 'taxonomy' ? 'details.area' : 'details.area_ha' => 'Luas dikerjakan tidak boleh melebihi luas tanam subblok.']);
                    }
                    if ((isset($details['area_ha']) && $details['area_ha'] <= 0) || (isset($details['weight_ton']) && $details['weight_ton'] <= 0)) {
                        throw ValidationException::withMessages(['details' => 'Luas minimal 1 m² dan berat panen minimal 1 kg.']);
                    }
                }
                if (($details['quantity_kg'] ?? 0) > ActivityInputLimits::NORMALIZED_QUANTITY_KG) {
                    throw ValidationException::withMessages(['details.quantity' => 'Jumlah terlalu besar setelah konversi satuan.']);
                }
                $log = $this->headers->resolve($farm, $actor, $data['work_date']);
                $date = $this->headers->activityTimestamp($data['work_date']);
                $activity = new FarmActivity([
                    'farm_id' => $farm->id, 'planting_cycle_id' => $cycle->id, 'activity_type_id' => $type->id,
                    'performed_by' => $actor->id, 'activity_date' => $date, 'daily_activity_log_id' => $log->id,
                    'position' => $this->headers->nextPosition($log, $actor), 'worker_count' => (int) $data['worker_count'],
                    'description' => $data['notes'] ?? null, 'obstacle_status' => 'none',
                ]);
                $blockId = $cycle->getAttribute('block_id');
                $block = $blockId === null ? null : \App\Models\FarmBlock::visibleTo($actor, $farm->team)->where('farm_id', $farm->id)->whereKey($blockId)->firstOrFail();
                $activity->forceFill(['block_id' => $block?->id, 'planting_cycle_task_id' => $task->id, 'task_details' => json_encode($details, JSON_THROW_ON_ERROR)])->save();
                match ($type->detail_key) {
                    'harvest' => $activity->harvestDetail()->create(['harvested_area_ha' => $details['area_ha'], 'gross_weight_ton' => $details['weight_ton']]),
                    default => null,
                };
                foreach ($data['photos'] as $photo) {
                    $path = $photo->store('farms/'.$activity->farm_id.'/'.$activity->id, 'activity');
                    if ($path === false) {
                        throw new \RuntimeException('Foto tidak berhasil disimpan.');
                    }
                    $paths[] = $path;
                    ActivityPhoto::create(['activity_id' => $activity->id, 'image_url' => $path]);
                }

                app(PlantingCycleStageService::class)->record($actor, $farm->team, $activity, $type);

                if ($task->status === 'pending') {
                    $task->forceFill(['status' => 'in_progress'])->save();
                }

                // Recording evidence does not approve or complete the task.
                return $activity;
            });
        } catch (Throwable $exception) {
            Storage::disk('activity')->delete($paths);
            throw $exception;
        }
    }
}
