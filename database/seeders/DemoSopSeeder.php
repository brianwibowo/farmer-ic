<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use App\Models\CropVariety;
use App\Models\SopTemplate;
use App\Models\Team;
use App\Models\User;
use App\Services\SopTemplateService;
use Illuminate\Database\Seeder;

class DemoSopSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SopActivityTypeSeeder::class);

        $team = Team::query()->where('slug', 'farm-ic-demo')->firstOrFail();
        $variety = CropVariety::query()->where('name', 'Bisi 18')->firstOrFail();
        $admin = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['admin']['email'])->firstOrFail();

        $template = SopTemplate::query()->firstOrCreate([
            'team_id' => $team->id,
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
            ['code' => 'land_cleaning', 'stage' => 'land_cleaning', 'name' => 'Bersihkan lahan', 'offset' => -14, 'instructions' => 'Bersihkan gulma, sisa tanaman, dan material yang mengganggu area tanam.'],
            ['code' => 'soil_preparation', 'stage' => 'soil_preparation', 'name' => 'Olah tanah dan bentuk bedengan', 'offset' => -7, 'instructions' => 'Bajak dan gemburkan tanah, lalu bentuk bedengan sesuai kontur lahan.'],
            ['code' => 'planting', 'stage' => 'planting', 'name' => 'Tanam benih Bisi 18', 'offset' => 0, 'instructions' => 'Tanam benih dengan jarak tanam 20 x 40 cm dan kedalaman yang seragam.'],
            ['code' => 'irrigation', 'stage' => 'maintenance', 'name' => 'Pengairan awal', 'offset' => 7, 'instructions' => 'Pastikan kelembapan tanah cukup setelah tanam; hindari genangan.'],
            ['code' => 'fertilization', 'stage' => 'maintenance', 'name' => 'Pemupukan susulan pertama', 'offset' => 14, 'instructions' => 'Aplikasikan NPK sesuai dosis anjuran dan hindari kontak langsung dengan batang.'],
            ['code' => 'pesticide', 'stage' => 'maintenance', 'name' => 'Pengendalian hama dan penyakit', 'offset' => 30, 'instructions' => 'Lakukan pengamatan dan aplikasikan pengendalian hanya jika ditemukan serangan.'],
            ['code' => 'fertilization', 'stage' => 'maintenance', 'name' => 'Pemupukan lanjutan', 'offset' => 45, 'instructions' => 'Berikan pupuk susulan sesuai fase pertumbuhan dan hasil pengamatan.'],
            ['code' => 'harvest', 'stage' => 'harvest', 'name' => 'Panen', 'offset' => 90, 'instructions' => 'Panen saat tongkol matang fisiologis; catat luas, berat, dan kualitas hasil.'],
        ];

        foreach ($tasks as $task) {
            $type = ActivityType::query()->where('code', $task['code'])->firstOrFail();
            $version->tasks()->create([
                'activity_type_id' => $type->id,
                'stage_code' => $task['stage'],
                'name' => $task['name'],
                'instructions' => $task['instructions'],
                'offset_days' => $task['offset'],
                'tolerance_days' => 2,
                'is_required' => true,
            ]);
        }

        app(SopTemplateService::class)->publish($version, $admin);
    }
}
