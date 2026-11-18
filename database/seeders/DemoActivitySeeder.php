<?php

namespace Database\Seeders;

use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\HarvestDetail;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\User;
use App\Services\Activity\DailyActivityLogService;
use App\Services\Activity\HarvestClosureService;
use App\Services\Activity\LegacyDemoHarvestRepairService;
use App\Services\Activity\PlantingCycleStageService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoActivitySeeder extends Seeder
{
    public function run(): void
    {
        $petugas = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['petugas']['email'])->firstOrFail();
        $team = $petugas->teams()->where('slug', 'farm-ic-demo')->firstOrFail();
        \Illuminate\Support\Facades\Gate::forUser($petugas)->authorize('viewAny', [Farm::class, $team]);
        $farm = Farm::visibleTo($petugas, $team)->where('team_id', $team->id)
            ->whereIn('name', DemoFarmSeeder::FARM_NAMES)->firstOrFail();
        $cycle = PlantingCycle::visibleTo($petugas, $team)->where('farm_id', $farm->id)->where('status', 'active')->orderByDesc('batch_number')->firstOrFail();
        $plantedAt = $cycle->planted_at ?? $cycle->planned_planting_at;
        if ($plantedAt === null) {
            throw new \LogicException('Siklus tanam demo harus memiliki tanggal tanam atau rencana tanam.');
        }

        $tasks = PlantingCycleTask::query()->where('planting_cycle_id', $cycle->id)
            ->orderBy('scheduled_date')->orderBy('offset_days')->orderBy('id')->get();
        $sopTask = fn (string $category, ?string $nameContains = null): ?PlantingCycleTask => $tasks->first(
            fn (PlantingCycleTask $task): bool => $task->activity_category === $category
                && ($nameContains === null || str_contains($task->name, $nameContains)),
        );
        $scheduled = function (string $category, ?string $nameContains, int $fallbackOffset) use ($sopTask, $plantedAt): string {
            return $sopTask($category, $nameContains)?->effectiveScheduledDate()?->toDateString()
                ?? $plantedAt->addDays($fallbackOffset)->toDateString();
        };
        $mappingDate = $plantedAt->subDays(12)->toDateString();
        $growthDate = $plantedAt->addDays(27)->toDateString();

        $this->call(BlockMappingActivityTypeSeeder::class);
        $this->call(SopActivityTypeSeeder::class);

        if (! DailyActivityLog::query()->where('farm_id', $farm->id)->exists()) {
            \Illuminate\Support\Facades\Gate::forUser($petugas)->authorize('create', [DailyActivityLog::class, $farm]);
            app(DailyActivityLogService::class)->create($farm, [
                'work_date' => $mappingDate,
                'worker_count' => 6,
                'recorded_by' => $petugas->id,
                'activities' => [[
                    'type' => 'block_mapping',
                    'block_mapping' => [
                        'block_name' => 'Blok 1',
                        'length_m' => 35,
                        'width_m' => 50,
                        'row_spacing_cm' => 40,
                        'plant_spacing_cm' => 20,
                        'rows_per_bed' => 2,
                    ],
                    'obstacle_status' => 'reported',
                    'obstacles' => 'Sebagian batas lahan mengikuti kontur lereng.',
                    'proposed_solution' => 'Sesuaikan panjang bedeng dengan batas lahan.',
                    'action_taken' => 'Batas blok ditandai di lapangan.',
                    'notes' => 'Pemetaan awal untuk persiapan bedengan.',
                ]],
            ]);
        }

        $this->seedBlockPreparation($farm, $petugas);
        $this->repairLegacyHarvest($farm, $petugas);

        $this->seedActivityWithDetail($farm, $cycle, $petugas, 'planting', 'Penanaman Blok 1', [
            'seed_name' => 'Benih jagung Bisi 18',
            'quantity_kg' => 18.500,
            'area_ha' => 4.2500,
        ], $plantedAt->toDateString());
        $this->seedActivityWithDetail($farm, $cycle, $petugas, 'maintenance', 'Pemupukan susulan pertama Blok 1', [
            'area_ha' => 4.2500,
        ], $scheduled('maintenance', 'Pemupukan susulan pertama', 14), $sopTask('maintenance', 'Pemupukan susulan pertama'));
        $this->seedActivityWithDetail($farm, $cycle, $petugas, 'maintenance', 'Irigasi sore Blok 1', [
            'area_ha' => 4.2500,
        ], $scheduled('maintenance', 'Pengairan awal', 7), $sopTask('maintenance', 'Pengairan awal'));
        $this->seedActivityWithDetail($farm, $cycle, $petugas, 'maintenance', 'Pengendalian ulat Blok 1', [
            'area_ha' => 4.2500,
        ], $scheduled('maintenance', 'Pengendalian hama', 30), $sopTask('maintenance', 'Pengendalian hama'));
        $this->seedActivityWithDetail($farm, $cycle, $petugas, 'incident', 'Batas bedeng longsor ringan', [
            'incident_category' => 'Erosi lahan',
            'severity' => 'low',
            'affected_area_ha' => 0.0200,
            'proposed_solution' => 'Perkuat tepi bedeng dengan mulsa dan patok kontur.',
        ], $growthDate);
        $this->seedGrowthObservation($farm, $cycle, $petugas, $growthDate);

        $previous = PlantingCycle::visibleTo($petugas, $team)
            ->where('farm_id', $farm->id)
            ->where('subblock_label', 'A')->whereHas('block', fn ($q) => $q->where('name', 'Blok Utara'))
            ->first();
        if ($previous instanceof PlantingCycle) {
            if ($previous->sop_template_version_id === null && $cycle->sop_template_version_id !== null) {
                $admin = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['admin']['email'])->firstOrFail();
                \Illuminate\Support\Facades\Gate::forUser($admin)->authorize('update', $previous);
                $previous->forceFill(['sop_template_version_id' => $cycle->sop_template_version_id])->save();
            }
            $this->seedActivityWithDetail($farm, $previous, $petugas, 'planting', 'Penanaman Blok Utara sebelumnya', [
                'seed_name' => 'Benih jagung Bisi 18', 'quantity_kg' => 18.500,
                'area_ha' => 4.2500,
            ], $previous->planted_at->toDateString());
            $this->seedPreviousHarvest($farm, $previous, $petugas);
            if ($previous->fresh()->status === 'active') {
                $admin = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['admin']['email'])->firstOrFail();
                \Illuminate\Support\Facades\Gate::forUser($admin)->authorize('configure', $previous);
                app(HarvestClosureService::class)->close($admin, $team, $previous);
            }
        }

        $planting = FarmActivity::query()->where('farm_id', $farm->id)->where('description', 'Penanaman Blok 1')->first();
        if ($planting) {
            ActivityPhoto::query()->firstOrCreate([
                'activity_id' => $planting->id,
                'label' => 'Kondisi awal bedengan',
            ], [
                'caption' => 'Dokumentasi kondisi awal bedengan Blok 1.',
                'image_url' => \Database\Seeders\Support\DemoUpload::image('activity', 'farms/'.$farm->id.'/'.$planting->id.'/demo.jpg'),
                'uploaded_at' => now(),
            ]);
        }
    }

    private function repairLegacyHarvest(Farm $farm, User $actor): void
    {
        if (! app()->environment(['local', 'testing']) || DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        $team = $farm->team;
        \Illuminate\Support\Facades\Gate::forUser($actor)->authorize('viewAny', [FarmActivity::class, $team]);
        \Illuminate\Support\Facades\Gate::forUser($actor)->authorize('viewAny', [DailyActivityLog::class, $team]);
        \Illuminate\Support\Facades\Gate::forUser($actor)->authorize('viewAny', [ActivityType::class, $team]);
        \Illuminate\Support\Facades\Gate::forUser($actor)->authorize('viewAny', [HarvestDetail::class, $team]);
        $activities = FarmActivity::visibleTo($actor, $team)->where('farm_id', $farm->id)
            ->where('description', 'Panen percobaan Blok 2')->whereNull('daily_activity_log_id')->get();
        $header = DailyActivityLog::visibleTo($actor, $team)->where('farm_id', $farm->id)
            ->whereDate('work_date', '2026-12-15')->first();
        if ($header !== null) {
            \Illuminate\Support\Facades\Gate::forUser($actor)->authorize('view', $header);
        }
        foreach ($activities as $activity) {
            \Illuminate\Support\Facades\Gate::forUser($actor)->authorize('update', $activity);
            app(LegacyDemoHarvestRepairService::class)->repair($actor, $activity, $header);
        }
    }

    /** @param array<string, mixed> $detail */
    private function seedActivityWithDetail(Farm $farm, PlantingCycle $cycle, User $user, string $typeCode, string $description, array $detail, string $activityDate, ?PlantingCycleTask $task = null): void
    {
        $type = ActivityType::query()->where('code', $typeCode)->firstOrFail();
        if (FarmActivity::query()->where('farm_id', $farm->id)->where('description', $description)->exists()) {
            return;
        }

        DB::transaction(function () use ($farm, $cycle, $user, $type, $description, $detail, $activityDate, $typeCode, $task): void {
            [$log, $position] = $this->header($farm, $user, $activityDate);
            $activity = FarmActivity::query()->create([
                'farm_id' => $farm->id,
                'planting_cycle_id' => $cycle->id,
                'activity_type_id' => $type->id,
                'performed_by' => $user->id,
                'activity_date' => CarbonImmutable::parse($activityDate.' 09:00', 'Asia/Jakarta'),
                'description' => $description,
                'status' => 'completed',
                'daily_activity_log_id' => $log->id, 'position' => $position, 'worker_count' => 6,
            ]);
            $activity->forceFill(['block_id' => $cycle->block_id])->save();
            if ($task !== null) {
                $activity->forceFill([
                    'planting_cycle_task_id' => $task->id,
                    'task_details' => json_encode($detail, JSON_THROW_ON_ERROR),
                ])->save();
                if (in_array($task->status, ['pending', 'in_progress'], true)) {
                    if ($typeCode === 'harvest') {
                        $task->forceFill(['status' => 'in_progress'])->save();
                    } else {
                        $task->forceFill([
                            'status' => 'completed',
                            'completed_at' => $activity->activity_date,
                            'completed_by' => $user->id,
                            'completion_note' => 'Tercatat melalui laporan aktivitas demo.',
                            'skip_reason' => null,
                        ])->save();
                    }
                }
            }
            $relation = match ($typeCode) {
                'planting' => 'plantingDetail',
                'maintenance' => null,
                'incident' => 'incidentDetail',
                'harvest' => 'harvestDetail',
                default => throw new \LogicException("Unsupported seeded activity type {$typeCode}"),
            };
            if ($relation !== null) {
                $activity->{$relation}()->create($detail);
            } elseif ($task === null) {
                $activity->forceFill([
                    'task_details' => json_encode($detail, JSON_THROW_ON_ERROR),
                ])->save();
            }
            app(PlantingCycleStageService::class)->record($user, $farm->team, $activity, $type);
        });
    }

    private function seedPreviousHarvest(Farm $farm, PlantingCycle $cycle, User $user): void
    {
        $description = 'Panen Blok Utara putaran sebelumnya';
        if (FarmActivity::query()->where('farm_id', $farm->id)->where('description', $description)->exists()) {
            return;
        }

        $harvestedAt = $cycle->estimated_harvest_at ?? $cycle->planted_at?->addDays(90);
        if ($harvestedAt === null) {
            throw new \LogicException('Siklus panen demo harus memiliki tanggal panen.');
        }
        $stage = $cycle->stages()->where('code', 'harvest')->first();
        $task = PlantingCycleTask::query()->firstOrCreate([
            'planting_cycle_id' => $cycle->id,
            'activity_category' => 'harvest',
            'activity_subcategory' => null,
        ], [
            'planting_cycle_stage_id' => $stage?->id,
            'name' => 'Panen',
            'instructions' => 'Panen saat tongkol matang fisiologis; catat luas, berat, dan kualitas hasil.',
            'offset_days' => 90,
            'tolerance_days' => 2,
            'scheduled_date' => $harvestedAt->toDateString(),
            'due_date' => $harvestedAt->addDays(2)->toDateString(),
            'is_required' => true,
            'status' => 'pending',
        ]);
        $this->seedActivityWithDetail($farm, $cycle, $user, 'harvest', $description, [
            'harvested_area_ha' => 4.2500,
            'gross_weight_ton' => 21.000,
            'color' => 'Kuning keemasan',
            'ripeness' => 'Matang panen',
            'cleanliness' => 'Bersih setelah sortasi',
        ], $harvestedAt->toDateString(), $task);
    }

    private function seedGrowthObservation(Farm $farm, PlantingCycle $cycle, User $user, string $activityDate): void
    {
        $type = ActivityType::query()->where('code', 'maintenance')->firstOrFail();
        if (FarmActivity::query()->where('farm_id', $farm->id)->where('description', 'Observasi pertumbuhan minggu ke-4')->exists()) {
            return;
        }

        DB::transaction(function () use ($farm, $cycle, $user, $type, $activityDate): void {
            [$log, $position] = $this->header($farm, $user, $activityDate);
            $activity = FarmActivity::query()->create([
                'farm_id' => $farm->id,
                'planting_cycle_id' => $cycle->id,
                'activity_type_id' => $type->id,
                'performed_by' => $user->id,
                'activity_date' => CarbonImmutable::parse($activityDate.' 10:00', 'Asia/Jakarta'),
                'description' => 'Observasi pertumbuhan minggu ke-4',
                'status' => 'completed',
                'daily_activity_log_id' => $log->id, 'position' => $position, 'worker_count' => 6,
            ]);
            $activity->forceFill([
                'block_id' => $cycle->block_id,
                'task_details' => json_encode(['area_ha' => '4.2500'], JSON_THROW_ON_ERROR),
            ])->save();
            $activity->observation()->create([
                'planting_cycle_id' => $cycle->id,
                'observed_by' => $user->id,
                'observed_at' => CarbonImmutable::parse($activityDate.' 10:00', 'Asia/Jakarta'),
                'growth_phase' => 'Vegetatif',
                'average_height_cm' => 48.50,
                'disease_indication' => 'Tidak terindikasi',
                'pest_indication' => 'Ringan',
                'health_status' => 'sehat',
                'growth_percentage' => 72.00,
                'plant_condition' => 'Daun hijau, pertumbuhan seragam.',
                'notes' => 'Perlu pemantauan ulat pada sisi timur blok.',
            ]);
            app(PlantingCycleStageService::class)->record($user, $farm->team, $activity, $type);
        });
    }

    private function seedBlockPreparation(Farm $farm, User $actor): void
    {
        $team = $farm->team;
        \Illuminate\Support\Facades\Gate::forUser($actor)->authorize('viewAny', [FarmBlock::class, $team]);
        $blocks = FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->whereIn('name', ['Blok Utara', 'Blok Selatan'])->get();
        foreach ($blocks as $block) {
            $isNorth = $block->name === 'Blok Utara';
            $cleaningDate = $isNorth ? '2026-05-17' : '2026-09-20';
            $soilDate = $isNorth ? '2026-05-20' : '2026-09-23';
            $reports = [
                ['land_cleaning', 'Pembersihan lahan percontohan '.$block->name, $cleaningDate, 'landCleaningDetail', ['area_ha' => '1.0000']],
                ['soil_preparation', 'Pengolahan tanah percontohan '.$block->name, $soilDate, null, ['area_ha' => '1.0000']],
            ];
            if ($isNorth) {
                $reports[] = ['base_fertilization', 'Pemupukan kandang percontohan '.$block->name, '2026-05-25', 'fertilizationDetail', [
                    'fertilization_stage' => 'kandang', 'area_ha' => '1.0000',
                ]];
            }
            foreach ($reports as [$code, $description, $date, $relation, $detail]) {
                if (FarmActivity::visibleTo($actor, $team)->where('farm_id', $farm->id)->where('description', $description)->exists()) {
                    continue;
                }
                $type = ActivityType::visibleTo($actor, $team)->where('code', $code)->firstOrFail();
                \Illuminate\Support\Facades\Gate::forUser($actor)->authorize('recordVoluntary', [FarmActivity::class, $farm, $type]);
                DB::transaction(function () use ($farm, $actor, $block, $type, $description, $date, $relation, $detail): void {
                    [$log, $position] = $this->header($farm, $actor, $date);
                    $activity = new FarmActivity([
                        'farm_id' => $farm->id, 'activity_type_id' => $type->id, 'performed_by' => $actor->id,
                        'activity_date' => CarbonImmutable::parse($date.' 09:00', 'Asia/Jakarta'),
                        'description' => $description, 'status' => 'completed', 'daily_activity_log_id' => $log->id,
                        'position' => $position, 'worker_count' => 6,
                    ]);
                    $activity->forceFill([
                        'block_id' => $block->id,
                        ...($relation === null ? ['task_details' => json_encode($detail, JSON_THROW_ON_ERROR)] : []),
                    ])->save();
                    if ($relation !== null) {
                        $activity->{$relation}()->create($detail);
                    }
                    app(PlantingCycleStageService::class)->record($actor, $farm->team, $activity, $type);
                });
            }
        }
    }

    /** @return array{DailyActivityLog, int} */
    private function header(Farm $farm, User $actor, string $date): array
    {
        \Illuminate\Support\Facades\Gate::forUser($actor)->authorize('create', [DailyActivityLog::class, $farm]);
        Farm::visibleTo($actor, $farm->team)->whereKey($farm->id)->lockForUpdate()->firstOrFail();
        $headers = app(\App\Services\Activity\DailyActivityHeaderService::class);
        $log = $headers->resolve($farm, $actor, $date);

        return [$log, $headers->nextPosition($log, $actor)];
    }
}
