<?php

namespace Database\Seeders;

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
        $team = Team::query()->where('slug', 'farm-ic-demo')->firstOrFail();
        $variety = CropVariety::query()->where('name', 'Bisi 18')->firstOrFail();
        $admin = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['admin']['email'])->firstOrFail();

        $template = SopTemplate::query()->firstOrCreate([
            'team_id' => $team->id,
            'variety_id' => $variety->id,
            'name' => 'SOP Budidaya Jagung Bisi 18',
        ], [
            'description' => 'Panduan budidaya jagung hibrida Bisi 18 dari penanaman sampai panen.',
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
            ['category' => 'planting', 'subcategory' => null, 'name' => 'Tanam benih Bisi 18', 'offset' => 0, 'instructions' => 'Tanam benih dengan jarak tanam 20 x 40 cm dan kedalaman yang seragam.'],
            ['category' => 'maintenance', 'subcategory' => 'irrigation', 'name' => 'Pengairan awal', 'offset' => 7, 'instructions' => 'Pastikan kelembapan tanah cukup setelah tanam; hindari genangan.'],
            ['category' => 'maintenance', 'subcategory' => 'follow_up_fertilization', 'name' => 'Pemupukan susulan pertama', 'offset' => 14, 'instructions' => 'Aplikasikan NPK sesuai dosis anjuran dan hindari kontak langsung dengan batang.'],
            ['category' => 'maintenance', 'subcategory' => 'pest_control', 'name' => 'Pengendalian hama dan penyakit', 'offset' => 30, 'instructions' => 'Lakukan pengamatan dan aplikasikan pengendalian hanya jika ditemukan serangan.'],
            ['category' => 'maintenance', 'subcategory' => 'follow_up_fertilization', 'name' => 'Pemupukan lanjutan', 'offset' => 45, 'instructions' => 'Berikan pupuk susulan sesuai fase pertumbuhan dan hasil pengamatan.'],
            ['category' => 'harvest', 'subcategory' => null, 'name' => 'Panen', 'offset' => 90, 'instructions' => 'Panen saat tongkol matang fisiologis; catat luas, berat, dan kualitas hasil.'],
        ];

        foreach ($tasks as $task) {
            $version->tasks()->create([
                'activity_category' => $task['category'],
                'activity_subcategory' => $task['subcategory'],
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
