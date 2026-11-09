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
    public function __construct(private readonly PlantingCycleTaskService $tasks, private readonly DailyActivityHeaderService $headers, private readonly ActivitySequenceService $sequence) {}

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
            ...$cycle->only(['id', 'batch_number', 'subblock_label', 'status', 'reserved_area_ha', 'planted_area_ha', 'planting_started_on']),
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

        $mapping = \App\Models\BlockMappingDetail::visibleTo($actor, $team)
            ->whereHas('activity', fn ($q) => $q->visibleTo($actor, $team)->where('planting_cycle_id', $cycle->id))->first();

        return [
            'plantingPattern' => $mapping === null ? '' : implode(' · ', array_filter([
                $mapping->getAttribute('plant_spacing_cm') ? 'Jarak tanam '.$mapping->getAttribute('plant_spacing_cm').' cm' : null,
                $mapping->getAttribute('row_spacing_cm') ? 'Jarak baris '.$mapping->getAttribute('row_spacing_cm').' cm' : null,
                $mapping->getAttribute('rows_per_bed') ? $mapping->getAttribute('rows_per_bed').' baris per bedeng' : null,
            ])),
            'cycle' => $this->context($actor, $cycle),
            'varieties' => CropVariety::visibleTo($actor, $team)->orderBy('name')->get(['id', 'name'])->toArray(),
            'reports' => $reports->map(function (FarmActivity $activity) use ($actor, $team): array {
                $detail = PlantingDetail::visibleTo($actor, $team)->where('activity_id', $activity->id)->first();

                return [
                    'id' => $activity->id, 'date' => $activity->activity_date->setTimezone('Asia/Jakarta')->toDateString(),
                    'notes' => $activity->description, 'detail' => $detail?->only(['seed_name', 'quantity_kg', 'planting_pattern', 'area_ha']),
                    'input' => json_decode((string) $activity->getAttribute('task_details'), true),
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
        if (($data['schema'] ?? null) === 'taxonomy') {
            $data['area_ha'] = round($data['area'] / ($data['area_unit'] === 'm²' ? 10000 : 1), 4);
            $data['quantity_kg'] = match ($data['quantity_unit']) {
                'kg' => $data['quantity'], 'sak' => $data['quantity'] * $data['bag_size'], default => null,
            };
            if ($data['area_ha'] <= 0 || ($data['quantity_kg'] ?? 0) > 999999999.999) {
                throw ValidationException::withMessages(['area' => 'Luas minimal 1 m²; jumlah benih tidak boleh melebihi batas penyimpanan.']);
            }
            if ($data['quantity_unit'] === 'butir' && floor((float) $data['quantity']) !== (float) $data['quantity']) {
                throw ValidationException::withMessages(['quantity' => 'Jumlah butir harus bilangan bulat.']);
            }
        }
        $paths = [];
        try {
            return DB::transaction(function () use ($actor, $cycle, $data, &$paths): array {
                $team = $cycle->farm->team;
                $farm = Farm::visibleTo($actor, $team)->whereKey($cycle->farm_id)->lockForUpdate()->firstOrFail();
                $actor = User::query()->whereKey($actor->id)->sharedLock()->firstOrFail();
                $cycle = PlantingCycle::visibleTo($actor, $team)->whereKey($cycle->id)->where('farm_id', $farm->id)->lockForUpdate()->firstOrFail();
                if (! in_array($cycle->status, ['planned', 'active'], true)) {
                    throw ValidationException::withMessages(['cycle' => 'Subblok sudah ditutup.']);
                }
                $this->sequence->ensureAllowed($actor, $cycle, 'planting', 'cycle');
                $dates = $this->plantingDates($actor, $cycle);
                $dates[] = $data['work_date'];
                $dates = array_values(array_unique($dates));
                sort($dates);
                if (CarbonImmutable::parse($dates[0])->diffInDays(CarbonImmutable::parse(end($dates))) > 2) {
                    throw ValidationException::withMessages(['work_date' => 'Penanaman dibatasi tiga hari kalender sejak tanam pertama. Pilih subblok lain yang telah disiapkan Admin.']);
                }
                $round = PlantingRound::visibleTo($actor, $team)->whereKey($cycle->getAttribute('planting_round_id'))->sharedLock()->firstOrFail();
                $block = FarmBlock::visibleTo($actor, $team)->whereKey($round->block_id)->sharedLock()->firstOrFail();
                if (! $block->is_active || $round->status !== 'active') {
                    throw ValidationException::withMessages(['cycle' => 'Blok dan periode harus aktif untuk mencatat penanaman.']);
                }
                $totalArea = LandArea::squareMetres($data['area_ha']) + LandArea::squareMetres($cycle->planted_area_ha ?? 0);
                if ($totalArea > LandArea::squareMetres($cycle->getAttribute('reserved_area_ha'))) {
                    throw ValidationException::withMessages([($data['schema'] ?? null) === 'taxonomy' ? 'area' : 'area_ha' => 'Akumulasi luas penanaman tidak boleh melebihi alokasi subblok. Isi luas tambahan untuk laporan ini.']);
                }
                $variety = CropVariety::visibleTo($actor, $team)->whereKey($data['variety_id'])->first();
                if ($variety === null) {
                    throw ValidationException::withMessages(['variety_id' => 'Varietas tidak tersedia atau tidak dapat diakses.']);
                }
                if ($cycle->planted_at !== null && $cycle->variety_id !== $variety->id) {
                    throw ValidationException::withMessages(['variety_id' => 'Penanaman lanjutan harus memakai varietas yang sama pada subblok ini.']);
                }
                if ($cycle->sop_template_version_id !== null && $cycle->sopTemplateVersion?->sopTemplate->commodity_id !== $variety->commodity_id) {
                    throw ValidationException::withMessages(['variety_id' => 'Komoditas varietas harus sesuai versi SOP yang sudah terikat pada subblok.']);
                }
                $type = ActivityType::visibleTo($actor, $team)->where('code', 'planting')->first();
                if ($type === null) {
                    throw ValidationException::withMessages(['cycle' => 'Referensi aktivitas Penanaman belum tersedia. Hubungi admin.']);
                }
                $cycle->forceFill(['planting_started_on' => $dates[0], 'planted_at' => $dates[1] ?? $dates[0], 'planting_window_checked_at' => null,
                    'variety_id' => $variety->id, 'planted_area_ha' => $totalArea / 10000, 'status' => 'active'])->save();
                $log = $this->headers->resolve($farm, $actor, $data);
                $started = CarbonImmutable::parse($data['work_date'].' '.$log->start_time, 'Asia/Jakarta');
                $activity = FarmActivity::create([
                    'farm_id' => $farm->id, 'planting_cycle_id' => $cycle->id, 'activity_type_id' => $type->id,
                    'performed_by' => $actor->id, 'activity_date' => $started, 'daily_activity_log_id' => $log->id,
                    'position' => $this->headers->nextPosition($log, $actor), 'worker_count' => (int) $data['worker_count'],
                    'description' => $data['notes'] ?? null, 'obstacle_status' => 'none',
                ]);
                $activity->forceFill(['block_id' => $block->id])->save();
                if (($data['schema'] ?? null) === 'taxonomy') {
                    $activity->forceFill(['task_details' => json_encode(array_intersect_key($data, array_flip(['area', 'area_unit', 'quantity', 'quantity_unit', 'bag_size', 'depth_cm'])), JSON_THROW_ON_ERROR)])->save();
                }
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
                $finished = $log->end_time === null ? $started : CarbonImmutable::parse($data['work_date'].' '.$log->end_time, 'Asia/Jakarta');
                $stage->forceFill(['status' => 'completed',
                    'started_at' => $stage->started_at === null || $started->lt($stage->started_at) ? $started : $stage->started_at,
                    'completed_at' => $stage->completed_at === null || $finished->gt($stage->completed_at) ? $finished : $stage->completed_at,
                ])->save();

                return $this->tasks->generateForSubblok($cycle, $actor);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
    }

    /** Distinct work dates, including historical realization whose reports were cleared.
     * @return list<string>
     */
    public function plantingDates(User $actor, PlantingCycle $cycle): array
    {
        $team = $cycle->farm->team;
        $dates = FarmActivity::visibleTo($actor, $team)->where('planting_cycle_id', $cycle->id)
            ->whereHas('activityType', fn ($query) => $query->visibleTo($actor, $team)->where('code', 'planting'))
            ->get(['activity_date'])->map(fn ($activity) => $activity->activity_date->setTimezone('Asia/Jakarta')->toDateString())->all();
        if ($cycle->getAttribute('planting_started_on') !== null) {
            $dates[] = substr((string) $cycle->getAttribute('planting_started_on'), 0, 10);
        } elseif ($cycle->planted_at !== null) {
            $dates[] = $cycle->planted_at->toDateString();
        }
        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }

    /** Caller authorizes cycle update and task synchronization. */
    public function reconcile(User $actor, PlantingCycle $cycle): void
    {
        DB::transaction(function () use ($actor, $cycle): void {
            $team = $cycle->farm->team;
            Farm::visibleTo($actor, $team)->whereKey($cycle->farm_id)->lockForUpdate()->firstOrFail();
            $cycle = PlantingCycle::visibleTo($actor, $team)->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $dates = $this->plantingDates($actor, $cycle);
            if (! $dates || ! in_array($cycle->status, ['planned', 'active'], true)) {
                return;
            }
            $cycle->forceFill(['planted_at' => $dates[1] ?? $dates[0]])->save();
            $this->tasks->generateForSubblok($cycle, $actor);
            if (now('Asia/Jakarta')->toDateString() > CarbonImmutable::parse($dates[0])->addDays(2)->toDateString()) {
                $cycle->forceFill(['planting_window_checked_at' => now()])->save();
            }
        });
    }
}
