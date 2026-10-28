<?php

namespace Database\Seeders;

use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\User;
use App\Services\Activity\DailyActivityLogService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoActivitySeeder extends Seeder
{
    public function run(): void
    {
        $farm = Farm::query()->where('name', 'Kebun Cileuksa Demo')->firstOrFail();
        $petugas = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['petugas']['email'])->firstOrFail();
        $cycle = PlantingCycle::query()->where('farm_id', $farm->id)->where('status', 'active')->firstOrFail();

        foreach ([
            ['code' => 'planting', 'name' => 'Penanaman', 'description' => 'Pencatatan proses penanaman.'],
            ['code' => 'fertilization', 'name' => 'Pemupukan', 'description' => 'Pencatatan pemupukan tanaman.'],
            ['code' => 'irrigation', 'name' => 'Irigasi', 'description' => 'Pencatatan pengairan lahan.'],
            ['code' => 'growth_check', 'name' => 'Pengecekan Pertumbuhan', 'description' => 'Observasi pertumbuhan dan kesehatan tanaman.'],
            ['code' => 'pesticide', 'name' => 'Pengendalian Hama', 'description' => 'Pencatatan aplikasi pengendalian hama.'],
            ['code' => 'incident', 'name' => 'Insiden Kebun', 'description' => 'Pencatatan insiden dan tindak lanjutnya.'],
            ['code' => 'harvest', 'name' => 'Panen', 'description' => 'Pencatatan hasil panen.'],
        ] as $type) {
            ActivityType::query()->firstOrCreate(['code' => $type['code']], $type);
        }
        $this->call(BlockMappingActivityTypeSeeder::class);

        if (! DailyActivityLog::query()->where('farm_id', $farm->id)->exists()) {
            app(DailyActivityLogService::class)->create($farm, [
                'work_date' => '2026-10-27',
                'start_time' => '08:00',
                'end_time' => '16:00',
                'worker_count' => 6,
                'recorded_by' => $petugas->id,
                'notes' => 'Pemetaan awal untuk persiapan bedengan demo.',
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
        ]);
        $this->seedActivityWithDetail($farm, $cycle, $petugas, 'fertilization', 'Pemupukan dasar Blok 1', [
            'fertilization_stage' => 'Pemupukan dasar',
            'fertilizer_type' => 'NPK 15-15-15',
            'brand_name' => 'NPK Demo',
            'subsidy_status' => 'non_subsidized',
            'application_method' => 'Tabur di sisi bedeng',
            'quantity_kg' => 125.000,
        ]);
        $this->seedActivityWithDetail($farm, $cycle, $petugas, 'irrigation', 'Irigasi sore Blok 1', [
            'water_source' => 'Embung kebun',
            'irrigation_method' => 'Selang portable',
            'machine_type' => 'Pompa listrik',
            'duration_hours' => 2.50,
        ]);
        $this->seedActivityWithDetail($farm, $cycle, $petugas, 'pesticide', 'Pengendalian ulat Blok 1', [
            'pesticide_category' => 'Biologis',
            'purpose' => 'Pengendalian ulat daun',
            'brand_name' => 'Bt Demo',
            'active_ingredient' => 'Bacillus thuringiensis',
            'dosage_per_tank' => '25 ml / 14 liter',
            'tank_size_liters' => 14.00,
            'tank_count' => 6,
        ]);
        $this->seedActivityWithDetail($farm, $cycle, $petugas, 'incident', 'Batas bedeng longsor ringan', [
            'incident_category' => 'Erosi lahan',
            'severity' => 'low',
            'affected_area_ha' => 0.0200,
            'proposed_solution' => 'Perkuat tepi bedeng dengan mulsa dan patok kontur.',
        ]);
        $this->seedGrowthObservation($farm, $cycle, $petugas);
        $this->seedActivityWithDetail($farm, $cycle, $petugas, 'harvest', 'Panen percobaan Blok 2', [
            'harvested_area_ha' => 0.5000,
            'gross_weight_ton' => 2.750,
            'moisture_percentage' => 18.50,
            'color' => 'Kuning cerah',
            'ripeness' => 'Matang panen',
            'cleanliness' => 'Bersih',
        ], '2026-12-15');

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
    private function seedActivityWithDetail(Farm $farm, PlantingCycle $cycle, User $user, string $typeCode, string $description, array $detail, string $activityDate = '2026-10-28'): void
    {
        $type = ActivityType::query()->where('code', $typeCode)->firstOrFail();
        if (FarmActivity::query()->where('farm_id', $farm->id)->where('description', $description)->exists()) {
            return;
        }

        DB::transaction(function () use ($farm, $cycle, $user, $type, $description, $detail, $activityDate, $typeCode): void {
            $activity = FarmActivity::query()->create([
                'farm_id' => $farm->id,
                'planting_cycle_id' => $cycle->id,
                'activity_type_id' => $type->id,
                'performed_by' => $user->id,
                'activity_date' => CarbonImmutable::parse($activityDate.' 09:00', 'Asia/Jakarta'),
                'description' => $description,
                'status' => 'completed',
            ]);
            $relation = match ($typeCode) {
                'planting' => 'plantingDetail',
                'fertilization' => 'fertilizationDetail',
                'irrigation' => 'irrigationDetail',
                'pesticide' => 'pesticideDetail',
                'incident' => 'incidentDetail',
                'harvest' => 'harvestDetail',
                default => throw new \LogicException("Unsupported demo activity type {$typeCode}"),
            };
            $activity->{$relation}()->create($detail);
        });
    }

    private function seedGrowthObservation(Farm $farm, PlantingCycle $cycle, User $user): void
    {
        $type = ActivityType::query()->where('code', 'growth_check')->firstOrFail();
        if (FarmActivity::query()->where('farm_id', $farm->id)->where('description', 'Observasi pertumbuhan minggu ke-4')->exists()) {
            return;
        }

        DB::transaction(function () use ($farm, $cycle, $user, $type): void {
            $activity = FarmActivity::query()->create([
                'farm_id' => $farm->id,
                'planting_cycle_id' => $cycle->id,
                'activity_type_id' => $type->id,
                'performed_by' => $user->id,
                'activity_date' => CarbonImmutable::parse('2026-10-28 10:00', 'Asia/Jakarta'),
                'description' => 'Observasi pertumbuhan minggu ke-4',
                'status' => 'completed',
            ]);
            $activity->observation()->create([
                'planting_cycle_id' => $cycle->id,
                'observed_by' => $user->id,
                'observed_at' => CarbonImmutable::parse('2026-10-28 10:00', 'Asia/Jakarta'),
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
}
