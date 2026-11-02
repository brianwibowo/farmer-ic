<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\SopTemplate;
use App\Models\SopTemplateTask;
use App\Models\SopTemplateVersion;
use App\Models\User;
use App\Services\SopTemplateService;
use Illuminate\Database\Seeder;

class DemoSopSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SopActivityTypeSeeder::class);

        $farm = Farm::query()->where('name', 'Kebun Cileuksa Demo')->firstOrFail();
        $variety = CropVariety::query()->where('name', 'Bisi 18')->firstOrFail();
        $admin = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['admin']['email'])->firstOrFail();

        $template = SopTemplate::query()->firstOrCreate([
            'farm_id' => $farm->id,
            'variety_id' => $variety->id,
            'name' => 'SOP Budidaya Jagung Bisi 18',
        ], [
            'description' => 'Panduan budidaya jagung hibrida Bisi 18 dari persiapan lahan sampai panen.',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        if ($template->versions()->exists()) {
            return;
        }

        $version = $template->versions()->create([
            'version' => 1,
            'notes' => 'Versi awal untuk demonstrasi SOP modular berbasis HST.',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $tasks = [
            ['code' => 'land_cleaning', 'stage' => 'land_cleaning', 'name' => 'Bersihkan lahan', 'anchor' => 'planting_date', 'offset' => -14, 'position' => 1, 'instructions' => 'Bersihkan gulma, sisa tanaman, dan material yang mengganggu area tanam.'],
            ['code' => 'soil_preparation', 'stage' => 'soil_preparation', 'name' => 'Olah tanah dan bentuk bedengan', 'anchor' => 'planting_date', 'offset' => -7, 'position' => 2, 'instructions' => 'Bajak dan gemburkan tanah, lalu bentuk bedengan sesuai kontur lahan.'],
            ['code' => 'planting', 'stage' => 'planting', 'name' => 'Tanam benih Bisi 18', 'anchor' => 'planting_date', 'offset' => 0, 'position' => 3, 'instructions' => 'Tanam benih dengan jarak tanam 20 x 40 cm dan kedalaman yang seragam.'],
            ['code' => 'irrigation', 'stage' => 'maintenance', 'name' => 'Pengairan awal', 'anchor' => 'planting_date', 'offset' => 7, 'position' => 4, 'instructions' => 'Pastikan kelembapan tanah cukup setelah tanam; hindari genangan.'],
            ['code' => 'fertilization', 'stage' => 'maintenance', 'name' => 'Pemupukan susulan pertama', 'anchor' => 'planting_date', 'offset' => 14, 'position' => 5, 'instructions' => 'Aplikasikan NPK sesuai dosis anjuran dan hindari kontak langsung dengan batang.'],
            ['code' => 'pesticide', 'stage' => 'maintenance', 'name' => 'Pengendalian hama dan penyakit', 'anchor' => 'planting_date', 'offset' => 30, 'position' => 6, 'instructions' => 'Lakukan pengamatan dan aplikasikan pengendalian hanya jika ditemukan serangan.'],
            ['code' => 'fertilization', 'stage' => 'maintenance', 'name' => 'Pemupukan lanjutan', 'anchor' => 'planting_date', 'offset' => 45, 'position' => 7, 'instructions' => 'Berikan pupuk susulan sesuai fase pertumbuhan dan hasil pengamatan.'],
            ['code' => 'harvest', 'stage' => 'harvest', 'name' => 'Panen', 'anchor' => 'planting_date', 'offset' => 90, 'position' => 8, 'instructions' => 'Panen saat tongkol matang fisiologis; catat luas, berat, dan kualitas hasil.'],
        ];

        foreach ($tasks as $task) {
            $type = ActivityType::query()->where('code', $task['code'])->firstOrFail();
            $version->tasks()->create([
                'activity_type_id' => $type->id,
                'stage_code' => $task['stage'],
                'name' => $task['name'],
                'instructions' => $task['instructions'],
                'schedule_anchor' => $task['anchor'],
                'offset_days' => $task['offset'],
                'tolerance_days' => 2,
                'position' => $task['position'],
                'is_required' => true,
            ]);
        }

        app(SopTemplateService::class)->publish($version, $admin);
    }
}
