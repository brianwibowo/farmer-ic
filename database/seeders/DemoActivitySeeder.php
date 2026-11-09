<?php

namespace Database\Seeders;

use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\User;
use App\Services\Activity\DailyActivityLogService;
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
        $cycle = PlantingCycle::visibleTo($petugas, $team)->where('farm_id', $farm->id)->where('status', 'active')->firstOrFail();
        $plantedAt = $cycle->planted_at ?? $cycle->planned_planting_at;
        if ($plantedAt === null) {
            throw new \LogicException('Siklus tanam demo harus memiliki tanggal tanam atau rencana tanam.');
        }

        $tasks = PlantingCycleTask::query()->where('planting_cycle_id', $cycle->id)
            ->orderBy('scheduled_date')->orderBy('offset_days')->orderBy('id')->get();
        $sopTask = fn (string $category, ?string $subcategory): ?PlantingCycleTask => $tasks->first(
            fn (PlantingCycleTask $task): bool => $task->activity_category === $category
                && $task->activity_subcategory === $subcategory,
        );
        $scheduled = function (string $category, ?string $subcategory, int $fallbackOffset) use ($sopTask, $plantedAt): string {
            return $sopTask($category, $subcategory)?->effectiveScheduledDate()?->toDateString()
                ?? $plantedAt->addDays($fallbackOffset)->toDateString();
        };
        $mappingDate = $plantedAt->subDays(12)->toDateString();
        $growthDate = $plantedAt->addDays(27)->toDateString();

        $this->call(BlockMappingActivityTypeSeeder::class);

        if (! DailyActivityLog::query()->where('farm_id', $farm->id)->exists()) {
            \Illuminate\Support\Facades\Gate::forUser($petugas)->authorize('create', [DailyActivityLog::class, $farm]);
            app(DailyActivityLogService::class)->create($farm, [
                'work_date' => $mappingDate,
                'start_time' => '08:00',
                'end_time' => '16:00',
                'worker_count' => 6,
                'recorded_by' => $petugas->id,
                'notes' => 'Pemetaan awal untuk persiapan bedengan.',
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
                ]],
            ]);
        }

        $this->seedActivityWithDetail($farm, $cycle, $petugas, 'planting', 'Penanaman Blok 1', [
            'seed_name' => 'Benih jagung Bisi 18',
            'quantity_kg' => 18.500,
            'planting_pattern' => 'Jarak tanam 20 x 40 cm',
            'area_ha' => 4.2500,
        ], $plantedAt->toDateString());
        $this->seedActivityWithDetail($farm, $cycle, $petugas, 'follow_up_fertilization', 'Pemupukan susulan pertama Blok 1', [
            'fertilization_stage' => 'Pemupukan susulan',
            'fertilizer_type' => 'NPK 15-15-15',
            'brand_name' => 'NPK',
            'subsidy_status' => 'non_subsidized',
            'application_method' => 'Tabur di sisi bedeng',
            'quantity_kg' => 125.000,
        ], $scheduled('maintenance', 'follow_up_fertilization', 14), $sopTask('maintenance', 'follow_up_fertilization'));
        $this->seedActivityWithDetail($farm, $cycle, $petugas, 'irrigation', 'Irigasi sore Blok 1', [
            'water_source' => 'Embung kebun',
            'irrigation_method' => 'Selang portable',
            'machine_type' => 'Pompa listrik',
            'duration_hours' => 2.50,
        ], $scheduled('maintenance', 'irrigation', 7), $sopTask('maintenance', 'irrigation'));
        $this->seedActivityWithDetail($farm, $cycle, $petugas, 'pest_control', 'Pengendalian ulat Blok 1', [
            'pesticide_category' => 'Biologis',
            'purpose' => 'Pengendalian ulat daun',
            'brand_name' => 'Bt',
            'active_ingredient' => 'Bacillus thuringiensis',
            'dosage_per_tank' => '25 ml / 14 liter',
            'tank_size_liters' => 14.00,
            'tank_count' => 6,
        ], $scheduled('maintenance', 'pest_control', 30), $sopTask('maintenance', 'pest_control'));
        $this->seedActivityWithDetail($farm, $cycle, $petugas, 'incident', 'Batas bedeng longsor ringan', [
            'incident_category' => 'Erosi lahan',
            'severity' => 'low',
            'affected_area_ha' => 0.0200,
            'proposed_solution' => 'Perkuat tepi bedeng dengan mulsa dan patok kontur.',
        ], $growthDate);
        $this->seedGrowthObservation($farm, $cycle, $petugas, $growthDate);

        $previous = PlantingCycle::visibleTo($petugas, $team)
            ->where('farm_id', $farm->id)
            ->where('status', 'harvested')
            ->orderByDesc('planted_at')
            ->orderByDesc('batch_number')
            ->first();
        if ($previous instanceof PlantingCycle) {
            $this->seedPreviousHarvest($farm, $previous, $petugas);
        }

        $planting = FarmActivity::query()->where('farm_id', $farm->id)->where('description', 'Penanaman Blok 1')->first();
        if ($planting) {
            ActivityPhoto::query()->firstOrCreate([
                'activity_id' => $planting->id,
                'label' => 'Kondisi awal bedengan',
            ], [
                'caption' => 'Dokumentasi kondisi awal bedengan Blok 1.',
                'image_url' => '/images/kebun-placeholder.svg',
                'uploaded_at' => now(),
            ]);
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
            if ($task !== null) {
                $activity->forceFill([
                    'planting_cycle_task_id' => $task->id,
                    'task_details' => json_encode($detail, JSON_THROW_ON_ERROR),
                ])->save();
                if (in_array($task->status, ['pending', 'in_progress'], true)) {
                    $task->forceFill([
                        'status' => 'completed',
                        'completed_at' => $activity->activity_date,
                        'completed_by' => $user->id,
                        'completion_note' => 'Tercatat melalui laporan aktivitas demo.',
                        'skip_reason' => null,
                    ])->save();
                }
            }
            $relation = match ($typeCode) {
                'planting' => 'plantingDetail',
                'follow_up_fertilization' => 'fertilizationDetail',
                'irrigation' => 'irrigationDetail',
                'pest_control' => 'pesticideDetail',
                'incident' => 'incidentDetail',
                'harvest' => 'harvestDetail',
                default => throw new \LogicException("Unsupported seeded activity type {$typeCode}"),
            };
            $activity->{$relation}()->create($detail);
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
            'moisture_percentage' => 17.50,
            'color' => 'Kuning keemasan',
            'ripeness' => 'Matang panen',
            'cleanliness' => 'Bersih setelah sortasi',
        ], $harvestedAt->toDateString(), $task);
    }

    private function seedGrowthObservation(Farm $farm, PlantingCycle $cycle, User $user, string $activityDate): void
    {
        $type = ActivityType::query()->where('code', 'growth_check')->firstOrFail();
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
        });
    }

    /** @return array{DailyActivityLog, int} */
    private function header(Farm $farm, User $actor, string $date): array
    {
        \Illuminate\Support\Facades\Gate::forUser($actor)->authorize('create', [DailyActivityLog::class, $farm]);
        Farm::visibleTo($actor, $farm->team)->whereKey($farm->id)->lockForUpdate()->firstOrFail();
        $headers = app(\App\Services\Activity\DailyActivityHeaderService::class);
        $log = $headers->resolve($farm, $actor, [
            'work_date' => $date, 'start_time' => '08:00', 'end_time' => '16:00',
            'daily_notes' => 'Kegiatan lapangan bersama tim Kebun Cileuksa.',
        ]);

        return [$log, $headers->nextPosition($log, $actor)];
    }
}
