<?php

namespace App\Services\Activity;

use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\PlantingDetail;
use App\Models\PlantingRound;
use App\Models\User;
use App\Services\PlantingCycleTaskService;
use App\Support\LandArea;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PlantingActivityService
{
    public function __construct(private readonly PlantingCycleTaskService $tasks, private readonly DailyActivityHeaderService $headers) {}

    /** @return \Illuminate\Database\Eloquent\Collection<int, PlantingCycle> */
    public function cycles(User $actor, Farm $farm): \Illuminate\Database\Eloquent\Collection
    {
        return PlantingCycle::visibleTo($actor, $farm->team)->where('farm_id', $farm->id)->whereNotNull('planting_round_id')
            ->orderBy('batch_number')->get();
    }

    /** @return array<string, mixed> */
    public function context(User $actor, PlantingCycle $cycle): array
    {
        $team = $cycle->farm->team;
        $cycle = PlantingCycle::visibleTo($actor, $team)->whereKey($cycle->id)->firstOrFail();
        $round = PlantingRound::visibleTo($actor, $team)->whereKey($cycle->getAttribute('planting_round_id'))->firstOrFail();
        $block = FarmBlock::visibleTo($actor, $team)->whereKey($round->block_id)->firstOrFail();
        $variety = CropVariety::visibleTo($actor, $team)->whereKey($cycle->variety_id)->first();

        return [
            ...$cycle->only(['id', 'batch_number', 'subblock_label', 'status', 'reserved_area_ha', 'planted_area_ha']),
            'planned_planting_at' => $cycle->planned_planting_at?->toDateString(), 'planted_at' => $cycle->planted_at?->toDateString(),
            'block' => $block->only(['id', 'name']), 'variety' => $variety?->only(['id', 'name']),
        ];
    }

    /** @return array<string, mixed> */
    public function formData(User $actor, PlantingCycle $cycle): array
    {
        $team = $cycle->farm->team;
        $reports = FarmActivity::visibleTo($actor, $team)->where('planting_cycle_id', $cycle->id)
            ->whereHas('activityType', fn ($query) => $query->visibleTo($actor, $team)->where('code', 'planting'))->orderBy('activity_date')->get();

        return [
            'cycle' => $this->context($actor, $cycle),
            'varieties' => CropVariety::visibleTo($actor, $team)->orderBy('name')->get(['id', 'name'])->toArray(),
            'reports' => $reports->map(function (FarmActivity $activity) use ($actor, $team): array {
                $detail = PlantingDetail::visibleTo($actor, $team)->where('activity_id', $activity->id)->first();

                return [
                    'id' => $activity->id, 'date' => $activity->activity_date->setTimezone('Asia/Jakarta')->toDateString(),
                    'notes' => $activity->description, 'detail' => $detail?->only(['seed_name', 'quantity_kg', 'planting_pattern', 'area_ha']),
                    'photos' => ActivityPhoto::visibleTo($actor, $team)->where('activity_id', $activity->id)->get()->map(fn ($photo) => [
                        'id' => $photo->id, 'url' => route('petugas.aktivitas.photos', ['activity' => $activity->id, 'photo' => $photo->id]),
                    ])->all(),
                ];
            })->all(),
        ];
    }

    /** @param array<string, mixed> $data
     * @return array{status: string, created: int, rescheduled: int, version_id: string|null, detail: string|null}
     */
    public function record(User $actor, PlantingCycle $cycle, array $data): array
    {
        $paths = [];
        try {
            return DB::transaction(function () use ($actor, $cycle, $data, &$paths): array {
                $team = $cycle->farm->team;
                $farm = Farm::visibleTo($actor, $team)->whereKey($cycle->farm_id)->lockForUpdate()->firstOrFail();
                $actor = User::query()->whereKey($actor->id)->sharedLock()->firstOrFail();
                $cycle = PlantingCycle::visibleTo($actor, $team)->whereKey($cycle->id)->where('farm_id', $farm->id)->lockForUpdate()->firstOrFail();
                if ($cycle->planted_at !== null || $cycle->status !== 'planned') {
                    throw ValidationException::withMessages(['cycle' => 'Realisasi tanam sudah dicatat atau subblok sudah ditutup. Data awal tidak ditimpa.']);
                }
                $round = PlantingRound::visibleTo($actor, $team)->whereKey($cycle->getAttribute('planting_round_id'))->sharedLock()->firstOrFail();
                $block = FarmBlock::visibleTo($actor, $team)->whereKey($round->block_id)->sharedLock()->firstOrFail();
                if (! $block->is_active || $round->status !== 'active') {
                    throw ValidationException::withMessages(['cycle' => 'Blok dan periode harus aktif untuk mencatat penanaman.']);
                }
                if (LandArea::squareMetres($data['area_ha']) > LandArea::squareMetres($cycle->getAttribute('reserved_area_ha'))) {
                    throw ValidationException::withMessages(['area_ha' => 'Luas realisasi tidak boleh melebihi alokasi subblok.']);
                }
                $variety = CropVariety::visibleTo($actor, $team)->whereKey($data['variety_id'])->first();
                if ($variety === null) {
                    throw ValidationException::withMessages(['variety_id' => 'Varietas tidak tersedia atau tidak dapat diakses.']);
                }
                if ($cycle->sop_template_version_id !== null && $cycle->sopTemplateVersion?->sopTemplate->commodity_id !== $variety->commodity_id) {
                    throw ValidationException::withMessages(['variety_id' => 'Komoditas varietas harus sesuai versi SOP yang sudah terikat pada subblok.']);
                }
                $type = ActivityType::visibleTo($actor, $team)->where('code', 'planting')->first();
                if ($type === null) {
                    throw ValidationException::withMessages(['cycle' => 'Referensi aktivitas Penanaman belum tersedia. Hubungi admin.']);
                }
                $cycle->forceFill(['planted_at' => $data['work_date'], 'variety_id' => $variety->id, 'planted_area_ha' => $data['area_ha'], 'status' => 'active'])->save();
                $log = $this->headers->resolve($farm, $actor, $data);
                $started = CarbonImmutable::parse($data['work_date'].' '.$log->start_time, 'Asia/Jakarta');
                $activity = FarmActivity::create([
                    'farm_id' => $farm->id, 'planting_cycle_id' => $cycle->id, 'activity_type_id' => $type->id,
                    'performed_by' => $actor->id, 'activity_date' => $started, 'daily_activity_log_id' => $log->id,
                    'position' => $this->headers->nextPosition($log, $actor), 'worker_count' => (int) $data['worker_count'],
                    'description' => $data['notes'] ?? null, 'obstacle_status' => 'none',
                ]);
                $activity->plantingDetail()->create(['seed_name' => $data['seed_name'], 'quantity_kg' => $data['quantity_kg'], 'planting_pattern' => $data['planting_pattern'] ?? null, 'area_ha' => $data['area_ha']]);
                foreach ($data['photos'] as $photo) {
                    $path = $photo->store('task-activities/'.$activity->id, 'local');
                    if ($path === false) {
                        throw new \RuntimeException('Foto tidak berhasil disimpan.');
                    }
                    $paths[] = $path;
                    ActivityPhoto::create(['activity_id' => $activity->id, 'image_url' => $path]);
                }
                $stage = PlantingCycleStage::visibleTo($actor, $team)->where('planting_cycle_id', $cycle->id)->where('code', 'planting')->lockForUpdate()->firstOrFail();
                $stage->forceFill(['status' => 'completed', 'started_at' => $started, 'completed_at' => CarbonImmutable::parse($data['work_date'].' '.$log->end_time, 'Asia/Jakarta')])->save();

                return $this->tasks->generateForSubblok($cycle, $actor);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
    }
}
